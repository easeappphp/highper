<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Core;

use Psr\Log\LoggerInterface;
use EaseAppPHP\HighPer\Framework\Logging\LoggerFactory;

abstract class ServiceProvider
{
    /**
     * Create a new service provider instance
     *
     * @param Application $app
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Register services in the container
     *
     * @return void
     */
    //abstract public function register(): void;
	
	/**
     * Register core services in the container
     * 
     * @param \Illuminate\Container\Container $container
     * @return void
     */
    public function register($container): void
    {
        // Register PSR-3 LoggerInterface
        $container->singleton(LoggerInterface::class, function () {
            return LoggerFactory::createLogger(
                'highper',                 // Logger name
                null,                      // Log to stdout by default
                true                       // Enable debug logging
            );
        });
    }

    /**
     * Boot services
     * 
     * @return void
     */
    public function boot(): void
    {
        // This method can be overridden by child classes
    }

    /**
     * Register a binding in the container
     *
     * @param string $abstract
     * @param callable|string|null $concrete
     * @param bool $shared
     * @return void
     */
    protected function bind(string $abstract, callable|string|null $concrete = null, bool $shared = false): void
    {
        $container = $this->app->getContainer();
        
        $container->bind($abstract, $concrete, $shared);
    }

    /**
     * Register a shared binding in the container
     *
     * @param string $abstract
     * @param callable|string|null $concrete
     * @return void
     */
    protected function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $container = $this->app->getContainer();
        
        $container->singleton($abstract, $concrete);
    }

    /**
     * Get the application instance
     *
     * @return Application
     */
    protected function getApp(): Application
    {
        return $this->app;
    }
}
