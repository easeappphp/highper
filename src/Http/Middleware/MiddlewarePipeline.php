<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Http\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Psr\Container\ContainerInterface;

class MiddlewarePipeline
{
    /**
     * @var array<string|callable> The middleware queue
     */
    protected array $queue = [];
    
    /**
     * @var RequestHandler The final request handler
     */
    protected RequestHandler $handler;
    
    /**
     * @var ContainerInterface The service container
     */
    protected ContainerInterface $container;

    /**
     * Create a new middleware pipeline
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Add middleware to the pipeline
     *
     * @param string|callable $middleware
     * @return self
     */
    public function pipe(string|callable $middleware): self
    {
        $this->queue[] = $middleware;
        
        return $this;
    }

    /**
     * Set the final request handler
     *
     * @param RequestHandler $handler
     * @return self
     */
    public function setHandler(RequestHandler $handler): self
    {
        $this->handler = $handler;
        
        return $this;
    }

    /**
     * Process the request through the middleware pipeline
     *
     * @param Request $request
     * @return Response
     */
    public function process(Request $request): Response
    {
        // Create the middleware stack
        $stack = $this->createStack();
        
        // Process the request through the stack
        return $stack->handleRequest($request);
    }

    /**
     * Create the middleware stack
     *
     * @return RequestHandler
     */
    protected function createStack(): RequestHandler
    {
        // Start with the final handler
        $next = $this->handler;
        
        // Build the stack from the end to the beginning
        foreach (array_reverse($this->queue) as $middleware) {
            $next = $this->createMiddleware($middleware, $next);
        }
        
        return $next;
    }

    /**
     * Create a middleware instance
     *
     * @param string|callable $middleware
     * @param RequestHandler $next
     * @return RequestHandler
     */
    protected function createMiddleware(string|callable $middleware, RequestHandler $next): RequestHandler
    {
        // Resolve middleware from the container if it's a class name
        if (is_string($middleware)) {
            $middleware = $this->container->get($middleware);
        }
        
        // If middleware is callable but not an instance of Middleware, wrap it
        if (is_callable($middleware) && !$middleware instanceof Middleware) {
            return new class($middleware, $next) implements RequestHandler {
                public function __construct(
                    private $middleware,
                    private RequestHandler $next
                ) {}
                
                public function handleRequest(Request $request): Response
                {
                    return ($this->middleware)($request, $this->next);
                }
            };
        }
        
        // If middleware is an instance of Middleware, use it directly
        if ($middleware instanceof Middleware) {
            return new class($middleware, $next) implements RequestHandler {
                public function __construct(
                    private Middleware $middleware,
                    private RequestHandler $next
                ) {}
                
                public function handleRequest(Request $request): Response
                {
                    return $this->middleware->handleRequest($request, $this->next);
                }
            };
        }
        
        throw new \InvalidArgumentException('Invalid middleware provided');
    }
}
