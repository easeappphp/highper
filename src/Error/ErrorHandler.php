<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Error;

use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;

class ErrorHandler
{
    /**
     * @var ContainerInterface The container
     */
    protected ContainerInterface $container;
    
    /**
     * @var ConfigProvider The config provider
     */
    protected ConfigProvider $config;
    
    /**
     * @var LoggerInterface|null The logger
     */
    protected ?LoggerInterface $logger = null;
    
    /**
     * @var Whoops|null The Whoops instance
     */
    protected ?Whoops $whoops = null;

    /**
     * Create a new error handler
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get(ConfigProvider::class);
        
        if ($container->has(LoggerInterface::class)) {
            $this->logger = $container->get(LoggerInterface::class);
        }
    }

    /**
     * Register the error handler
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerWhoops();
        $this->registerErrorHandler();
        $this->registerExceptionHandler();
        $this->registerShutdownFunction();
    }

    /**
     * Register Whoops
     *
     * @return void
     */
    protected function registerWhoops(): void
    {
        $this->whoops = new Whoops();
        
        $environment = $this->config->get('app.environment', 'production');
        
        if ($environment === 'development') {
            // Use the pretty page handler in development
            $handler = new PrettyPageHandler();
            
            // Add some application paths to the handler
            $handler->setApplicationPaths([
                $this->config->get('app.base_path', ''),
            ]);
            
            $this->whoops->pushHandler($handler);
        } else {
            // Use the JSON response handler in production
            $handler = new JsonResponseHandler();
            
            // Only show the error message in production
            $handler->addTraceToOutput(false);
            
            $this->whoops->pushHandler($handler);
        }
        
        $this->whoops->register();
    }

    /**
     * Register the error handler
     *
     * @return void
     */
    protected function registerErrorHandler(): void
    {
        set_error_handler(function (int $level, string $message, string $file = '', int $line = 0) {
            if (error_reporting() & $level) {
                throw new \ErrorException($message, 0, $level, $file, $line);
            }
        });
    }

    /**
     * Register the exception handler
     *
     * @return void
     */
    protected function registerExceptionHandler(): void
    {
        set_exception_handler(function (\Throwable $e) {
            $this->handleException($e);
        });
    }

    /**
     * Register the shutdown function
     *
     * @return void
     */
    protected function registerShutdownFunction(): void
    {
        register_shutdown_function(function () {
            $error = error_get_last();
            
            if ($error !== null && $this->isFatalError($error['type'])) {
                $this->handleException(new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                ));
            }
        });
    }

    /**
     * Handle an exception
     *
     * @param \Throwable $e
     * @return void
     */
    public function handleException(\Throwable $e): void
    {
        // Log the exception
        if ($this->logger) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);
        }
        
        // Let Whoops handle the exception
        if ($this->whoops) {
            $this->whoops->handleException($e);
        }
    }

    /**
     * Determine if the error type is fatal
     *
     * @param int $type
     * @return bool
     */
    protected function isFatalError(int $type): bool
    {
        return in_array($type, [
            E_ERROR,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_PARSE,
        ]);
    }
}
