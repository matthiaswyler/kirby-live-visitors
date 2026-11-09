<?php

namespace Mwyler\LiveVisitors;

/**
 * Derives a stable, non-reversible visitor id from IP + User-Agent,
 * salted with a random salt that rotates every day.
 *
 * Raw IP addresses and User-Agent strings are never stored — only the
 * truncated SHA-256 hash is kept as a presence key. Because the salt is
 * random and rotates daily, hashes cannot be reversed or correlated
 * across days.
 */
class VisitorId
{
    private string $filepath;
    private ?string $salt = null;

    public function __construct(string $cacheDir)
    {
        $this->filepath = $cacheDir . '/live-visitors-salt.json';
    }

    public function token(string $ip, string $userAgent): string
    {
        return substr(hash('sha256', $this->dailySalt() . '|' . $ip . '|' . $userAgent), 0, 32);
    }

    /**
     * Salted hash of the IP alone, used to cap presence entries per IP.
     * The token can't serve this purpose because it also mixes in the UA.
     */
    public function ipHash(string $ip): string
    {
        return substr(hash('sha256', $this->dailySalt() . '|ip|' . $ip), 0, 16);
    }

    private function dailySalt(): string
    {
        return $this->salt ??= $this->loadSalt();
    }

    private function loadSalt(): string
    {
        $today = gmdate('Y-m-d');

        if (file_exists($this->filepath)) {
            $data = json_decode((string) file_get_contents($this->filepath), true);
            if (is_array($data) && ($data['date'] ?? null) === $today && !empty($data['salt'])) {
                return $data['salt'];
            }
        }

        $salt = bin2hex(random_bytes(32));

        $dir = dirname($this->filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->filepath,
            json_encode(['date' => $today, 'salt' => $salt], JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        return $salt;
    }
}
