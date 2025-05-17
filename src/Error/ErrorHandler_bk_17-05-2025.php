<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Error;

use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Util\Misc;
use EaseAppPHP\HighPer\Framework\Exceptions\BaseException;
use EaseAppPHP\HighPer\Framework\Exceptions\DatabaseException;

/**
 * Enhanced ErrorHandler that integrates with filp/whoops
 */
class ErrorHandler
{
    /**
     * @var Run|null The Whoops instance
     */
    protected static ?Run $whoops = null;
    
    /**
     * @var bool Whether debug mode is enabled
     */
    protected static bool $debug = false;
    
    /**
     * Initialize the error handler
     *
     * @param bool $debug Whether to show detailed error information
     * @param array $options Additional options for the error handler
     * @return void
     */
    public static function initialize(bool $debug = false, array $options = []): void
    {
        self::$debug = $debug;
        
        // Create a new Whoops instance
        self::$whoops = new Run();
        
        // First, check if it's an AJAX request
        if (Misc::isAjaxRequest()) {
            $jsonHandler = new JsonResponseHandler();
            $jsonHandler->addTraceToOutput($debug);
            
            // Set JSON API format if specified
            if (isset($options['jsonApi']) && $options['jsonApi']) {
                $jsonHandler->setJsonApi(true);
            }
            
            self::$whoops->pushHandler($jsonHandler);
        }
        
        // In debug mode, use PrettyPageHandler
        if ($debug) {
            $prettyPageHandler = new PrettyPageHandler();
            
            // Set page title
            $prettyPageHandler->setPageTitle("Highper Framework Error");
            
            // Add Highper-specific information
            $prettyPageHandler->addDataTable('Highper Framework Info', [
                'Version' => $options['version'] ?? 'Highper v1.0',
                'Environment' => $debug ? 'Development' : 'Production',
                'PHP Version' => phpversion(),
                'Request Time' => date('Y-m-d H:i:s'),
            ]);
            
            // Set editor if provided
            if (isset($options['editor'])) {
                $prettyPageHandler->setEditor($options['editor']);
            }
            
            self::$whoops->pushHandler($prettyPageHandler);
        } else {
            // In production, use a simpler error handler
            self::$whoops->pushHandler(function ($exception, $inspector, $run) {
                http_response_code(500);
                
                if ($exception instanceof BaseException) {
                    $statusCode = $exception->getStatusCode();
                    http_response_code($statusCode);
                    
                    echo $exception->getUserMessage();
                    
                    if ($exception->shouldLog()) {
                        // Log the exception if logging is enabled
                        error_log((string) $exception);
                    }
                } else {
                    echo "An error occurred. Please try again later.";
                    error_log((string) $exception);
                }
                
                return \Whoops\Handler\Handler::QUIT;
            });
        }
        
        // Register the error handler
        self::$whoops->register();
    }
    
    /**
     * Add a custom handler to Whoops
     *
     * @param callable|\Whoops\Handler\HandlerInterface $handler The handler to add
     * @param bool $append Whether to append the handler or prepend it
     * @return void
     */
    public static function addHandler($handler, bool $append = true): void
    {
        if (self::$whoops === null) {
            self::initialize(self::$debug);
        }
        
        if ($append) {
            self::$whoops->pushHandler($handler);
        } else {
            self::$whoops->prependHandler($handler);
        }
    }
    
    /**
     * Add data to the pretty page handler
     *
     * @param string $label The label for the data table
     * @param array $data The data to display
     * @return void
     */
    public static function addDataTable(string $label, array $data): void
    {
        if (self::$whoops === null) {
            self::initialize(self::$debug);
        }
        
        foreach (self::$whoops->getHandlers() as $handler) {
            if ($handler instanceof PrettyPageHandler) {
                $handler->addDataTable($label, $data);
                break;
            }
        }
    }
    
    /**
     * Get the Whoops instance
     *
     * @return Run|null The Whoops instance
     */
    public static function getWhoops(): ?Run
    {
        return self::$whoops;
    }
    
    /**
     * Handle an exception manually
     *
     * @param \Throwable $exception The exception to handle
     * @return void
     */
    public static function handleException(\Throwable $exception): void
    {
        if (self::$whoops === null) {
            self::initialize(self::$debug);
        }
        
        self::$whoops->handleException($exception);
    }
}