<?php

declare(strict_types=1);

namespace App\Cache;

interface KeyValueStoreInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool;

    public function delete(string $key): bool;

    public function increment(string $key, int $ttlSeconds): int;

    public function ttl(string $key): ?int;

    public function clear(): void;
}
