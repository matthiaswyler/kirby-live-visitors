<?php

namespace Mwyler\LiveVisitors;

use Exception;

class PlausibleClient
{
    private string $apiKey;
    private string $siteId;
    private string $baseUrl;

    public function __construct(string $apiKey, string $siteId, string $baseUrl = 'https://plausible.io')
    {
        $this->apiKey  = $apiKey;
        $this->siteId  = $siteId;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function realtimeVisitors(array $dimensions = []): array
    {
        $now      = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $fiveAgo  = $now->modify('-5 minutes');

        return $this->query([
            'site_id'    => $this->siteId,
            'metrics'    => ['visitors'],
            'date_range' => [$fiveAgo->format('c'), $now->format('c')],
            'dimensions' => $dimensions,
        ]);
    }

    private function query(array $payload): array
    {
        $ch = curl_init($this->baseUrl . '/api/v2/query');

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception('Plausible API request failed: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('Plausible API returned HTTP ' . $httpCode . ': ' . $response);
        }

        return json_decode($response, true) ?? [];
    }
}
