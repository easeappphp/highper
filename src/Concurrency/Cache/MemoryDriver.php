<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Cache;

class MemoryDriver implements CacheDriverInterface
{
    /**
     * @var array The cache items
     */
    protected static array $items = [];
    
    /**
     * @var array The cache expiration times
     */
    protected static array $expirations = [];

    /**
     * Get a value from the cache
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        $this->removeExpired();
        
        return self::$items[$key] ?? null;
    }

    /**
     * Set a value in the cache
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        self::$items[$key] = $value;
        
        if ($ttl !== null) {
            self::$expirations[$key] = time() + $ttl;
        } else {
            unset(self::$expirations[$key]);
        }
        
        return true;
    }

    /**
     * Delete a value from the cache
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        unset(self::$items[$key], self::$expirations[$key]);
        
        return true;
    }

    /**
     * Clear the cache
     *
     * @return bool
     */
    public function clear(): bool
    {
        self::$items = [];
        self::$expirations = [];
        
        return true;
    }

    /**
     * Check if a key exists in the cache
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->removeExpired();
        
        return isset(self::$items[$key]);
    }

    /**
     * Remove expired items from the cache
     *
     * @return void
     */
    protected function removeExpired(): void
    {
        $now = time();
        
        foreach (self::$expirations as $key => $expiration) {
            if ($expiration <= $now) {
                unset(self::$items[$key], self::$expirations[$key]);
            }
        }
    }
}
