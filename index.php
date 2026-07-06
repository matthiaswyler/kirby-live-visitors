<?php

require_once __DIR__ . '/src/PlausibleClient.php';
require_once __DIR__ . '/src/PresenceStore.php';
require_once __DIR__ . '/src/VisitorId.php';

Kirby::plugin('matthiaswyler/live-visitors', [
    'options' => [
        'apiKey'        => null,
        'siteId'        => null,
        'baseUrl'       => 'https://plausible.io',
        'interval'      => 30,
        'cacheTtl'      => 1,
        'presenceTtl'   => 30,
        'activeTimeout' => 60,
        'dimensions'    => ['visit:country_name', 'visit:city_name'],
    ],
    'api' => [
        'routes' => require __DIR__ . '/src/routes.php',
    ],
    'snippets' => [
        'live-visitors' => __DIR__ . '/snippets/live-visitors.php',
    ],
]);
