<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Logging;

use Amp\Log\Formatter;

class LineFormatter implements Formatter
{
    /**
     * Create a new line formatter
     *
     * @param string $format
     */
    public function __construct(
        protected string $format = "[%datetime%] %channel%.%level%: %message% %context% %exception%\n"
    ) {
    }

    /**
     * Format a log record
     *
     * @param array $record
     * @return string
     */
    public function format(array $record): string
    {
        $output = $this->format;
        
        // Replace placeholders
        $output = str_replace('%datetime%', $record['context']['timestamp'] ?? date('c'), $output);
        $output = str_replace('%channel%', $record['channel'], $output);
        $output = str_replace('%level%', $record['level'], $output);
        $output = str_replace('%message%', $record['message'], $output);
        
        // Format context data
        $context = $record['context'] ?? [];
        unset($context['timestamp']);
        
        if (!empty($context)) {
            if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
                $exception = $context['exception'];
                unset($context['exception']);
            }
            
            $output = str_replace('%context%', json_encode($context), $output);
        } else {
            $output = str_replace('%context%', '', $output);
        }
        
        // Format exception
        if (isset($exception) && $exception instanceof \Throwable) {
            $exceptionOutput = sprintf(
                "{%s: %s in %s:%s}",
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
            
            $output = str_replace('%exception%', $exceptionOutput, $output);
        } else {
            $output = str_replace('%exception%', '', $output);
        }
        
        return $output;
    }
}
