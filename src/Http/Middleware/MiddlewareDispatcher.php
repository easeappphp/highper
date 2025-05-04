<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Http\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use EaseAppPHP\HighPer\Framework\Http\Router\Router;
use Psr\Container\ContainerInterface;

class MiddlewareDispatcher implements RequestHandler
{
    /**
     * @var array<string|callable> The middleware stack
     */
    protected array $middleware = [];
    
    /**
     * @var Router The router
     */
    protected Router $router;
    
    /**
     * @var ContainerInterface The service container
     */
    protected ContainerInterface $container;

    /**
     * Create a new middleware dispatcher
     *
     * @param Router $router
     * @param ContainerInterface $container
     */
    public function __construct(Router $router, ContainerInterface $container)
    {
        $this->router = $router;
        $this->container = $container;
    }

    /**
     * Add middleware to the stack
     *
     * @param string|callable $middleware
     * @return self
     */
    public function add(string|callable $middleware): self
    {
        $this->middleware[] = $middleware;
        
        return $this;
    }

    /**
     * Set the middleware stack
     *
     * @param array $middleware
     * @return self
     */
    public function setMiddleware(array $middleware): self
    {
        $this->middleware = $middleware;
        
        return $this;
    }

    /**
     * Get the middleware stack
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Handle the request through the middleware pipeline
     *
     * @param Request $request
     * @return Response
     */
    public function handleRequest(Request $request): Response
    {
        // Create the middleware pipeline
        $pipeline = new MiddlewarePipeline($this->container);
        
        // Add middleware to the pipeline
        foreach ($this->middleware as $middleware) {
            $pipeline->pipe($middleware);
        }
        
        // Set the router as the final handler
        $pipeline->setHandler($this->router);
        
        // Process the request through the pipeline
        return $pipeline->process($request);
    }
}
