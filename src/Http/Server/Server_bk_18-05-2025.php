<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Http\Server;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Socket\BindContext;
use Amp\Socket\ServerSocket;
use EaseAppPHP\HighPer\Framework\Http\Middleware\MiddlewareDispatcher;
use EaseAppPHP\HighPer\Framework\Http\Router\Router;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;

class Server
{
    /**
     * @var HttpServer The AmPHP HTTP server instance
     */
    protected HttpServer $server;
    
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
        $sockets = [
            ServerSocket::listen("$host:$port", (new BindContext())->withBacklog(1024))
        ];

        $this->logger->info("Starting server at http://$host:$port");

        // Create the final request handler that combines the middleware pipeline with the router
        $requestHandler = new CallableRequestHandler(function ($request) {
            return $this->middlewareDispatcher->handle($request);
        });

        // Create the HTTP server
        $this->server = new HttpServer(
            $sockets,
            $requestHandler,
            $this->logger,
            (new DefaultHttpDriverFactory())->withConnectionLimit(10000000) // C10M support
        );

        // Register server shutdown on loop termination
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
     * Stop the HTTP server
     *
     * @return void
     */
    public function stop(): void
    {
        $this->logger->info("Stopping server");
        $this->server->stop();
        EventLoop::stop();
    }
}
