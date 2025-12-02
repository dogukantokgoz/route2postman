<?php

namespace dogukantokgoz\Route2Postman\Collections;

use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class RequestBodyGenerator
{
    public function buildBodyFromFormRequest(FormRequest $request, array $requestConfig, string $httpMethod): array
    {
        $bodyType = $this->determineBodyFormat($requestConfig['request_body']['default_body_type'], $httpMethod);
        return [
            'mode' => $bodyType,
            $bodyType => $this->createBodyContent($request, $bodyType, $requestConfig),
            'options' => $this->getBodyOptions($bodyType)
        ];
    }

    protected function determineBodyFormat(string $defaultBodyType, string $httpMethod): string
    {
        return $httpMethod === 'POST' && $defaultBodyType === 'formdata' ? 'formdata' : 'raw';
    }

    protected function createBodyContent(FormRequest $request, string $bodyType, array $requestConfig): array|string|null
    {
        try {
            $rules = $request->rules();
        } catch (Throwable $th) {
            $rules = [];
        }

        return match ($bodyType) {
            'raw' => json_encode(
                $this->buildBodyFromRules($rules, $requestConfig),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            'formdata' => $this->generateFormData($rules, $requestConfig),
            default => json_encode(['message' => 'Success'])
        };
    }

    protected function buildBodyFromRules(array $rules, array $requestConfig): array
    {
        $data = [];

        foreach ($rules as $field => $rule) {
            $this->setNestedValue($data, $field, $rule, $requestConfig);
        }

        return $data;
    }

    protected function setNestedValue(&$data, string $field, array|string $rules, array $requestConfig): void
    {
        $rules = is_array($rules) ? $rules : explode('|', $rules);
        $value = $this->createFieldSampleValue($field, $rules, $requestConfig);

        if (str_contains($field, '.*')) {
            $this->setArrayNestedValue($data, $field, $value);
        } else {
            $this->setNestedValueInArray($data, $field, $value);
        }
    }

    protected function createFieldSampleValue(string $field, array|string $rules, array $requestConfig): mixed
    {
        $rules = is_array($rules) ? $rules : explode('|', $rules);
        $defaultValues = data_get($requestConfig, 'request_body.default_values', []);

        if (array_key_exists($field, $defaultValues)) {
            return $defaultValues[$field];
        }

        if (in_array('email', $rules)) {
            return 'user@user.com';
        }

        if (in_array('password', $rules)) {
            return 'password';
        }

        if (in_array('array', $rules)) {
            return [];
        }

        if (in_array('integer', $rules)) {
            $min = 0;
            $max = 10;
            foreach ($rules as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'min:')) {
                    $min = (int) str_replace('min:', '', $rule);
                }
                if (is_string($rule) && str_starts_with($rule, 'max:')) {
                    $max = (int) str_replace('max:', '', $rule);
                }
            }
            return rand($min, $max);
        }

        if (in_array('numeric', $rules)) {
            $min = 1;
            $max = 100;
            foreach ($rules as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'min:')) {
                    $min = (int) str_replace('min:', '', $rule);
                }
                if (is_string($rule) && str_starts_with($rule, 'max:')) {
                    $max = (int) str_replace('max:', '', $rule);
                }
            }
            return rand($min, $max);
        }

        if (in_array('boolean', $rules)) {
            return rand(0, 1);
        }

        if (in_array('date_format', $rules)) {
            foreach ($rules as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'date_format:')) {
                    $format = str_replace('date_format:', '', $rule);
                    return $this->createDateTimeSample($format);
                }
            }
        }

        return 'sample_text';
    }

    protected function createDateTimeSample(string $format): string
    {
        return match ($format) {
            'H:i' => date('H:i'),
            'Y-m-d' => date('Y-m-d'),
            'Y-m-d H:i:s' => date('Y-m-d H:i:s'),
            default => date($format)
        };
    }

    protected function setArrayNestedValue(&$data, string $field, mixed $value): void
    {
        $parts = explode('.*', $field);
        $current = &$data;

        foreach ($parts as $index => $part) {
            if ($index === 0) {
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            } elseif ($index === count($parts) - 1) {
                $cleanPart = ltrim($part, '.');

                if (empty($current)) {
                    $current[] = [];
                }

                if ($cleanPart) {
                    $this->setNestedValueInArray($current[0], $cleanPart, $value);
                }
            } else {
                $cleanPart = ltrim($part, '.');

                if (empty($current)) {
                    $current[] = [];
                }

                if (!isset($current[0][$cleanPart])) {
                    $current[0][$cleanPart] = [];
                }

                $current = &$current[0][$cleanPart];
            }
        }
    }

    protected function setNestedValueInArray(&$target, string $path, mixed $value): void
    {
        $parts = explode('.', $path);

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $target[$part] = $value;
            } else {
                if (!isset($target[$part])) {
                    $target[$part] = [];
                }
                $target = &$target[$part];
            }
        }
    }

    protected function generateFormData(array $rules, array $requestConfig): array
    {
        $data = $this->buildBodyFromRules($rules, $requestConfig);
        return $this->flattenForFormData($data);
    }

    protected function flattenForFormData(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value) && !empty($value)) {
                if ($this->isSequentialArray($value)) {
                    foreach ($value as $index => $item) {
                        $indexedKey = "{$newKey}[{$index}]";
                        if (is_array($item)) {
                            $result = array_merge($result, $this->flattenForFormData($item, $indexedKey));
                        } else {
                            $result[] = [
                                'key' => $indexedKey,
                                'value' => $item,
                                'type' => 'text'
                            ];
                        }
                    }
                } else {
                    $result = array_merge($result, $this->flattenForFormData($value, $newKey));
                }
            } elseif (!is_array($value)) {
                $result[] = [
                    'key' => $newKey,
                    'value' => $value,
                    'type' => 'text'
                ];
            }
        }

        return $result;
    }

    protected function isSequentialArray(array $array): bool
    {
        $keys = array_keys($array);
        return $keys === range(0, count($array) - 1);
    }

    public function buildBodyFromModel(string $controllerClass, array $requestConfig, string $httpMethod): array
    {
        $bodyType = $this->determineBodyFormat($requestConfig['request_body']['default_body_type'] ?? 'raw', $httpMethod);

        $modelClass = $this->getModelFromController($controllerClass);

        if (!$modelClass) {
            return [
                'mode' => $bodyType,
                $bodyType => $bodyType === 'raw' ? "{}" : [],
                'options' => $this->getBodyOptions($bodyType)
            ];
        }

        $fillables = $this->getFillablesFromModel($modelClass);
        $data = array_fill_keys($fillables, "");

        return [
            'mode' => $bodyType,
            $bodyType => $this->createBodyContentFromData($data, $bodyType),
            'options' => $this->getBodyOptions($bodyType)
        ];
    }

    protected function getModelFromController(string $controllerClass): ?string
    {
        if (!$controllerClass)
            return null;

        $baseName = class_basename($controllerClass);
        $modelName = str_replace('Controller', '', $baseName);

        // Check common namespaces
        $namespaces = [
            'App\\Models\\',
            'App\\',
        ];

        foreach ($namespaces as $namespace) {
            $fullClass = $namespace . $modelName;
            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        return null;
    }

    protected function getFillablesFromModel(string $modelClass): array
    {
        try {
            $model = new $modelClass();
            return $model->getFillable();
        } catch (Throwable $e) {
            return [];
        }
    }

    protected function createBodyContentFromData(array $data, string $bodyType): string|array
    {
        return match ($bodyType) {
            'raw' => json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            'formdata' => $this->flattenForFormData($data),
            default => json_encode(['message' => 'Success'])
        };
    }

    protected function getBodyOptions(string $bodyType): array
    {
        return match ($bodyType) {
            'raw' => ['raw' => ['language' => 'json']],
            'formdata' => [],
            default => []
        };
    }
}
