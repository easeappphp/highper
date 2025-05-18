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
 * Combines both instance-based and static approaches for flexibility
 */
class ErrorHandler
{
    /**
     * @var Run The Whoops instance for this object
     */
    protected Run $whoops;
    
    /**
     * @var Run|null The static Whoops instance
     */
    protected static ?Run $staticWhoops = null;
    
    /**
     * @var bool Whether debug mode is enabled
     */
    protected static bool $debug = false;
    
    /**
     * ErrorHandler constructor.
     */
    public function __construct()
    {
        $this->whoops = new Run();
    }
    
    /**
     * Register the error handler - instance method
     * 
     * @param bool $isDebug Whether to show detailed errors
     * @param array $options Additional options for the error handler
     * @return void
     */
    public function register(bool $isDebug = true, array $options = []): void
    {
        // Store debug status for static methods
        self::$debug = $isDebug;
        
        // First, check if it's an AJAX request
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $jsonHandler = new JsonResponseHandler();
            $jsonHandler->addTraceToOutput($isDebug);
            
            // Set JSON API format if specified
            if (isset($options['jsonApi']) && $options['jsonApi']) {
                $jsonHandler->setJsonApi(true);
            }
            
            $this->whoops->pushHandler($jsonHandler);
        }
        
        // In debug mode, use PrettyPageHandler
        if ($isDebug) {
            $prettyPageHandler = new PrettyPageHandler();
            
            // Set page title
            $prettyPageHandler->setPageTitle("Highper Framework Error");
            
            // Add Highper-specific information
            $prettyPageHandler->addDataTable('Highper Framework Info', [
                'Version' => $options['version'] ?? 'Highper v1.0',
                'Environment' => $isDebug ? 'Development' : 'Production',
                'PHP Version' => phpversion(),
                'Request Time' => date('Y-m-d H:i:s'),
            ]);
            
            // Set editor if provided
            if (isset($options['editor'])) {
                $prettyPageHandler->setEditor($options['editor']);
            }
            
            $this->whoops->pushHandler($prettyPageHandler);
        } else {
            // In production, use a simpler error handler
            $this->whoops->pushHandler(function ($exception, $inspector, $run) {
                http_response_code(500);
                
                if (class_exists('EaseAppPHP\HighPer\Framework\Exceptions\BaseException') && 
                    $exception instanceof BaseException) {
                    $statusCode = $exception->getStatusCode();
                    http_response_code($statusCode);
                    
                    echo $exception->getUserMessage();
                    
                    if (method_exists($exception, 'shouldLog') && $exception->shouldLog()) {
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
        $this->whoops->register();
    }
    
    /**
     * Add a custom handler
     *
     * @param callable|\Whoops\Handler\HandlerInterface $handler The handler to add
     * @param bool $append Whether to append the handler or prepend it
     * @return void
     */
    public function addHandler($handler, bool $append = true): void
    {
        if ($append) {
            $this->whoops->pushHandler($handler);
        } else {
            $this->whoops->prependHandler($handler);
        }
    }
    
    /**
     * Add data to the pretty page handler
     *
     * @param string $label The label for the data table
     * @param array $data The data to display
     * @return void
     */
    public function addDataTable(string $label, array $data): void
    {
        foreach ($this->whoops->getHandlers() as $handler) {
            if ($handler instanceof PrettyPageHandler) {
                $handler->addDataTable($label, $data);
                break;
            }
        }
    }
    
    /**
     * Get the Whoops instance
     *
     * @return Run The Whoops instance
     */
    public function getWhoops(): Run
    {
        return $this->whoops;
    }
    
    /**
     * Handle an exception manually
     *
     * @param \Throwable $exception The exception to handle
     * @return void
     */
    public function handleException(\Throwable $exception): void
    {
        $this->whoops->handleException($exception);
    }
    
    /**
     * Check if the current request is an AJAX request
     *
     * @return bool
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Initialize the error handler (static method for backward compatibility)
     *
     * @param bool $debug Whether to show detailed error information
     * @param array $options Additional options for the error handler
     * @return void
     */
    public static function initialize(bool $debug = false, array $options = []): void
    {
        self::$debug = $debug;
        
        // Create a new Whoops instance
        self::$staticWhoops = new Run();
        
        // First, check if it's an AJAX request
        if (Misc::isAjaxRequest()) {
            $jsonHandler = new JsonResponseHandler();
            $jsonHandler->addTraceToOutput($debug);
            
            // Set JSON API format if specified
            if (isset($options['jsonApi']) && $options['jsonApi']) {
                $jsonHandler->setJsonApi(true);
            }
            
            self::$staticWhoops->pushHandler($jsonHandler);
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
            
            self::$staticWhoops->pushHandler($prettyPageHandler);
        } else {
            // In production, use a simpler error handler
            self::$staticWhoops->pushHandler(function ($exception, $inspector, $run) {
                http_response_code(500);
                
                if (class_exists('EaseAppPHP\HighPer\Framework\Exceptions\BaseException') && 
                    $exception instanceof BaseException) {
                    $statusCode = $exception->getStatusCode();
                    http_response_code($statusCode);
                    
                    echo $exception->getUserMessage();
                    
                    if (method_exists($exception, 'shouldLog') && $exception->shouldLog()) {
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
        self::$staticWhoops->register();
    }
    
    /**
     * Add a custom handler to static Whoops (for backward compatibility)
     *
     * @param callable|\Whoops\Handler\HandlerInterface $handler The handler to add
     * @param bool $append Whether to append the handler or prepend it
     * @return void
     */
    public static function addStaticHandler($handler, bool $append = true): void
    {
        if (self::$staticWhoops === null) {
            self::initialize(self::$debug);
        }
        
        if ($append) {
            self::$staticWhoops->pushHandler($handler);
        } else {
            self::$staticWhoops->prependHandler($handler);
        }
    }
    
    /**
     * Add data to the static pretty page handler (for backward compatibility)
     *
     * @param string $label The label for the data table
     * @param array $data The data to display
     * @return void
     */
    public static function addStaticDataTable(string $label, array $data): void
    {
        if (self::$staticWhoops === null) {
            self::initialize(self::$debug);
        }
        
        foreach (self::$staticWhoops->getHandlers() as $handler) {
            if ($handler instanceof PrettyPageHandler) {
                $handler->addDataTable($label, $data);
                break;
            }
        }
    }
    
    /**
     * Get the static Whoops instance (for backward compatibility)
     *
     * @return Run|null The Whoops instance
     */
    public static function getStaticWhoops(): ?Run
    {
        return self::$staticWhoops;
    }
    
    /**
     * Handle an exception manually with static instance (for backward compatibility)
     *
     * @param \Throwable $exception The exception to handle
     * @return void
     */
    public static function handleStaticException(\Throwable $exception): void
    {
        if (self::$staticWhoops === null) {
            self::initialize(self::$debug);
        }
        
        self::$staticWhoops->handleException($exception);
    }
}