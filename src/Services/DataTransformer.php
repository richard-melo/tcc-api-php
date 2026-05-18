<?php

declare(strict_types=1);

namespace App\Services;

class DataTransformer
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function keysToSnakeCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $snake = strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($key)));
            $result[$snake] = $value;
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function keysToCamelCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $camel = lcfirst(str_replace('_', '', ucwords($key, '_')));
            $result[$camel] = $value;
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function flatten(array $data, string $prefix = '', string $separator = '.'): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix !== '' ? $prefix . $separator . $key : $key;
            if (is_array($value)) {
                $result += $this->flatten($value, $fullKey, $separator);
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function pick(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function omit(array $data, array $keys): array
    {
        return array_diff_key($data, array_flip($keys));
    }

    /**
     * @param array<string, mixed> $data
     * @param callable(mixed): mixed $fn
     * @return array<string, mixed>
     */
    public function mapValues(array $data, callable $fn): array
    {
        return array_map($fn, $data);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, list<array<string, mixed>>>
     */
    public function groupBy(array $items, string $key): array
    {
        $result = [];
        foreach ($items as $item) {
            $group = (string) ($item[$key] ?? '');
            $result[$group][] = $item;
        }
        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function unique(array $items, string $key): array
    {
        $seen   = [];
        $result = [];
        foreach ($items as $item) {
            $val = (string) ($item[$key] ?? '');
            if (!isset($seen[$val])) {
                $seen[$val] = true;
                $result[]   = $item;
            }
        }
        return $result;
    }
}
