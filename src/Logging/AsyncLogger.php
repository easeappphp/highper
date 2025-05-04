<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Logging;

use Amp\Log\Handler\Handler;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Revolt\EventLoop;

class AsyncLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var string The logger name
     */
    protected string $name;
    
    /**
     * @var array<Handler> The handlers
     */
    protected array $handlers;
    
    /**
     * @var array The context data to include in all log entries
     */
    protected array $contextData = [];

    /**
     * Create a new async logger
     *
     * @param string $name
     * @param array $handlers
     */
    public function __construct(string $name, array $handlers)
    {
        $this->name = $name;
        $this->handlers = $handlers;
    }

    /**
     * Set context data to include in all log entries
     *
     * @param array $data
     * @return self
     */
    public function withContext(array $data): self
    {
        $this->contextData = array_merge($this->contextData, $data);
        
        return $this;
    }

    /**
     * Log a message
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Merge global context with log-specific context
        $context = array_merge($this->contextData, $context);
        
        // Add timestamp
        $context['timestamp'] = date('c');
        
        // Add trace ID if available
        if (isset($context['traceId'])) {
            $context['traceId'] = $context['traceId'];
        }
        
        // Create log record
        $record = [
            'message' => (string) $message,
            'level' => $level,
            'context' => $context,
            'channel' => $this->name,
        ];
        
        // Process log record asynchronously
        $this->processAsync($record);
    }

    /**
     * Process the log record asynchronously
     *
     * @param array $record
     * @return void
     */
    protected function processAsync(array $record): void
    {
        // Schedule the log processing in the event loop
        EventLoop::queue(function () use ($record) {
            foreach ($this->handlers as $handler) {
                // Skip handlers that don't handle this level
                if (!$this->isHandling($handler, $record['level'])) {
                    continue;
                }
                
                try {
                    $handler->handle($record);
                } catch (\Throwable $e) {
                    // Avoid infinite loops by not logging the logger error
                    error_log('Error in log handler: ' . $e->getMessage());
                }
            }
        });
    }

    /**
     * Check if the handler is handling the given level
     *
     * @param Handler $handler
     * @param string $level
     * @return bool
     */
    protected function isHandling(Handler $handler, string $level): bool
    {
        $levels = [
            LogLevel::DEBUG => 100,
            LogLevel::INFO => 200,
            LogLevel::NOTICE => 250,
            LogLevel::WARNING => 300,
            LogLevel::ERROR => 400,
            LogLevel::CRITICAL => 500,
            LogLevel::ALERT => 550,
            LogLevel::EMERGENCY => 600,
        ];
        
        $handlerLevel = property_exists($handler, 'level') ? $handler->level : LogLevel::DEBUG;
        
        return $levels[$level] >= $levels[$handlerLevel];
    }
}
