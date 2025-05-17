<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Error;

use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;

/**
 * Class ErrorHandler
 * Handles errors and exceptions in the application
 * @package EaseAppPHP\HighPer\Framework\Error
 */
class ErrorHandler
{
    /**
     * @var Run
     */
    protected $whoops;

    /**
     * ErrorHandler constructor.
     */
    public function __construct()
    {
        $this->whoops = new Run();
    }

    /**
     * Register the error handler
     * 
     * @param bool $isDebug Whether to show detailed errors
     * @return void
     */
    public function register($isDebug = true)
    {
        if ($isDebug) {
            // For development environment - show pretty error pages
            $handler = new PrettyPageHandler();
            $handler->setPageTitle("Whoops! An error occurred.");
            $this->whoops->pushHandler($handler);
            
            // Add JSON handler for AJAX requests
            if ($this->isAjaxRequest()) {
                $this->whoops->pushHandler(new JsonResponseHandler());
            }
        } else {
            // For production environment - log errors but show friendly message
            $this->whoops->pushHandler(function ($exception, $inspector, $run) {
                // Log the error here if needed
                
                // Return a generic error message
                echo "An error occurred. Please try again later.";
                
                return Run::QUIT_EXECUTION;
            });
        }
        
        // Register with PHP
        $this->whoops->register();
    }
    
    /**
     * Check if the current request is an AJAX request
     *
     * @return bool
     */
    private function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
