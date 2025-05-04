<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Http\Router;

class Route
{
    /**
     * @var array<string> The middleware for this route
     */
    protected array $middleware = [];

    /**
     * Create a new route instance
     *
     * @param array $methods The HTTP methods
     * @param string $path The route path
     * @param string|callable $handler The route handler
     * @param string|null $name The route name
     */
    public function __construct(
        protected array $methods,
        protected string $path,
        protected string|callable $handler,
        protected ?string $name = null
    ) {
    }

    /**
     * Add middleware to the route
     *
     * @param string|array $middleware
     * @return self
     */
    public function middleware(string|array $middleware): self
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_merge($this->middleware, $middleware);
        
        return $this;
    }

    /**
     * Get the HTTP methods
     *
     * @return array
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Get the route path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the route handler
     *
     * @return string|callable
     */
    public function getHandler(): string|callable
    {
        return $this->handler;
    }

    /**
     * Get the route name
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the route middleware
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Set the route name
     *
     * @param string $name
     * @return self
     */
    public function name(string $name): self
    {
        $this->name = $name;
        
        return $this;
    }
}