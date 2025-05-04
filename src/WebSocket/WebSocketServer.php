<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\WebSocket;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Socket\BindContext;
use Amp\Socket\ServerSocket;
use Amp\Websocket\Server\Gateway;
use Amp\Websocket\Server\WebsocketServer;
use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;

class WebSocketServer
{
    /**
     * @var WebsocketServer The AmPHP WebSocket server
     */
    protected WebsocketServer $server;
    
    /**
     * @var Gateway The WebSocket gateway
     */
    protected Gateway $gateway;
    
    /**
     * @var ContainerInterface The container
     */
    protected ContainerInterface $container;
    
    /**
     * @var LoggerInterface The logger
     */
    protected LoggerInterface $logger;
    
    /**
     * @var ConfigProvider The config provider
     */
    protected ConfigProvider $config;
    
    /**
     * @var array The registered WebSocket handlers
     */
    protected array $handlers = [];

    /**
     * Create a new WebSocket server
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
        $this->config = $container->get(ConfigProvider::class);
    }

    /**
     * Register a WebSocket handler
     *
     * @param string $path
     * @param string|WebSocketHandlerInterface $handler
     * @return self
     */
    public function route(string $path, string|WebSocketHandlerInterface $handler): self
    {
        $this->handlers[$path] = $handler;
        
        return $this;
    }

    /**
     * Start the WebSocket server
     *
     * @param string|null $host
     * @param int|null $port
     * @return void
     */
    public function start(?string $host = null, ?int $port = null): void
    {
        $host = $host ?? $this->config->get('websocket.host', '127.0.0.1');
        $port = $port ?? $this->config->get('websocket.port', 8080);
        
        $this->logger->info("Starting WebSocket server at ws://{$host}:{$port}");
        
        $socket = ServerSocket::listen("{$host}:{$port}", (new BindContext())->withBacklog(1024));
        
        // Create the WebSocket server
        $this->server = new WebsocketServer($socket, $this->createRequestHandler(), $this->logger);
        
        // Get the gateway
        $this->gateway = $this->server->getGateway();
        
        // Register server shutdown
        EventLoop::onSignal(SIGINT, function () {
            $this->stop();
        });
        
        EventLoop::onSignal(SIGTERM, function () {
            $this->stop();
        });
        
        // Start the server
        $this->server->start();
    }

    /**
     * Create the request handler
     *
     * @return callable
     */
    protected function createRequestHandler(): callable
    {
        return function (Request $request, Response $response): Response {
            $path = $request->getUri()->getPath();
            
            // Check if a handler exists for this path
            if (!isset($this->handlers[$path])) {
                $this->logger->debug('No WebSocket handler registered for path', [
                    'path' => $path,
                ]);
                
                return $response;
            }
            
            $handler = $this->handlers[$path];
            
            // Resolve the handler from the container if needed
            if (is_string($handler)) {
                $handler = $this->container->get($handler);
            }
            
            if (!$handler instanceof WebSocketHandlerInterface) {
                throw new \InvalidArgumentException('WebSocket handler must implement WebSocketHandlerInterface');
            }
            
            // Set up the WebSocket connection
            $this->gateway->accept($request, $response, function (WebSocketConnection $connection) use ($handler) {
                // Call the handler
                $handler->onConnect($connection);
                
                // Process messages
                while ($message = $connection->receive()) {
                    $handler->onMessage($connection, $message);
                }
                
                // Connection closed
                $handler->onDisconnect($connection);
            });
            
            return $response;
        };
    }

    /**
     * Stop the WebSocket server
     *
     * @return void
     */
    public function stop(): void
    {
        $this->logger->info('Stopping WebSocket server');
        $this->server->stop();
    }

    /**
     * Get the WebSocket gateway
     *
     * @return Gateway
     */
    public function getGateway(): Gateway
    {
        return $this->gateway;
    }
}
