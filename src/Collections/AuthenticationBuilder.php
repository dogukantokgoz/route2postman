<?php

namespace dogukantokgoz\Route2Postman\Collections;

class AuthenticationBuilder
{
    public function buildCollectionAuth(array $authConfig): array
    {
        switch ($authConfig['type'] ?? 'bearer') {
            case 'bearer':
                return [
                    'type' => 'bearer',
                    'bearer' => [
                        [
                            'key' => 'token',
                            'value' => '{{token}}',
                            'type' => 'string'
                        ]
                    ]
                ];

            case 'basic':
                return [
                    'type' => 'basic',
                    'basic' => [
                        [
                            'key' => 'username',
                            'value' => '{{auth_username}}',
                            'type' => 'string'
                        ],
                        [
                            'key' => 'password',
                            'value' => '{{auth_password}}',
                            'type' => 'string'
                        ]
                    ]
                ];

            case 'api_key':
                return [
                    'type' => 'apikey',
                    'apikey' => [
                        [
                            'key' => 'key',
                            'value' => $authConfig['default']['key_name'] ?? 'X-API-KEY',
                            'type' => 'string'
                        ],
                        [
                            'key' => 'value',
                            'value' => '{{api_key}}',
                            'type' => 'string'
                        ],
                        [
                            'key' => 'in',
                            'value' => $authConfig['location'] ?? 'header',
                            'type' => 'string'
                        ]
                    ]
                ];

            default:
                return [];
        }
    }

    public function buildRequestAuth(array $authConfig): array
    {
        switch ($authConfig['type'] ?? 'bearer') {
            case 'bearer':
                return [
                    'type' => 'bearer',
                    'bearer' => [
                        ['key' => 'token', 'value' => '{{token}}']
                    ]
                ];

            case 'basic':
                return [
                    'type' => 'basic',
                    'basic' => [
                        ['key' => 'username', 'value' => '{{auth_username}}'],
                        ['key' => 'password', 'value' => '{{auth_password}}']
                    ]
                ];

            case 'api_key':
                return [
                    'type' => 'apikey',
                    'apikey' => [
                        ['key' => 'key', 'value' => $authConfig['default']['key_name'] ?? 'X-API-KEY'],
                        ['key' => 'value', 'value' => '{{api_key}}'],
                        ['key' => 'in', 'value' => $authConfig['location'] ?? 'header']
                    ]
                ];

            default:
                return [];
        }
    }

    public function buildAuthVariables(array $authConfig): array
    {
        $variables = [];

        switch ($authConfig['type'] ?? 'bearer') {
            case 'bearer':
                $variables[] = [
                    'key' => 'token',
                    'value' => $authConfig['default']['token'] ?? '',
                    'type' => 'string',
                    'description' => 'Bearer token for API authentication'
                ];
                break;

            case 'basic':
                $variables[] = [
                    'key' => 'auth_username',
                    'value' => $authConfig['default']['username'] ?? '',
                    'type' => 'string',
                    'description' => 'Basic Auth username'
                ];
                $variables[] = [
                    'key' => 'auth_password',
                    'value' => $authConfig['default']['password'] ?? '',
                    'type' => 'string',
                    'description' => 'Basic Auth password'
                ];
                break;

            case 'api_key':
                $variables[] = [
                    'key' => 'api_key',
                    'value' => $authConfig['default']['key_value'] ?? '',
                    'type' => 'string',
                    'description' => 'API Key for authentication'
                ];
                if (isset($authConfig['default']['key_name'])) {
                    $variables[] = [
                        'key' => 'api_key_name',
                        'value' => $authConfig['default']['key_name'],
                        'type' => 'string',
                        'description' => 'API Key header name'
                    ];
                }
                break;
        }

        return $variables;
    }

    public function isProtectedRoute(array $middleware, array $config): bool
    {
        $authMiddleware = $config['auth']['protected_middleware'] ?? ['auth'];
        return !empty(array_intersect($authMiddleware, $middleware));
    }
}
