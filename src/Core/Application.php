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
use EaseAppPHP\HighPer\Framework\Core\EAIsConsole;
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
     * @var EAIsConsole The IsConsole instance
     */
	protected $eaIsConsoleinstance;
    protected $eaRequestConsoleStatusResult;
	
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
        // Load Environment Variables
		$this->loadEnvironmentVariables();
		
		// Initialize the container first
        $this->initializeContainer();
		
		// Register base bindings
		$this->registerBaseBindings();
		
		// Register Base Service Providers
        $this->registerBaseServiceProviders();
		
		//Check if the request is based upon Console or Web
		$eaIsConsole = new EAIsConsole();
		$this->container->instance('EAIsConsole', $eaIsConsole);
		
		//Save EA REQUEST Console Status Result to Container
		$this->container->instance('EARequestConsoleStatusResult', $this->container->get('EAIsConsole')->checkSTDIN());
		$this->eaRequestConsoleStatusResult = $this->container->get('EARequestConsoleStatusResult'); 
        
        // Register the logger before error handling so it can be used for error logging
        $this->registerLogger();
        
        // Then initialize error handling
        $this->initializeErrorHandling();
        
        // Initialize Event Loop      
        $this->initializeEventLoop();
		
		//Initialize Router
        $this->initializeRouter();
		
		//Initialize Middleware
        $this->initializeMiddleware();
		
		//Initialize Logger
        //$this->initializeLogger();
		
		//Initialize Tracing
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
     * Register the logger in the container
     *
     * @return void
     */
    protected function registerLogger(): void
    {
        $this->container->singleton(LoggerInterface::class, function () {
            return LoggerFactory::createLogger('highper');
        });
    }

    /**
     * Initialize error handling
     */
    /*protected function initializeErrorHandling(): void
    {
        $errorHandler = new ErrorHandler($this->container);
        $errorHandler->register();
        $this->container->instance(ErrorHandler::class, $errorHandler);
    }*/
	
	/**
     * Initialize error handling
     *
     * @return void
     */
    protected function initializeErrorHandling(): void
    {
        $errorHandler = new ErrorHandler();
        $errorHandler->register($this->isDebugMode());
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
    /*protected function initializeLogger(): void
    {
        $logger = (new LoggerFactory($this->container))->create();
        $this->logger = $logger;
        $this->container->instance(LoggerInterface::class, $logger);
    }*/

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
        if ($this->container->get('EARequestConsoleStatusResult') == "Console") {
		//$host = $host ?? $config->get('app.host', '127.0.0.1');
        //$port = $port ?? $config->get('app.port', 8080);
		$host = $host ?? $config->get('app.host', '0.0.0.0');
        $port = $port ?? $config->get('app.port', 5000);

        $server = $this->container->make(Server::class);
        $server->start($host, $port);
        
        // Run the event loop
        EventLoop::run();
		}
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
	
	/**
	 * Determine if the application is in debug mode
	 * 
	 * @return bool
	 */
	protected function isDebugMode(): bool
	{
		// Check for APP_DEBUG environment variable, if not set, assume true for development
		$appDebug = getenv('APP_DEBUG');
		
		if ($appDebug !== false) {
			return filter_var($appDebug, FILTER_VALIDATE_BOOLEAN);
		}
		
		// Check for APP_ENV environment variable to determine environment
		$appEnv = getenv('APP_ENV') ?: 'development';
		
		// Consider 'development' and 'local' as debug mode environments
		return in_array(strtolower($appEnv), ['development', 'local']);
	}
}
