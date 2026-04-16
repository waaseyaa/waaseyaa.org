<?php

declare(strict_types=1);

return [
    'database' => null,

    // Analytics (Umami). Set UMAMI_TRACKER_URL to empty string to disable locally.
    'analytics' => [
        'tracker_url' => getenv('UMAMI_TRACKER_URL') ?: '',
        'site_id'     => getenv('UMAMI_SITE_ID') ?: '',
    ],

    'ssr' => [
        'theme' => '',
        'cache_max_age' => 300,
    ],
];
