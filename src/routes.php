<?php

use Mwyler\LiveVisitors\PlausibleClient;
use Mwyler\LiveVisitors\PresenceStore;

return [
    [
        'pattern' => 'live-visitors',
        'method'  => 'GET',
        'auth'    => false,
        'action'  => function () {
            $kirby  = kirby();
            $apiKey = $kirby->option('matthiaswyler.live-visitors.apiKey');
            $siteId = $kirby->option('matthiaswyler.live-visitors.siteId')
                ?? parse_url($kirby->url(), PHP_URL_HOST);

            $presenceTtl = (int) $kirby->option('matthiaswyler.live-visitors.presenceTtl', 30);
            $store       = new PresenceStore($kirby->root('cache'));
            $presence    = $store->getActive($presenceTtl);

            $presenceList = array_map(function ($s) {
                return [
                    'id'   => substr($s['token'], 0, 8),
                    'page' => $s['page'] ?? '',
                ];
            }, $presence);

            if (!$apiKey || !$siteId) {
                return [
                    'total'    => count($presenceList),
                    'geo'      => [],
                    'presence' => $presenceList,
                ];
            }

            $cache  = $kirby->cache('matthiaswyler.live-visitors');
            $cached = $cache->get('plausible');

            if ($cached === null) {
                $baseUrl    = $kirby->option('matthiaswyler.live-visitors.baseUrl', 'https://plausible.io');
                $dimensions = $kirby->option('matthiaswyler.live-visitors.dimensions', []);
                $client     = new PlausibleClient($apiKey, $siteId, $baseUrl);

                try {
                    $response = $client->realtimeVisitors($dimensions);
                } catch (\Exception $e) {
                    $response = ['results' => []];
                }

                $geo   = [];
                $total = 0;

                if (!empty($response['results'])) {
                    foreach ($response['results'] as $row) {
                        $count  = $row['metrics'][0] ?? 0;
                        $total += $count;

                        $entry = ['count' => $count];

                        foreach ($dimensions as $i => $dim) {
                            $key = match ($dim) {
                                'visit:country_name' => 'country',
                                'visit:city_name'    => 'city',
                                'visit:region_name'  => 'region',
                                'event:page'         => 'page',
                                'visit:device'       => 'device',
                                'visit:browser'      => 'browser',
                                'visit:os'           => 'os',
                                default              => str_replace(['visit:', 'event:'], '', $dim),
                            };

                            $entry[$key] = $row['dimensions'][$i] ?? '';
                        }

                        $geo[] = $entry;
                    }
                }

                $cached = ['geo' => $geo, 'plausibleTotal' => $total];

                $cacheTtl = (int) $kirby->option('matthiaswyler.live-visitors.cacheTtl', 1);
                $cache->set('plausible', $cached, $cacheTtl);
            }

            return [
                'total'    => max($cached['plausibleTotal'], count($presenceList)),
                'geo'      => $cached['geo'],
                'presence' => $presenceList,
            ];
        },
    ],
    [
        'pattern' => 'live-visitors/heartbeat',
        'method'  => 'POST',
        'auth'    => false,
        'action'  => function () {
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (preg_match('/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|twitterbot|linkedinbot|whatsapp|telegram|headlesschrome|phantomjs|puppeteer|playwright/i', $ua)) {
                return ['ok' => false];
            }

            $body = json_decode(file_get_contents('php://input'), true);

            $token = $body['token'] ?? '';
            $page  = $body['page']  ?? '';

            if (!is_string($token) || strlen($token) < 8 || strlen($token) > 64) {
                return ['ok' => false];
            }

            $page  = is_string($page) ? substr($page, 0, 255) : '';
            $store = new PresenceStore(kirby()->root('cache'));

            $store->heartbeat($token, ['page' => $page]);

            return ['ok' => true];
        },
    ],
];
