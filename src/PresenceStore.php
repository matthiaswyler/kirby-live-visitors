<?php

namespace Mwyler\LiveVisitors;

class PresenceStore
{
    private string $filepath;

    public function __construct(string $cacheDir)
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

    public function getActive(int $ttl = 30): array
    {
        $cutoff   = time() - $ttl;
        $sessions = $this->read();

        return array_values(array_filter($sessions, function (array $s) use ($cutoff) {
            return ($s['last_seen'] ?? 0) >= $cutoff;
        }));
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
            $content  = stream_get_contents($fh);
            $sessions = $content ? json_decode($content, true) : [];
            if (!is_array($sessions)) $sessions = [];

            $cutoff   = time() - 120;
            $sessions = array_filter($sessions, fn($s) => ($s['last_seen'] ?? 0) >= $cutoff);

            $fn($sessions);

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($sessions, JSON_UNESCAPED_UNICODE));
            fflush($fh);
            flock($fh, LOCK_UN);
        }

        fclose($fh);
    }

    private function read(): array
    {
        if (!file_exists($this->filepath)) return [];

        $content = file_get_contents($this->filepath);
        $data    = json_decode($content, true);

        return is_array($data) ? $data : [];
    }
}
