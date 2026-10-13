<?php

// Only the frontend origin configured through the environment may call the API (api-contract §4).
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ORIGIN', 'http://localhost:5173'))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Demo-Now'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
