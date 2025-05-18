<?php

return [
    [
        'host' => env('ES_HOST', 'localhost'),
        'port' => env('ES_PORT', '9200'),
        'scheme' => env('ES_SCHEME', 'http'),
        'user' => env('ES_USER', 'elastic'),
        'pass' => env('ES_PASS', ''),
    ],
];
