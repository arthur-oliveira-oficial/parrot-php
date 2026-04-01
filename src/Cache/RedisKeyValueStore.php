<?php

declare(strict_types=1);

namespace App\Cache;

use Predis\Client;

class RedisKeyValueStore implements KeyValueStoreInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $prefix = 'parrot:'
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->client->get($this->prefix($key));

        if ($value === null) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        $payload = is_scalar($value) || $value === null
            ? (string) $value
            : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = $ttlSeconds > 0
            ? $this->client->setex($this->prefix($key), $ttlSeconds, $payload)
            : $this->client->set($this->prefix($key), $payload);

        return $result === 'OK';
    }

    public function delete(string $key): bool
    {
        return $this->client->del([$this->prefix($key)]) >= 0;
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        $redisKey = $this->prefix($key);
        $value = (int) $this->client->incr($redisKey);

        if ($value === 1) {
            $this->client->expire($redisKey, $ttlSeconds);
        }

        return $value;
    }

    public function ttl(string $key): ?int
    {
        $ttl = (int) $this->client->ttl($this->prefix($key));

        if ($ttl < 0) {
            return null;
        }

        return $ttl;
    }

    public function clear(): void
    {
    }

    private function prefix(string $key): string
    {
        return $this->prefix . $key;
    }
}
