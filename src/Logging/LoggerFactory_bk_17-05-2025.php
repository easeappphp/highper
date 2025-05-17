<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Logging;

use Amp\ByteStream\WritableStream;
use Amp\Log\ConsoleFormatter;
use Amp\Log\Handler\StreamHandler;
use Amp\Log\Logger;
use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class LoggerFactory
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
     * Create a new logger factory
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get(ConfigProvider::class);
    }

    /**
     * Create a new logger instance
     *
     * @return LoggerInterface
     */
    public function create(): LoggerInterface
    {
        $handlers = $this->createHandlers();
        
        return new AsyncLogger(
            $this->config->get('app.name', 'Highper'),
            $handlers
        );
    }

    /**
     * Create the logger handlers
     *
     * @return array
     */
    protected function createHandlers(): array
    {
        $handlers = [];
        
        // Get enabled log channels
        $channels = $this->config->get('logging.channels', ['file']);
        
        foreach ($channels as $channel) {
            $handler = $this->createHandler($channel);
            
            if ($handler) {
                $handlers[] = $handler;
            }
        }
        
        return $handlers;
    }

    /**
     * Create a handler for the given channel
     *
     * @param string $channel
     * @return mixed
     */
    protected function createHandler(string $channel): mixed
    {
        $config = $this->config->get("logging.{$channel}");
        
        if (!$config) {
            return null;
        }
        
        $type = $config['type'] ?? 'stream';
        
        return match ($type) {
            'stream' => $this->createStreamHandler($config),
            'console' => $this->createConsoleHandler($config),
            default => null,
        };
    }

    /**
     * Create a stream handler
     *
     * @param array $config
     * @return StreamHandler
     */
    protected function createStreamHandler(array $config): StreamHandler
    {
        $path = $config['path'] ?? 'php://stdout';
        $level = $config['level'] ?? 'info';
        
        $formatter = $this->createFormatter($config);
        
        $stream = $this->createStream($path);
        
        $handler = new StreamHandler($stream, $formatter);
        $handler->setLevel($level);
        
        return $handler;
    }

    /**
     * Create a console handler
     *
     * @param array $config
     * @return StreamHandler
     */
    protected function createConsoleHandler(array $config): StreamHandler
    {
        $level = $config['level'] ?? 'info';
        
        $formatter = new ConsoleFormatter();
        
        $stream = $this->createStream('php://stdout');
        
        $handler = new StreamHandler($stream, $formatter);
        $handler->setLevel($level);
        
        return $handler;
    }

    /**
     * Create a formatter
     *
     * @param array $config
     * @return mixed
     */
    protected function createFormatter(array $config): mixed
    {
        $formatter = $config['formatter'] ?? 'json';
        
        return match ($formatter) {
            'json' => new JsonFormatter(),
            'line' => new LineFormatter(),
            default => new JsonFormatter(),
        };
    }

    /**
     * Create a stream
     *
     * @param string $path
     * @return WritableStream
     */
    protected function createStream(string $path): WritableStream
    {
        // Use AmPHP file system to create a writable stream
        // This is a placeholder for the actual implementation
        
        return new class($path) implements WritableStream {
            private $resource;
            
            public function __construct(private string $path)
            {
                $this->resource = fopen($path, 'a');
            }
            
            public function write(string $data): void
            {
                fwrite($this->resource, $data);
            }
            
            public function end(): void
            {
                if (is_resource($this->resource)) {
                    fclose($this->resource);
                }
            }
            
            public function isClosed(): bool
            {
                return !is_resource($this->resource);
            }
        };
    }
}
