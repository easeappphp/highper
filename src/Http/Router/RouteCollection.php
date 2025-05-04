<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Http\Router;

class RouteCollection
{
    /**
     * @var array<Route> The registered routes
     */
    protected array $routes = [];
    
    /**
     * @var array<string, Route> The named routes
     */
    protected array $namedRoutes = [];
    
    /**
     * @var string The current route prefix
     */
    protected string $prefix = '';

    /**
     * Add a route to the collection
     *
     * @param Route $route
     * @return void
     */
    public function add(Route $route): void
    {
        // Apply the current prefix to the route
        $path = $route->getPath();
        if ($this->prefix !== '') {
            $path = rtrim($this->prefix, '/') . '/' . ltrim($path, '/');
            
            // Create a new route with the prefixed path
            $route = new Route(
                $route->getMethods(),
                $path,
                $route->getHandler(),
                $route->getName()
            );
            
            // Re-apply middleware
            $route->middleware($route->getMiddleware());
        }
        
        $this->routes[] = $route;
        
        // Register named routes
        if ($name = $route->getName()) {
            $this->namedRoutes[$name] = $route;
        }
    }

    /**
     * Get all routes
     *
     * @return array<Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Get a named route
     *
     * @param string $name
     * @return Route|null
     */
    public function getByName(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Set the current route prefix
     *
     * @param string $prefix
     * @return void
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * Get the current route prefix
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
