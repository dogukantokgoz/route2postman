<?php

namespace dogukantokgoz\Route2Postman\Collections;

class UrlBuilder
{
    public function buildPostmanUrl(string $uri, string $baseUrl = '{{base_url}}'): array
    {
        return [
            'raw' => $baseUrl . '/' . $uri,
            'host' => [$baseUrl],
            'path' => $this->parsePathSegments($uri)
        ];
    }

    public function parsePathSegments(string $uri): array
    {
        return explode('/', $uri);
    }
}
