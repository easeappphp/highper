<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Http\Server;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\SocketHttpServer; // Added SocketHttpServer
use Amp\Socket\BindContext;
use Amp\Socket\InternetAddress;
use function Amp\Socket\listen;
use EaseAppPHP\HighPer\Framework\Http\Middleware\MiddlewareDispatcher;
use EaseAppPHP\HighPer\Framework\Http\Router\Router;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use function Amp\trapSignal;

class Server
{
    /**
     * @var SocketHttpServer The AmPHP HTTP server instance
     */
    protected SocketHttpServer $server;
    
    /**
     * @var Router The router
     */
    protected Router $router;
    
    /**
     * @var MiddlewareDispatcher The middleware dispatcher
     */
    protected MiddlewareDispatcher $middlewareDispatcher;
    
    /**
     * @var LoggerInterface The logger
     */
    protected LoggerInterface $logger;

    /**
     * Create a new HTTP server
     *
     * @param ContainerInterface $container
     */
    public function __construct(protected ContainerInterface $container)
    {
        $this->router = $container->get(Router::class);
        $this->middlewareDispatcher = $container->get(MiddlewareDispatcher::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * Start the HTTP server
     *
     * @param string $host The server host
     * @param int $port The server port
     * @return void
     */
    public function start(string $host, int $port): void
    {
        try {
            // Create the server with direct access
            //$this->server = SocketHttpServer::createForDirectAccess($this->logger);
			$this->server = SocketHttpServer::createForDirectAccess(
				$this->logger,  // First parameter: logger
				true,          // Second parameter: enableCompression (boolean, not null)
			);
            
			$this->logger->info("SocketHttpServer exposing on $host:$port");
			
            // Expose the address to listen on
            $this->server->expose(new InternetAddress($host, $port));
            
			$this->logger->info("Successfully exposed on $host:$port");
			
            $this->logger->info("Starting server at http://$host:$port");
            
            // Create the final request handler that combines the middleware pipeline with the router
            $requestHandler = new ClosureRequestHandler(function ($request) {
                return $this->middlewareDispatcher->handle($request);
            });
            
            // Create error handler
            $errorHandler = new DefaultErrorHandler();
            
            // Start the server with the request handler and error handler
            $this->server->start($requestHandler, $errorHandler);
            
            // Register server shutdown on loop termination
            EventLoop::onSignal(SIGINT, function () {
                $this->stop();
            });
            
            EventLoop::onSignal(SIGTERM, function () {
                $this->stop();
            });

        } catch (\Throwable $e) {
            $this->logger->error('Server failed to start: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Stop the HTTP server
     *
     * @return void
     */
    public function stop(): void
    {
        try {
            $this->logger->info("Stopping server gracefully");
            $this->server->stop();
            $this->logger->info("Server stopped successfully");
        } catch (\Throwable $e) {
            $this->logger->error('Error while stopping server: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        } finally {
            EventLoop::stop();
        }
    }
    
    /**
     * Check if the server is running
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return isset($this->server) && $this->server instanceof SocketHttpServer;
    }
}