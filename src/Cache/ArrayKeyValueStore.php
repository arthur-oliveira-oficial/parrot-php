<?php

declare(strict_types=1);

namespace App\Cache;

class ArrayKeyValueStore implements KeyValueStoreInterface
{
    /** @var array<string, mixed> */
    private static array $values = [];

    /** @var array<string, int|null> */
    private static array $expiresAt = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->purgeIfExpired($key);

        return self::$values[$key] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        self::$values[$key] = $value;
        self::$expiresAt[$key] = $ttlSeconds > 0 ? time() + $ttlSeconds : null;

        return true;
    }

    public function delete(string $key): bool
    {
        unset(self::$values[$key], self::$expiresAt[$key]);

        return true;
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        $this->purgeIfExpired($key);

        if (!isset(self::$values[$key])) {
            self::$values[$key] = 1;
            self::$expiresAt[$key] = time() + $ttlSeconds;

            return 1;
        }

        self::$values[$key] = (int) self::$values[$key] + 1;

        return (int) self::$values[$key];
    }

    public function ttl(string $key): ?int
    {
        $this->purgeIfExpired($key);

        if (!array_key_exists($key, self::$expiresAt)) {
            return null;
        }

        $expiresAt = self::$expiresAt[$key];

        if ($expiresAt === null) {
            return null;
        }

        return max(0, $expiresAt - time());
    }

    public function clear(): void
    {
        self::$values = [];
        self::$expiresAt = [];
    }

    private function purgeIfExpired(string $key): void
    {
        if (!array_key_exists($key, self::$expiresAt)) {
            return;
        }

        $expiresAt = self::$expiresAt[$key];

        if ($expiresAt !== null && $expiresAt <= time()) {
            unset(self::$values[$key], self::$expiresAt[$key]);
        }
    }
}
