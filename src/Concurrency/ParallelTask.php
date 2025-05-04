<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency;

use Amp\Parallel\Worker\Task;

/**
 * ParallelTask - Wrapper for executing tasks in parallel workers
 */
class ParallelTask implements Task
{
    private $resource;
    private $callable;
    private array $args;
    
    /**
     * Constructor for ParallelTask
     *
     * @param mixed $resource The resource to pass to the worker (connection pool, connection, etc.)
     * @param callable $callable The async callable to execute
     * @param array $args Additional arguments for the callable
     */
    public function __construct($resource, callable $callable, array $args = [])
    {
        $this->resource = $resource;
        $this->callable = $callable;
        $this->args = $args;
    }
    
    /**
     * Execute the task in a worker
     *
     * @return mixed The result of the task
     */
    public async function run(): mixed
    {
        $callable = $this->callable;
        return await $callable($this->resource, ...$this->args);
    }
}