<?php

declare(strict_types=1);

return [
    'paths' => [
        'api/*',
        'docs/*',
        'documentation/*',
        'swagger/*',
        'api/documentation',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Authorization',
        'Accept',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-APP-KEY',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
