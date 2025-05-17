<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Core;

use Closure;
use Illuminate\Container\Container;
use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use EaseAppPHP\HighPer\Framework\Config\DotEnvLoader;
use EaseAppPHP\HighPer\Framework\Error\ErrorHandler;
use EaseAppPHP\HighPer\Framework\EventLoop\LoopFactory;
use EaseAppPHP\HighPer\Framework\Http\Middleware\MiddlewareDispatcher;
use EaseAppPHP\HighPer\Framework\Http\Router\Router;
use EaseAppPHP\HighPer\Framework\Http\Server\Server;
use EaseAppPHP\HighPer\Framework\Logging\LoggerFactory;
use EaseAppPHP\HighPer\Framework\Tracing\OpenTelemetryFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;

class Application
{
    /**
     * @var ContainerInterface The service container
     */
    protected ContainerInterface $container;

    /**
     * @var Router The router instance
     */
    protected Router $router;

    /**
     * @var MiddlewareDispatcher The middleware dispatcher
     */
    protected MiddlewareDispatcher $middlewareDispatcher;

    /**
     * @var LoggerInterface The logger instance
     */
    protected LoggerInterface $logger;

    /**
     * @var array The registered service providers
     */
    protected array $serviceProviders = [];

    /**
     * Create a new application instance
     *
     * @param string $basePath The base path of the application
     */
    public function __construct(protected string $basePath)
    {
        $this->bootstrap();
    }

    /**
     * Bootstrap the application
     */
    protected function bootstrap(): void
    {
        $this->loadEnvironmentVariables();
        $this->initializeContainer();
        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->initializeErrorHandling();
        $this->initializeEventLoop();
        $this->initializeRouter();
        $this->initializeMiddleware();
        $this->initializeLogger();
        $this->initializeTracing();
    }

    /**
     * Load environment variables
     */
    protected function loadEnvironmentVariables(): void
    {
        (new DotEnvLoader($this->basePath))->load();
    }

    /**
     * Initialize the service container
     */
    protected function initializeContainer(): void
    {
        $this->container = new Container();
    }

    /**
     * Register base bindings in the container
     */
    protected function registerBaseBindings(): void
    {
        $this->container->instance('app', $this);
        $this->container->instance(Application::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance('base_path', $this->basePath);
        
        // Register config provider
        $configProvider = new ConfigProvider($this->basePath);
        $this->container->instance(ConfigProvider::class, $configProvider);
        $this->container->instance('config', $configProvider);
    }

    /**
     * Register base service providers
     */
    protected function registerBaseServiceProviders(): void
    {
        // This will be implemented in later versions
    }

    /**
     * Initialize error handling
     */
    protected function initializeErrorHandling(): void
    {
        $errorHandler = new ErrorHandler($this->container);
        $errorHandler->register();
        $this->container->instance(ErrorHandler::class, $errorHandler);
    }

    /**
     * Initialize the event loop
     */
    protected function initializeEventLoop(): void
    {
        $loop = LoopFactory::create();
        $this->container->instance('loop', $loop);
        EventLoop::setDriver($loop);
    }

    /**
     * Initialize the router
     */
    protected function initializeRouter(): void
    {
        $this->router = $this->container->make(Router::class);
        $this->container->instance(Router::class, $this->router);
    }

    /**
     * Initialize the middleware dispatcher
     */
    protected function initializeMiddleware(): void
    {
        $this->middlewareDispatcher = $this->container->make(MiddlewareDispatcher::class);
        $this->container->instance(MiddlewareDispatcher::class, $this->middlewareDispatcher);
    }

    /**
     * Initialize the logger
     */
    protected function initializeLogger(): void
    {
        $logger = (new LoggerFactory($this->container))->create();
        $this->logger = $logger;
        $this->container->instance(LoggerInterface::class, $logger);
    }

    /**
     * Initialize tracing
     */
    protected function initializeTracing(): void
    {
        $tracer = (new OpenTelemetryFactory($this->container))->create();
        $this->container->instance('tracer', $tracer);
    }

    /**
     * Get the service container
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the router instance
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get the middleware dispatcher
     *
     * @return MiddlewareDispatcher
     */
    public function getMiddlewareDispatcher(): MiddlewareDispatcher
    {
        return $this->middlewareDispatcher;
    }

    /**
     * Register a service provider
     *
     * @param ServiceProvider|string $provider
     * @return void
     */
    public function register(ServiceProvider|string $provider): void
    {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        $provider->register();
        $this->serviceProviders[] = $provider;
    }

    /**
     * Add a middleware to the global middleware stack
     *
     * @param string|callable $middleware
     * @return self
     */
    public function addMiddleware(string|callable $middleware): self
    {
        $this->middlewareDispatcher->add($middleware);
        return $this;
    }

    /**
     * Start the HTTP server
     *
     * @param string|null $host The server host
     * @param int|null $port The server port
     * @return void
     */
    public function run(?string $host = null, ?int $port = null): void
    {
        $config = $this->container->get('config');
        $host = $host ?? $config->get('app.host', '127.0.0.1');
        $port = $port ?? $config->get('app.port', 8080);

        $server = $this->container->make(Server::class);
        $server->start($host, $port);
        
        // Run the event loop
        EventLoop::run();
    }

    /**
     * Get the base path of the application
     *
     * @param string $path
     * @return string
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}
