<?php

namespace dogukantokgoz\Route2Postman\Collections;

class Builder
{
    public function __construct(
        protected RouteGrouper $routeGrouper,
        protected AuthenticationBuilder $authBuilder,
        protected array $config,
    ) {
    }

    public function buildCollection(array $routes): array
    {
        $variables = [
            ['key' => 'base_url', 'value' => $this->config['base_url']]
        ];

        $collection = [
            'info' => $this->buildCollectionInfo(),
            'item' => $this->routeGrouper->organizeRoutes($routes),
            'variable' => $variables
        ];

        if ($this->config['auth']['enabled'] ?? false) {
            $collection['auth'] = $this->authBuilder->buildCollectionAuth($this->config['auth']);
            $collection['variable'] = array_merge(
                $variables,
                $this->authBuilder->buildAuthVariables($this->config['auth'])
            );
        }

        return $collection;
    }

    protected function buildCollectionInfo(): array
    {
        return [
            'name' => $this->config['name'],
            'description' => $this->config['description'],
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
        ];
    }
}
