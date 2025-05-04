<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Core;

use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use EaseAppPHP\HighPer\Framework\Http\Middleware\Security\CorsMiddleware;
use EaseAppPHP\HighPer\Framework\Http\Middleware\Security\RateLimitingMiddleware;
use EaseAppPHP\HighPer\Framework\Http\Middleware\Security\SecurityMiddleware;

class Bootstrap
{
    /**
     * @var Application The application instance
     */
    protected Application $app;

    /**
     * Create a new bootstrap instance
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Bootstrap the application
     *
     * @return void
     */
    public function bootstrap(): void
    {
        $this->registerServiceProviders();
        $this->registerMiddleware();
        $this->registerRoutes();
    }

    /**
     * Register service providers
     *
     * @return void
     */
    protected function registerServiceProviders(): void
    {
        $config = $this->app->getContainer()->get(ConfigProvider::class);
        $providers = $config->get('app.providers', []);
        
        foreach ($providers as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Register middleware
     *
     * @return void
     */
    protected function registerMiddleware(): void
    {
        $config = $this->app->getContainer()->get(ConfigProvider::class);
        
        // Register global middleware
        $middleware = $config->get('middleware.global', [
            SecurityMiddleware::class,
            CorsMiddleware::class,
            RateLimitingMiddleware::class,
        ]);
        
        foreach ($middleware as $class) {
            $this->app->addMiddleware($class);
        }
    }

    /**
     * Register routes
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $routesPath = $this->app->basePath('routes');
        
        // Load route files
        if (is_dir($routesPath)) {
            $files = glob($routesPath . '/*.php');
            
            foreach ($files as $file) {
                // Each route file should return a closure that accepts the application instance
                $routeRegistrar = require $file;
                
                if (is_callable($routeRegistrar)) {
                    $routeRegistrar($this->app);
                }
            }
        }
    }
}
