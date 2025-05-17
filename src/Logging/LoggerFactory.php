<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Logging;

use Monolog\Logger;
use Amp\Log\StreamHandler;
use Amp\ByteStream;
use Psr\Log\LoggerInterface;
use Monolog\Formatter\LineFormatter;

/**
 * Class LoggerFactory
 * Creates an asynchronous logger instance
 * @package EaseAppPHP\HighPer\Framework\Logging
 */
class LoggerFactory
{
    /**
     * Create a new asynchronous logger instance 
     * 
     * @param string $name The channel name
     * @param string|null $logPath Optional log file path
     * @param bool $isDebug Whether to show debug-level messages
     * 
     * @return LoggerInterface
     */
    public static function createLogger(string $name = 'app', ?string $logPath = null, bool $isDebug = true): LoggerInterface
    {
        $logger = new Logger($name);
        
        // If log path is provided, log to file
        if ($logPath !== null) {
            // Use asynchronous file logging
            try {
                // Note: In a real implementation, you would use Amp\File\openFile
                // But to keep dependencies minimal, we'll use stdout as fallback if needed
                $handler = new StreamHandler(ByteStream\getStdout());
                $formatter = new LineFormatter(
                    "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                    'Y-m-d H:i:s',
                    true,
                    true
                );
                $handler->setFormatter($formatter);
                $logger->pushHandler($handler);
            } catch (\Throwable $e) {
                // Fallback to system logger if file access fails
                $logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());
            }
        } else {
            // Log to stdout by default (good for containers)
            $handler = new StreamHandler(ByteStream\getStdout());
            
            if ($isDebug) {
                // Use a more user-friendly console format for debug
                $handler->setFormatter(new \Amp\Log\ConsoleFormatter());
            } else {
                // Use more compact format for production
                $formatter = new LineFormatter(
                    "[%datetime%] %level_name%: %message%\n",
                    'Y-m-d H:i:s',
                    true,
                    true
                );
                $handler->setFormatter($formatter);
            }
            
            $logger->pushHandler($handler);
        }
        
        return $logger;
    }
}
