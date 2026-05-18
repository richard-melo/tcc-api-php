<?php

declare(strict_types=1);

namespace App\Helpers;

final class ArrayHelper
{
    /**
     * @param array<mixed> $arr
     * @return array<mixed>
     */
    public static function flatten(array $arr, int $depth = PHP_INT_MAX): array
    {
        $result = [];
        foreach ($arr as $item) {
            if (is_array($item) && $depth > 0) {
                foreach (self::flatten($item, $depth - 1) as $v) {
                    $result[] = $v;
                }
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * @param array<mixed> $arr
     * @return array<mixed>
     */
    public static function unique(array $arr): array
    {
        return array_values(array_unique($arr));
    }

    /**
     * @param array<mixed> $arr
     * @return array<int, array<mixed>>
     */
    public static function chunk(array $arr, int $size): array
    {
        return array_chunk($arr, max(1, $size));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return list<mixed>
     */
    public static function pluck(array $items, string $key): array
    {
        return array_values(array_map(
            static fn(array $item): mixed => $item[$key] ?? null,
            $items
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    public static function indexBy(array $items, string $key): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[(string) ($item[$key] ?? '')] = $item;
        }
        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public static function sum(array $items, string $key): float
    {
        return (float) array_sum(array_map(
            static fn(array $item): float => (float) ($item[$key] ?? 0),
            $items
        ));
    }

    /** @param array<mixed> $arr */
    public static function first(array $arr): mixed
    {
        return $arr[array_key_first($arr) ?? 0] ?? null;
    }

    /** @param array<mixed> $arr */
    public static function last(array $arr): mixed
    {
        return $arr[array_key_last($arr) ?? 0] ?? null;
    }
}
