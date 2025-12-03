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
                return ['name' => $name, 'item' => $items];
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
            function ($items, $name) {
                return ['name' => $name, 'item' => $items];
            },
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

        // Inject login script and disable auth for login routes
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
        } elseif ($this->shouldAddAuthToRequest($route)) {
            // Add auth only to routes with middleware that are not excluded
            $formatted['request']['auth'] = $this->authBuilder->buildRequestAuth($this->config['auth']);
        } else {
            // Add noauth to routes without middleware or excluded routes
            $formatted['request']['auth'] = ['type' => 'noauth'];
        }

        return $formatted;


    }

    protected function isLoginRoute(RouteInfoDto $route): bool
    {
        return $this->isAuthExcludedRoute($route);
    }

    protected function isAuthExcludedRoute(RouteInfoDto $route): bool
    {
        $uri = strtolower($route->uri);
        $apiPrefix = $this->config['routes']['prefix'] ?? 'api';
        
        // Remove api prefix from URI
        $uriWithoutPrefix = str_starts_with($uri, $apiPrefix . '/')
            ? substr($uri, strlen($apiPrefix) + 1)
            : $uri;
        
        // Trim leading and trailing '/' characters
        $uriWithoutPrefix = trim($uriWithoutPrefix, '/');
        
        $excludedRoutes = $this->config['auth']['excluded_routes'] ?? ['login', 'register', 'password-reset', 'password/reset', 'forgot-password', 'forgotpassword', 'reset-password', 'resetpassword'];

        foreach ($excludedRoutes as $excludedRoute) {
            $excludedRouteLower = strtolower(trim($excludedRoute, '/'));

            // Check if excluded route word appears anywhere in the URI (not just exact match)
            // Example: 'login' will match 'api/login', 'api/auth/login', etc.
            if (str_contains($uriWithoutPrefix, $excludedRouteLower)) {
                return true;
            }
        }

        return false;
    }

    protected function shouldAddAuthToFolder(array $routes): bool
    {
        // We no longer add auth at folder level, we add it to each route individually
        // This method is no longer used but kept for backward compatibility
        return false;
    }

    protected function shouldAddAuthToRequest(RouteInfoDto $route): bool
    {
        // First: Check if route is excluded
        if ($this->isAuthExcludedRoute($route)) {
            return false;
        }

        // Check if route has any middleware
        // Middleware array should not be empty
        $middleware = $route->middleware ?? [];
        if (empty($middleware)) {
            return false;
        }

        // Filter out empty strings and null values
        $validMiddleware = array_filter($middleware, function($mw) {
            if ($mw === null || $mw === '') {
                return false;
            }
            $mwString = trim((string)$mw);
            return !empty($mwString);
        });
        
        // Filter out 'api' middleware - this is Laravel's automatically added group middleware
        // Only check route-specific middleware
        $routeSpecificMiddleware = array_filter($validMiddleware, function($mw) {
            return $mw !== 'api';
        });

        // If there are middleware other than 'api', add auth
        return !empty($routeSpecificMiddleware);
    }
}
