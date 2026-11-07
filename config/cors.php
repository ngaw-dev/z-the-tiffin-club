<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The Tiffin Box frontend will live on a separate origin (e.g. a Vite dev
    | server or the production web app). Allowed origins come from config so
    | they can be set per-environment via FRONTEND_URL / CORS_ALLOWED_ORIGINS
    | without touching this file.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        ...array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => true,

];
