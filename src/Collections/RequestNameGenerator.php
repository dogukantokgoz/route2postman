<?php

namespace dogukantokgoz\Route2Postman\Collections;

use dogukantokgoz\Route2Postman\DataTransferObjects\RouteInfoDto;

class RequestNameGenerator
{
    public function __construct(
        protected array $config
    ) {
    }

    public function generateRequestName(RouteInfoDto $route): string
    {
        $actionName = $route->action;

        return $this->toPascalCase($actionName);
    }

    protected function toPascalCase(string $name): string
    {
        if (empty($name)) {
            return 'Request';
        }

        $parts = preg_split('/(?=[A-Z])|_|-/', $name);
        $parts = array_filter($parts);
        $parts = array_map('ucfirst', $parts);

        return implode('', $parts);
    }
}
