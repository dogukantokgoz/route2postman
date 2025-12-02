<?php

namespace dogukantokgoz\Route2Postman\Collections;

use dogukantokgoz\Route2Postman\DataTransferObjects\RouteInfoDto;

class RouteGrouper
{
    public function __construct(
        protected string $strategy,
        protected array $config,
        protected RequestNameGenerator $nameGenerator,
        protected RequestBodyGenerator $bodyGenerator,
        protected HeaderBuilder $headerBuilder,
        protected AuthenticationBuilder $authBuilder,
        protected UrlBuilder $urlBuilder
    ) {
    }

    public function organizeRoutes(array $routes): array
    {
        return match ($this->strategy) {
            'prefix' => $this->createPrefixBasedStructure($routes),
            'nested_path' => $this->createNestedPathStructure($routes),
            'controller' => $this->createControllerBasedStructure($routes),
            default => $this->createPrefixBasedStructure($routes)
        };
    }

    protected function createPrefixBasedStructure(array $routes): array
    {
        $groups = [];
        $apiPrefix = $this->config['routes']['prefix'] ?? 'api';

        foreach ($routes as $route) {
            $uri = $route->uri;

            // Remove api prefix from URI
            $uriWithoutApiPrefix = str_starts_with($uri, $apiPrefix . '/')
                ? substr($uri, strlen($apiPrefix) + 1)
                : $uri;

            // Get the first segment after api/ as the folder name
            $segments = explode('/', $uriWithoutApiPrefix);
            $prefix = $segments[0] ?? 'other';

            $groups[$prefix][] = $this->buildPostmanRequest($route);
        }

        return array_map(
            function ($items, $name) {
                $folder = ['name' => $name, 'item' => $items];

                if ($this->config['auth']['enabled'] ?? false) {
                    $folder['auth'] = $this->authBuilder->buildCollectionAuth($this->config['auth']);
                }

                return $folder;
            },
            $groups,
            array_keys($groups)
        );
    }

    protected function createNestedPathStructure(array $routes): array
    {
        $maxDepth = $this->config['collection']['max_nesting_depth'] ?? 2;
        $result = [];

        foreach ($routes as $route) {
            $uriWithoutPrefix = trim(str_replace($this->config['routes']['prefix'], '', $route->uri), "/");
            $uriWithoutSegments = trim(preg_replace('/\{.*?\}/', '', $uriWithoutPrefix), "/");
            $segments = explode('/', $uriWithoutSegments);

            $current = &$result;

            foreach ($segments as $i => $segment) {
                if ($i < $maxDepth) {
                    $found = false;
                    foreach ($current as &$item) {
                        if (isset($item['name']) && $item['name'] === $segment) {
                            $current = &$item['item'];
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $newItem = [
                            'name' => $segment,
                            'item' => []
                        ];
                        $current[] = $newItem;
                        $current = &$current[count($current) - 1]['item'];
                    }

                    if ($i === count($segments) - 1) {
                        $current[] = $this->buildPostmanRequest($route);
                    }
                }
            }
        }

        return $result;
    }

    protected function createControllerBasedStructure(array $routes): array
    {
        $groups = [];

        foreach ($routes as $route) {
            $controllerName = $this->getControllerDisplayName($route->controller);
            $groups[$controllerName][] = $this->buildPostmanRequest($route);
        }

        return array_map(
            fn($items, $name) => ['name' => $name, 'item' => $items],
            $groups,
            array_keys($groups)
        );
    }

    protected function getControllerDisplayName(?string $controllerClass): string
    {
        if (!$controllerClass) {
            return 'Undefined';
        }

        $baseName = class_basename($controllerClass);
        return str_replace('Controller', '', $baseName);
    }

    protected function buildPostmanRequest(RouteInfoDto $route): array
    {
        $formatted = [
            'name' => $this->nameGenerator->generateRequestName($route),
            'request' => [
                'method' => $route->methods[0],
                'header' => $this->headerBuilder->buildRequestHeaders($route, $this->config, $this->authBuilder),
                'url' => $this->urlBuilder->buildPostmanUrl($route->uri)
            ]
        ];

        if ($route->formRequest) {
            $formatted['request']['body'] = $this->bodyGenerator->buildBodyFromFormRequest(
                $route->formRequest,
                $this->config,
                $route->methods[0],
            );
        } elseif (in_array($route->methods[0], ['POST', 'PUT', 'PATCH']) && $route->controller) {
            $formatted['request']['body'] = $this->bodyGenerator->buildBodyFromModel(
                $route->controller,
                $this->config,
                $route->methods[0]
            );
        }

        // Inject Login Script and Disable Auth for Login
        if ($this->isLoginRoute($route)) {
            $formatted['request']['auth'] = ['type' => 'noauth'];

            $formatted['event'] = [
                [
                    'listen' => 'test',
                    'script' => [
                        'exec' => [
                            'let response = pm.response.json();',
                            '',
                            'if (response.data.token) {',
                            '    pm.environment.set("token", response.data.token);',
                            '}'
                        ],
                        'type' => 'text/javascript'
                    ]
                ]
            ];
        }

        // Protected routes will inherit Collection Auth (Bearer {{token}}) automatically
        // So we don't need to explicitly set it here unless we want to override it.
        // The Collection Auth is set in Builder.php based on config.

        return $formatted;


    }

    protected function isLoginRoute(RouteInfoDto $route): bool
    {
        $uri = strtolower($route->uri);
        return str_ends_with($uri, 'login') || str_contains($uri, 'auth/login');
    }
}
