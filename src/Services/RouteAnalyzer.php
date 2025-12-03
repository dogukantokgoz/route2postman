<?php

namespace dogukantokgoz\Route2Postman\Services;

use Closure;
use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionUnionType;
use dogukantokgoz\Route2Postman\Contracts\RouteAnalyzerInterface;
use dogukantokgoz\Route2Postman\DataTransferObjects\RouteInfoDto;
use dogukantokgoz\Route2Postman\Exceptions\RouteProcessingException;
use dogukantokgoz\Route2Postman\Exceptions\UnsupportedRouteException;

class RouteAnalyzer implements RouteAnalyzerInterface
{
    public function __construct(
        protected Router $router,
        protected array $config
    ) {
    }

    public function analyzeRoutes(): array
    {
        $routes = [];

        foreach ($this->filterApplicableRoutes() as $route) {
            try {
                $routes[] = $this->extractRouteInformation($route);
            } catch (UnsupportedRouteException $e) {
                // Skip routes with missing handlers or controllers
                continue;
            }
        }

        return $routes;
    }

    protected function filterApplicableRoutes(): array
    {
        return array_filter($this->router->getRoutes()->getRoutes(), function (Route $route) {
            if ($this->matchesFilterCriteria($route)) {
                return str_starts_with($route->uri(), $this->config['routes']['prefix']);
            }
            return false;
        });
    }

    protected function extractRouteInformation(Route $route): RouteInfoDto
    {
        $controller = $route->getControllerClass();
        
        // Skip routes without controller (unless it's a Closure)
        if ($controller === null && !($route->getAction('uses') instanceof Closure)) {
            throw UnsupportedRouteException::forMissingHandler(
                uri: $route->uri(),
                controller: null,
                method: $route->getActionMethod()
            );
        }

        try {
            $reflector = $this->createRouteReflection($route);

            $formRequestType = collect($reflector->getParameters())
                ->first(fn($parameter) => $this->isFormRequest($parameter))
                    ?->getType()
                    ?->getName();

            $formRequest = $formRequestType ? new $formRequestType() : null;

            // Get only route-specific middleware (not global or group middleware)
            // getAction('middleware') returns only middleware directly assigned to the route
            $routeMiddleware = $route->getAction('middleware') ?? [];
            
            // Convert to array if it's not already an array
            if (!is_array($routeMiddleware)) {
                $routeMiddleware = $routeMiddleware ? [$routeMiddleware] : [];
            }
            
            return new RouteInfoDto(
                uri: $route->uri(),
                methods: $route->methods(),
                controller: $route->getControllerClass(),
                action: $route->getActionMethod(),
                formRequest: $formRequest,
                middleware: $routeMiddleware,
                isProtected: $this->requiresAuthentication($route)
            );
        } catch (UnsupportedRouteException $e) {
            throw $e;
        } catch (Exception $e) {
            throw RouteProcessingException::forReflectionFailure(
                route: $route,
                previous: $e,
                failureType: 'reflection_failure'
            );
        }
    }

    protected function createRouteReflection(Route $route): ReflectionFunctionAbstract
    {
        $controller = $route->getControllerClass();
        $method = $route->getActionMethod();

        if ($route->getAction('uses') instanceof Closure) {
            return new ReflectionFunction($route->getAction('uses'));
        }

        if (!class_exists($controller)) {
            throw UnsupportedRouteException::forMissingController(
                uri: $route->uri(),
                controller: $controller
            );
        }

        $targetMethod = $method === $controller ? '__invoke' : $method;
        if (!method_exists($controller, $targetMethod)) {
            throw UnsupportedRouteException::forMissingHandler(
                uri: $route->uri(),
                controller: $controller,
                method: $targetMethod
            );
        }

        return new ReflectionMethod($controller, $targetMethod);
    }

    protected function matchesFilterCriteria(Route $route): bool
    {
        $prefix = $this->config['routes']['prefix'] ?? 'api';
        if ($prefix && !str_starts_with($route->uri(), $prefix)) {
            return false;
        }

        if (!empty($this->config['routes']['include']['patterns'])) {
            $matched = false;
            foreach ($this->config['routes']['include']['patterns'] as $pattern) {
                if (Str::is($pattern, $route->uri())) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched)
                return false;
        }

        foreach ($this->config['routes']['exclude']['patterns'] as $pattern) {
            if (Str::is($pattern, $route->uri())) {
                return false;
            }
        }

        if (!empty($this->config['routes']['include']['middleware'])) {
            $routeMiddleware = $route->gatherMiddleware();
            if (empty(array_intersect($this->config['routes']['include']['middleware'], $routeMiddleware))) {
                return false;
            }
        }

        foreach ($this->config['routes']['exclude']['middleware'] as $middleware) {
            if (in_array($middleware, $route->gatherMiddleware())) {
                return false;
            }
        }

        $controller = $route->getControllerClass();
        if ($controller && in_array($controller, $this->config['routes']['exclude']['controllers'])) {
            return false;
        }

        return true;
    }

    protected function requiresAuthentication(Route $route): bool
    {
        $authMiddleware = $this->config['auth']['protected_middleware'] ?? ['auth'];

        return !empty(array_intersect(
            $authMiddleware,
            $route->gatherMiddleware()
        ));
    }

    private function isFormRequest(ReflectionParameter $p): bool
    {
        $type = $p->getType();
        if (!$type)
            return false;

        $types = $type instanceof ReflectionUnionType
            ? $type->getTypes()
            : [$type];

        foreach ($types as $t) {
            if (!$t->isBuiltin() && is_subclass_of($t->getName(), FormRequest::class)) {
                return true;
            }
        }

        return false;
    }
}
