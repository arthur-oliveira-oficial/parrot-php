<?php

declare(strict_types=1);

namespace App\Cache;

class ApcuKeyValueStore implements KeyValueStoreInterface
{
    private const META_PREFIX = 'meta:';

    public function get(string $key, mixed $default = null): mixed
    {
        $value = apcu_fetch($key, $success);

        return $success ? $value : $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        $stored = apcu_store($key, $value, $ttlSeconds > 0 ? $ttlSeconds : 0);

        if (!$stored) {
            return false;
        }

        $meta = $ttlSeconds > 0 ? ['expires_at' => time() + $ttlSeconds] : ['expires_at' => null];
        apcu_store(self::META_PREFIX . $key, $meta, $ttlSeconds > 0 ? $ttlSeconds : 0);

        return true;
    }

    public function delete(string $key): bool
    {
        apcu_delete($key);
        apcu_delete(self::META_PREFIX . $key);

        return true;
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        $success = false;
        $currentValue = apcu_inc($key, 1, $success, $ttlSeconds);

        if ($success) {
            return (int) $currentValue;
        }

        apcu_store($key, 1, $ttlSeconds);
        apcu_store(self::META_PREFIX . $key, ['expires_at' => time() + $ttlSeconds], $ttlSeconds);

        return 1;
    }

    public function ttl(string $key): ?int
    {
        $meta = apcu_fetch(self::META_PREFIX . $key, $success);

        if (!$success || !is_array($meta)) {
            return null;
        }

        $expiresAt = $meta['expires_at'] ?? null;

        if (!is_int($expiresAt)) {
            return null;
        }

        return max(0, $expiresAt - time());
    }

    public function clear(): void
    {
        apcu_clear_cache();
    }
}
