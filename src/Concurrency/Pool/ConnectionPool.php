<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Pool;

use Amp\Cancellation;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use Psr\Log\LoggerInterface;

abstract class ConnectionPool
{
    /**
     * @var array The pool configuration
     */
    protected array $config;
    
    /**
     * @var array<object> The connection pool
     */
    protected array $pool = [];
    
    /**
     * @var array<DeferredFuture> The waiting queue
     */
    protected array $waiting = [];
    
    /**
     * @var bool Whether the pool is closed
     */
    protected bool $closed = false;

    /**
     * Create a new connection pool
     *
     * @param ConfigProvider $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected ConfigProvider $config,
        protected LoggerInterface $logger
    ) {
        $this->loadConfig();
    }

    /**
     * Load the pool configuration
     *
     * @return void
     */
    protected function loadConfig(): void
    {
        $poolName = $this->getPoolName();
        
        $this->config = [
            'min_connections' => $this->config->get("pool.{$poolName}.min_connections", 2),
            'max_connections' => $this->config->get("pool.{$poolName}.max_connections", 10),
            'idle_timeout' => $this->config->get("pool.{$poolName}.idle_timeout", 60),
            'wait_timeout' => $this->config->get("pool.{$poolName}.wait_timeout", 15),
            'max_wait_queue' => $this->config->get("pool.{$poolName}.max_wait_queue", 100),
        ];
    }

    /**
     * Get a connection from the pool
     *
     * @param Cancellation|null $cancellation
     * @return Future<object>
     */
    public function get(?Cancellation $cancellation = null): Future
    {
        if ($this->closed) {
            throw new \RuntimeException('Connection pool is closed');
        }
        
        // Try to get an idle connection
        if (count($this->pool) > 0) {
            $connection = array_pop($this->pool);
            return Future::complete($connection);
        }
        
        // Create a new connection if below max
        if (count($this->pool) + count($this->waiting) < $this->config['max_connections']) {
            try {
                $connection = $this->createConnection();
                return Future::complete($connection);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to create connection: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
                return Future::error($e);
            }
        }
        
        // Check if wait queue is full
        if (count($this->waiting) >= $this->config['max_wait_queue']) {
            throw new \RuntimeException('Connection pool wait queue is full');
        }
        
        // Add to wait queue
        $deferred = new DeferredFuture();
        $this->waiting[] = $deferred;
        
        // Create a timeout cancellation
        $timeoutCancellation = new TimeoutCancellation($this->config['wait_timeout']);
        
        // Create a composite cancellation if needed
        if ($cancellation !== null) {
            $compositeCancellation = new DeferredCancellation();
            $id1 = $cancellation->subscribe($compositeCancellation->cancel(...));
            $id2 = $timeoutCancellation->subscribe($compositeCancellation->cancel(...));
            
            $cancellation = $compositeCancellation->getCancellation();
            
            // Clean up the subscriptions when complete
            $deferred->getFuture()->finally(function () use ($cancellation, $id1, $id2) {
                $cancellation->unsubscribe($id1);
                $cancellation->unsubscribe($id2);
            });
        } else {
            $cancellation = $timeoutCancellation;
        }
        
        // Set up cancellation
        $id = $cancellation->subscribe(function () use ($deferred) {
            $this->cancelWaiting($deferred);
            $deferred->error(new \RuntimeException('Timed out waiting for connection from pool'));
        });
        
        // Clean up cancellation when complete
        $deferred->getFuture()->finally(function () use ($cancellation, $id) {
            $cancellation->unsubscribe($id);
        });
        
        return $deferred->getFuture();
    }

    /**
     * Return a connection to the pool
     *
     * @param object $connection
     * @return void
     */
    public function put(object $connection): void
    {
        if ($this->closed) {
            $this->closeConnection($connection);
            return;
        }
        
        // Check if a waiting request exists
        if (count($this->waiting) > 0) {
            $deferred = array_shift($this->waiting);
            $deferred->complete($connection);
            return;
        }
        
        // Add back to the pool
        $this->pool[] = $connection;
        
        // Prune idle connections
        $this->pruneIdleConnections();
    }

    /**
     * Close the connection pool
     *
     * @return void
     */
    public function close(): void
    {
        $this->closed = true;
        
        // Close all connections
        foreach ($this->pool as $connection) {
            $this->closeConnection($connection);
        }
        
        $this->pool = [];
        
        // Complete all waiting requests with an error
        foreach ($this->waiting as $deferred) {
            $deferred->error(new \RuntimeException('Connection pool closed'));
        }
        
        $this->waiting = [];
    }

    /**
     * Cancel a waiting request
     *
     * @param DeferredFuture $deferred
     * @return void
     */
    protected function cancelWaiting(DeferredFuture $deferred): void
    {
        $index = array_search($deferred, $this->waiting, true);
        
        if ($index !== false) {
            array_splice($this->waiting, $index, 1);
        }
    }

    /**
     * Prune idle connections
     *
     * @return void
     */
    protected function pruneIdleConnections(): void
    {
        // Keep at least min_connections
        if (count($this->pool) <= $this->config['min_connections']) {
            return;
        }
        
        // Close excess connections
        $excess = count($this->pool) - $this->config['min_connections'];
        
        for ($i = 0; $i < $excess; $i++) {
            $connection = array_pop($this->pool);
            $this->closeConnection($connection);
        }
    }

    /**
     * Get the pool name
     *
     * @return string
     */
    abstract protected function getPoolName(): string;

    /**
     * Create a new connection
     *
     * @return object
     */
    abstract protected function createConnection(): object;

    /**
     * Close a connection
     *
     * @param object $connection
     * @return void
     */
    abstract protected function closeConnection(object $connection): void;
}
