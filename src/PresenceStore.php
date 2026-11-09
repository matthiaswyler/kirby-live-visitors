<?php

namespace Mwyler\LiveVisitors;

class PresenceStore
{
    private string $filepath;

    /**
     * @param int $maxVisitors Max present visitors in total (0 = no limit)
     * @param int $maxPerIp    Max present visitors per IP hash (0 = no limit)
     */
    public function __construct(
        string $cacheDir,
        private int $ttl = 30,
        private int $maxVisitors = 0,
        private int $maxPerIp = 0,
    ) {
        $this->filepath = $cacheDir . '/live-visitors-presence.json';
    }

    /**
     * Records a heartbeat. Returns false if a new visitor was rejected
     * because a limit was reached. Visitors already present always refresh.
     */
    public function heartbeat(string $token, string $ipHash = ''): bool
    {
        return $this->withLock(function (array &$sessions) use ($token, $ipHash): bool {
            if (!isset($sessions[$token]) && !$this->hasRoomFor($sessions, $ipHash)) {
                return false;
            }

            $sessions[$token] = [
                // Random public id that lives only while the visitor stays
                // present (expired entries are pruned before this runs), so
                // separate visits can't be linked through the API.
                'id'        => $sessions[$token]['id'] ?? bin2hex(random_bytes(4)),
                'ip'        => $ipHash,
                'last_seen' => time(),
            ];

            return true;
        });
    }

    public function getActive(): array
    {
        return array_values($this->active($this->read()));
    }

    private function active(array $sessions): array
    {
        $cutoff = time() - $this->ttl;

        return array_filter($sessions, fn ($s) => ($s['last_seen'] ?? 0) >= $cutoff);
    }

    private function hasRoomFor(array $sessions, string $ipHash): bool
    {
        if ($this->maxVisitors > 0 && count($sessions) >= $this->maxVisitors) {
            return false;
        }

        if ($this->maxPerIp > 0) {
            $sameIp = array_filter($sessions, fn ($s) => ($s['ip'] ?? null) === $ipHash);

            if (count($sameIp) >= $this->maxPerIp) {
                return false;
            }
        }

        return true;
    }

    /**
     * Runs $fn on the active sessions under an exclusive lock and writes
     * the result back only if $fn returns true, so rejected heartbeats
     * don't rewrite the file.
     */
    private function withLock(callable $fn): bool
    {
        $dir = dirname($this->filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fh = fopen($this->filepath, 'c+');
        if (!$fh) return false;

        $changed = false;

        if (flock($fh, LOCK_EX)) {
            $sessions = $this->active($this->decode(stream_get_contents($fh)));
            $changed  = $fn($sessions);

            // Encode before truncating, so a failed encode keeps the old
            // file instead of wiping every visitor.
            $json = $changed ? json_encode($sessions, JSON_UNESCAPED_UNICODE) : false;

            if ($json !== false) {
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, $json);
                fflush($fh);
            }

            flock($fh, LOCK_UN);
        }

        fclose($fh);

        return $changed;
    }

    private function read(): array
    {
        if (!file_exists($this->filepath)) return [];

        $fh = fopen($this->filepath, 'r');
        if (!$fh) return [];

        // A shared lock waits for a running heartbeat to finish rewriting
        // the file; without it, reads can land between truncate and write.
        $content = '';
        if (flock($fh, LOCK_SH)) {
            $content = stream_get_contents($fh);
            flock($fh, LOCK_UN);
        }

        fclose($fh);

        return $this->decode($content);
    }

    private function decode(string|false $content): array
    {
        $data = $content ? json_decode($content, true) : [];

        return is_array($data) ? $data : [];
    }
}
