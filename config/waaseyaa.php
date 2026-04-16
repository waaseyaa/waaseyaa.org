<?php

declare(strict_types=1);

return [
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'log_level' => getenv('LOG_LEVEL') ?: 'warning',
    'environment' => getenv('APP_ENV') ?: 'production',
    'database' => null,

    'config_dir' => getenv('WAASEYAA_CONFIG_DIR') ?: __DIR__ . '/sync',

    'ssr' => [
        'theme' => getenv('WAASEYAA_SSR_THEME') ?: '',
        'cache_max_age' => (int) (getenv('WAASEYAA_SSR_CACHE_MAX_AGE') ?: 300),
    ],

    // Analytics (Umami). No-op when either variable is empty.
    'analytics' => [
        'tracker_url' => getenv('UMAMI_TRACKER_URL') ?: '',
        'site_id'     => getenv('UMAMI_SITE_ID') ?: '',
    ],
];
