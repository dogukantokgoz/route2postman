<?php

return [
    'name' => env('APP_NAME', 'Laravel Routes'),
    'description' => env('API_DESCRIPTION', 'API Documentation'),
    'base_url' => env('APP_URL', 'http://localhost'),

    'routes' => [
        'prefix' => 'api',

        'include' => [
            'patterns' => [],
            'middleware' => [],
            'controllers' => [],
        ],

        'exclude' => [
            'patterns' => [],
            'middleware' => [],
            'controllers' => [],
        ],
    ],

    'collection' => [
        'grouping_strategy' => 'prefix',
        'max_nesting_depth' => 10,
        'folder_names' => [],
        'request_naming' => '{uri}',
    ],

    'request_body' => [
        'default_body_type' => 'raw',
        'default_values' => [],
    ],

    'auth' => [
        'enabled' => true,
        'type' => 'bearer',
        'location' => 'header',

        'default' => [
            'token' => '',
            'username' => 'user@user.com',
            'password' => 'password',
            'key_name' => 'X-API-KEY',
            'key_value' => 'your-api-key-here',
        ],

        'protected_middleware' => ['auth', 'auth:api', 'auth:sanctum'],
    ],

    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],

    'export' => [
        'storage_driver' => env('POSTMAN_STORAGE_DISK', 'local'),
        'output_path' => env('POSTMAN_STORAGE_DIR', storage_path('postman')),
        'file_name' => env('POSTMAN_STORAGE_FILE', 'route_collection'),
    ],
];
