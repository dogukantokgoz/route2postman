<?php

namespace dogukantokgoz\Route2Postman\Collections;

use dogukantokgoz\Route2Postman\DataTransferObjects\RouteInfoDto;

class HeaderBuilder
{
    public function buildRequestHeaders(RouteInfoDto $route, array $config, AuthenticationBuilder $authBuilder): array
    {
        $headers = $this->buildDefaultHeaders($config);

        if ($authBuilder->isProtectedRoute($route->middleware, $config) && $this->isApiKeyAuth($config)) {
            $headers[] = $this->buildApiKeyHeader($config['auth']);
        }

        return $headers;
    }

    public function buildDefaultHeaders(array $config): array
    {
        $headers = [];
        foreach ($config['headers'] ?? [] as $key => $value) {
            $headers[] = [
                'key' => $key,
                'value' => $value,
                'type' => 'text'
            ];
        }
        return $headers;
    }

    public function buildApiKeyHeader(array $authConfig): array
    {
        return [
            'key' => $authConfig['default']['key_name'] ?? 'X-API-KEY',
            'value' => '{{api_key}}',
            'type' => 'text'
        ];
    }

    protected function isApiKeyAuth(array $config): bool
    {
        return ($config['auth']['type'] ?? null) === 'api_key';
    }
}
