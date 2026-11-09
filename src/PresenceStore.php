<?php

namespace Mwyler\LiveVisitors;

class PresenceStore
{
    private string $filepath;

    public function __construct(string $cacheDir, private int $ttl = 30)
    {
        $this->filepath = $cacheDir . '/live-visitors-presence.json';
    }

    public function heartbeat(string $token, array $data = []): void
    {
        $this->withLock(function (array &$sessions) use ($token, $data) {
            $sessions[$token] = array_merge($data, [
                'token'     => $token,
                'last_seen' => time(),
            ]);
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

    private function withLock(callable $fn): void
    {
        $dir = dirname($this->filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fh = fopen($this->filepath, 'c+');
        if (!$fh) return;

        if (flock($fh, LOCK_EX)) {
            $sessions = $this->active($this->decode(stream_get_contents($fh)));

            $fn($sessions);

            // Encode before truncating, so a failed encode keeps the old
            // file instead of wiping every visitor.
            $json = json_encode($sessions, JSON_UNESCAPED_UNICODE);

            if ($json !== false) {
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, $json);
                fflush($fh);
            }

            flock($fh, LOCK_UN);
        }

        fclose($fh);
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
