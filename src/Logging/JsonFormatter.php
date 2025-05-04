<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Logging;

use Amp\Log\Formatter;

class JsonFormatter implements Formatter
{
    /**
     * Format a log record
     *
     * @param array $record
     * @return string
     */
    public function format(array $record): string
    {
        $output = [
            'message' => $record['message'],
            'channel' => $record['channel'],
            'level' => $record['level'],
            'timestamp' => $record['context']['timestamp'] ?? date('c'),
        ];
        
        // Include context data (excluding some internal keys)
        $context = $record['context'] ?? [];
        unset($context['timestamp']);
        
        if (!empty($context)) {
            $output['context'] = $context;
        }
        
        // Include exception details if present
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $e = $context['exception'];
            $output['exception'] = [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }
        
        return json_encode($output) . PHP_EOL;
    }
}
