<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Pool;

use Amp\Future;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use Psr\Log\LoggerInterface;

class WorkerPool
{
    /**
     * @var Pool The Amp worker pool
     */
    protected Pool $pool;
    
    /**
     * @var array The pool configuration
     */
    protected array $config;

    /**
     * Create a new worker pool
     *
     * @param ConfigProvider $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected ConfigProvider $config,
        protected LoggerInterface $logger
    ) {
        $this->loadConfig();
        $this->initialize();
    }

    /**
     * Load the pool configuration
     *
     * @return void
     */
    protected function loadConfig(): void
    {
        $this->config = [
            'min_workers' => $this->config->get('worker_pool.min_workers', 2),
            'max_workers' => $this->config->get('worker_pool.max_workers', 8),
        ];
    }

    /**
     * Initialize the worker pool
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->pool = new DefaultPool($this->config['max_workers']);
        
        $this->logger->debug('Worker pool initialized', [
            'min_workers' => $this->config['min_workers'],
            'max_workers' => $this->config['max_workers'],
        ]);
    }

    /**
     * Submit a task to the worker pool
     *
     * @param Task $task
     * @return Future
     */
    public function submit(Task $task): Future
    {
        $this->logger->debug('Submitting task to worker pool', [
            'task' => get_class($task),
        ]);
        
        return $this->pool->submit($task);
    }

    /**
     * Get a worker from the pool
     *
     * @return Worker
     */
    public function getWorker(): Worker
    {
        return $this->pool->getWorker();
    }

    /**
     * Get the worker pool
     *
     * @return Pool
     */
    public function getPool(): Pool
    {
        return $this->pool;
    }

    /**
     * Shutdown the worker pool
     *
     * @return Future
     */
    public function shutdown(): Future
    {
        $this->logger->debug('Shutting down worker pool');
        
        return $this->pool->shutdown();
    }
}
