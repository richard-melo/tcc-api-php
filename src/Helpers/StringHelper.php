<?php

declare(strict_types=1);

namespace App\Helpers;

final class StringHelper
{
    public static function toSnakeCase(string $s): string
    {
        $result = (string) preg_replace('/[A-Z]/', '_$0', lcfirst($s));
        return strtolower($result);
    }

    public static function toCamelCase(string $s): string
    {
        return lcfirst(str_replace('_', '', ucwords($s, '_')));
    }

    public static function truncate(string $s, int $max, string $suffix = '...'): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }
        return mb_substr($s, 0, $max - mb_strlen($suffix)) . $suffix;
    }

    public static function slug(string $s): string
    {
        $lower   = strtolower($s);
        $ascii   = (string) preg_replace('/[^a-z0-9\s-]/', '', $lower);
        $trimmed = trim($ascii, ' -');
        return (string) preg_replace('/[\s-]+/', '-', $trimmed);
    }

    public static function excerpt(string $s, int $words): string
    {
        $parts = explode(' ', $s);
        if (count($parts) <= $words) {
            return $s;
        }
        return implode(' ', array_slice($parts, 0, $words)) . '...';
    }

    public static function wordCount(string $s): int
    {
        $trimmed = trim($s);
        if ($trimmed === '') {
            return 0;
        }
        return count(preg_split('/\s+/', $trimmed) ?: []);
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    public static function startsWith(string $s, string $prefix): bool
    {
        return str_starts_with($s, $prefix);
    }
}
