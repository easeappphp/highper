<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Cache;

interface CacheDriverInterface
{
    /**
     * Get a value from the cache
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed;

    /**
     * Set a value in the cache
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Delete a value from the cache
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Clear the cache
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * Check if a key exists in the cache
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;
}
