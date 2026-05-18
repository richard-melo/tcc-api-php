<?php

declare(strict_types=1);

namespace App\Services;

class CacheManager
{
    /** @var array<string, mixed> */
    private array $store = [];

    /** @var array<string, int> */
    private array $expiry = [];

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->store[$key]  = $value;
        $this->expiry[$key] = time() + $ttl;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }
        return $this->store[$key];
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->store)) {
            return false;
        }
        if (time() > ($this->expiry[$key] ?? 0)) {
            unset($this->store[$key], $this->expiry[$key]);
            return false;
        }
        return true;
    }

    public function delete(string $key): bool
    {
        if (!array_key_exists($key, $this->store)) {
            return false;
        }
        unset($this->store[$key], $this->expiry[$key]);
        return true;
    }

    public function flush(): void
    {
        $this->store  = [];
        $this->expiry = [];
    }

    public function increment(string $key, int $by = 1): int
    {
        $current = $this->has($key) ? (int) $this->store[$key] : 0;
        $new     = $current + $by;
        $this->set($key, $new);
        return $new;
    }

    public function decrement(string $key, int $by = 1): int
    {
        return $this->increment($key, -$by);
    }

    /**
     * @param callable(): mixed $callback
     */
    public function remember(string $key, callable $callback, int $ttl = 3600): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}
