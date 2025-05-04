<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Database;

use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnectionPool;
use Amp\Parallel\Worker\TaskWorkerPool;
use EaseAppPHP\HighPer\Framework\Core\ServiceProviderInterface;

/**
 * DatabaseServiceProvider - Registers database components with the framework
 */
class DatabaseServiceProvider implements ServiceProviderInterface
{
    /**
     * Register database components with the container
     *
     * @param array $config Configuration array
     */
    public function register(array $config): void
    {
        $container = app()->getContainer();
        
        // Register MySQL connection pool
        $container->singleton(MysqlConnectionPool::class, function () use ($config) {
            $mysqlConfig = MysqlConfig::fromString(
                "host={$config['db.host']} " .
                "user={$config['db.username']} " .
                "password={$config['db.password']} " .
                "db={$config['db.database']}"
            );
            
            $maxConnections = $config['db.max_connections'] ?? 10;
            return new MysqlConnectionPool($mysqlConfig, $maxConnections);
        });
        
        // Register worker pool for parallel tasks
        $container->singleton(TaskWorkerPool::class, function () use ($config) {
            $workerCount = $config['concurrency.worker_count'] ?? 4;
            return new TaskWorkerPool($workerCount);
        });
        
        // Register AsyncDatabase
        $container->singleton(AsyncDatabase::class, function ($container) {
            return new AsyncDatabase(
                $container->get(MysqlConnectionPool::class),
                $container->get(TaskWorkerPool::class)
            );
        });
        
        // Register QueryBuilder factory
        $container->bind('db.query', function ($container) {
            return new QueryBuilder($container->get(AsyncDatabase::class));
        });
        
        // Setup Model base class
        Model::setDatabase($container->get(AsyncDatabase::class));
    }
    
    /**
     * Boot the service provider
     */
    public function boot(): void
    {
        // Shutdown worker pool when application terminates
        app()->onShutdown(function () {
            $workerPool = app()->getContainer()->get(TaskWorkerPool::class);
            $workerPool->shutdown();
        });
    }
}