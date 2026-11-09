<?php

use Mwyler\LiveVisitors\PlausibleClient;
use Mwyler\LiveVisitors\PresenceStore;
use Mwyler\LiveVisitors\VisitorId;

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
            $store       = new PresenceStore($kirby->root('cache'), $presenceTtl);
            $presence    = $store->getActive();

            // Only the random per-visit id is public. Entries written before
            // 1.3.0 have none and expire within presenceTtl.
            $presenceList = array_values(array_map(
                fn ($s) => ['id' => $s['id']],
                array_filter($presence, fn ($s) => isset($s['id']))
            ));

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
            $kirby = kirby();
            $ua    = $kirby->visitor()->userAgent() ?? '';

            if (preg_match('/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|twitterbot|linkedinbot|whatsapp|telegram|headlesschrome|phantomjs|puppeteer|playwright/i', $ua)) {
                return ['ok' => false];
            }

            // Derive a stable, non-reversible presence id from IP + User-Agent.
            // Raw IP/UA are never stored; the salt rotates daily. This collapses
            // multiple tabs/reloads from the same visitor into a single presence.
            // The request body is ignored (pre-1.3.0 widgets still send a page).
            $ip    = $kirby->visitor()->ip() ?? '';
            $token = (new VisitorId($kirby->root('cache')))->token($ip, $ua);

            $presenceTtl = (int) $kirby->option('matthiaswyler.live-visitors.presenceTtl', 30);
            $store       = new PresenceStore($kirby->root('cache'), $presenceTtl);
            $store->heartbeat($token);

            return ['ok' => true];
        },
    ],
];
