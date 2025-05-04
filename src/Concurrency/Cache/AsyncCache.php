<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Cache;

use Amp\Future;
use EaseAppPHP\HighPer\Framework\Concurrency\Pool\WorkerPool;
use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use Psr\Log\LoggerInterface;

class AsyncCache
{
    /**
     * @var WorkerPool The worker pool
     */
    protected WorkerPool $workerPool;
    
    /**
     * @var LoggerInterface The logger
     */
    protected LoggerInterface $logger;
    
    /**
     * @var ConfigProvider The config provider
     */
    protected ConfigProvider $config;
    
    /**
     * @var string The cache driver
     */
    protected string $driver;
    
    /**
     * @var array The cache configuration
     */
    protected array $cacheConfig;

    /**
     * Create a new async cache
     *
     * @param WorkerPool $workerPool
     * @param LoggerInterface $logger
     * @param ConfigProvider $config
     */
    public function __construct(
        WorkerPool $workerPool,
        LoggerInterface $logger,
        ConfigProvider $config
    ) {
        $this->workerPool = $workerPool;
        $this->logger = $logger;
        $this->config = $config;
        
        $this->loadConfig();
    }

    /**
     * Load the cache configuration
     *
     * @return void
     */
    protected function loadConfig(): void
    {
        $this->driver = $this->config->get('cache.driver', 'memory');
        $this->cacheConfig = $this->config->get("cache.{$this->driver}", []);
    }

    /**
     * Get a value from the cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->logger->debug('Getting value from cache', [
            'key' => $key,
            'driver' => $this->driver,
        ]);
        
        // Get the cache driver instance
        $driver = $this->getDriver();
        
        // Check if the key exists
        if (!$driver->has($key)) {
            return $default;
        }
        
        // Get the value
        return $driver->get($key);
    }

    /**
     * Get a value from the cache asynchronously
     *
     * @param string $key
     * @param mixed $default
     * @return Future<mixed>
     */
    public function getAsync(string $key, mixed $default = null): Future
    {
        return $this->workerPool->submit(new class($this, $key, $default) implements \Amp\Parallel\Worker\Task {
            public function __construct(
                private AsyncCache $cache,
                private string $key,
                private mixed $default
            ) {}
            
            public function run(): mixed
            {
                return $this->cache->get($this->key, $this->default);
            }
        });
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
        $this->logger->debug('Setting value in cache', [
            'key' => $key,
            'driver' => $this->driver,
            'ttl' => $ttl,
        ]);
        
        // Get the cache driver instance
        $driver = $this->getDriver();
        
        // Set the value
        return $driver->set($key, $value, $ttl);
    }

    /**
     * Set a value in the cache asynchronously
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return Future<bool>
     */
    public function setAsync(string $key, mixed $value, ?int $ttl = null): Future
    {
        return $this->workerPool->submit(new class($this, $key, $value, $ttl) implements \Amp\Parallel\Worker\Task {
            public function __construct(
                private AsyncCache $cache,
                private string $key,
                private mixed $value,
                private ?int $ttl
            ) {}
            
            public function run(): bool
            {
                return $this->cache->set($this->key, $this->value, $this->ttl);
            }
        });
    }

    /**
     * Delete a value from the cache
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $this->logger->debug('Deleting value from cache', [
            'key' => $key,
            'driver' => $this->driver,
        ]);
        
        // Get the cache driver instance
        $driver = $this->getDriver();
        
        // Delete the value
        return $driver->delete($key);
    }

    /**
     * Delete a value from the cache asynchronously
     *
     * @param string $key
     * @return Future<bool>
     */
    public function deleteAsync(string $key): Future
    {
        return $this->workerPool->submit(new class($this, $key) implements \Amp\Parallel\Worker\Task {
            public function __construct(
                private AsyncCache $cache,
                private string $key
            ) {}
            
            public function run(): bool
            {
                return $this->cache->delete($this->key);
            }
        });
    }

    /**
     * Clear the cache
     *
     * @return bool
     */
    public function clear(): bool
    {
        $this->logger->debug('Clearing cache', [
            'driver' => $this->driver,
        ]);
        
        // Get the cache driver instance
        $driver = $this->getDriver();
        
        // Clear the cache
        return $driver->clear();
    }

    /**
     * Clear the cache asynchronously
     *
     * @return Future<bool>
     */
    public function clearAsync(): Future
    {
        return $this->workerPool->submit(new class($this) implements \Amp\Parallel\Worker\Task {
            public function __construct(
                private AsyncCache $cache
            ) {}
            
            public function run(): bool
            {
                return $this->cache->clear();
            }
        });
    }

    /**
     * Check if a key exists in the cache
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        // Get the cache driver instance
        $driver = $this->getDriver();
        
        // Check if the key exists
        return $driver->has($key);
    }

    /**
     * Check if a key exists in the cache asynchronously
     *
     * @param string $key
     * @return Future<bool>
     */
    public function hasAsync(string $key): Future
    {
        return $this->workerPool->submit(new class($this, $key) implements \Amp\Parallel\Worker\Task {
            public function __construct(
                private AsyncCache $cache,
                private string $key
            ) {}
            
            public function run(): bool
            {
                return $this->cache->has($this->key);
            }
        });
    }

    /**
     * Get the cache driver instance
     *
     * @return CacheDriverInterface
     */
    protected function getDriver(): CacheDriverInterface
    {
        // Create the driver instance based on the configuration
        return match ($this->driver) {
            'redis' => new RedisDriver($this->cacheConfig),
            'memcached' => new MemcachedDriver($this->cacheConfig),
            'filesystem' => new FilesystemDriver($this->cacheConfig),
            default => new MemoryDriver(),
        };
    }
}