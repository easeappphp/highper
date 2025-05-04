<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Http\Router;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router as AmpRouter;
use Amp\Http\Status;
use EaseAppPHP\HighPer\Framework\Http\Server\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class Router implements RequestHandler
{
    /**
     * @var AmpRouter The AmPHP router
     */
    protected AmpRouter $router;
    
    /**
     * @var RouteCollection The route collection
     */
    protected RouteCollection $routes;
    
    /**
     * @var ContainerInterface The service container
     */
    protected ContainerInterface $container;
    
    /**
     * @var LoggerInterface The logger
     */
    protected LoggerInterface $logger;

    /**
     * Create a new router instance
     *
     * @param ContainerInterface $container
     * @param LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->routes = new RouteCollection();
        $this->router = new AmpRouter();
    }

    /**
     * Add a GET route
     *
     * @param string $path The route path
     * @param string|callable $handler The route handler
     * @param string|null $name The route name
     * @return Route
     */
    public function get(string $path, string|callable $handler, ?string $name = null): Route
    {
        return $this->addRoute(['GET'], $path, $handler, $name);
    }

    /**
     * Add a POST route
     *
     * @param string $path The route path
     * @param string|callable $handler The route handler
     * @param string|null $name The route name
     * @return Route
     */
    public function post(string $path, string|callable $handler, ?string $name = null): Route
    {
        return $this->addRoute(['POST'], $path, $handler, $name);
    }

    /**
     * Add a PUT route
     *
     * @param string $path The route path
     * @param string|callable $handler The route handler
     * @param string|null $name The route name
     * @return Route
     */
    public function put(string $path, string|callable $handler, ?string $name = null): Route
    {
        return $this->addRoute(['PUT'], $path, $handler, $name);
    }

    /**
     * Add a DELETE route
     *
     * @param string $path The route path
     * @param string|callable $handler The route handler
     * @param string|null $name The route name
     * @return Route
     */
    public function delete(string $path, string|callable $handler, ?string $name = null): Route
    {
        return $this->addRoute(['DELETE'], $path, $handler, $name);
    }

    /**
     * Add a PATCH route
     *
     * @param string $path The route path
     * @param string|callable $handler The route handler
     * @param string|null $name The route name
     * @return Route
     */
    public function patch(string $path, string|callable $handler, ?string $name = null): Route
    {
        return $this->addRoute(['PATCH'], $path, $handler, $name);
    }

    /**
     * Add a route that responds to any HTTP method
     *
     * @param string $path The route path
     * @param string|callable $handler The route handler
     * @param string|null $name The route name
     * @return Route
     */
    public function any(string $path, string|callable $handler, ?string $name = null): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], $path, $handler, $name);
    }

    /**
     * Add a new route
     *
     * @param array $methods The HTTP methods
     * @param string $path The route path
     * @param string|callable $handler The route handler
     * @param string|null $name The route name
     * @return Route
     */
    public function addRoute(array $methods, string $path, string|callable $handler, ?string $name = null): Route
    {
        $route = new Route($methods, $path, $handler, $name);
        $this->routes->add($route);
        
        return $route;
    }

    /**
     * Group routes with a common prefix
     *
     * @param string $prefix The route prefix
     * @param callable $callback The callback to define routes
     * @return void
     */
    public function group(string $prefix, callable $callback): void
    {
        $previousPrefix = $this->routes->getPrefix();
        $this->routes->setPrefix($previousPrefix . $prefix);
        
        $callback($this);
        
        $this->routes->setPrefix($previousPrefix);
    }

    /**
     * Register all routes with the AmPHP router
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        foreach ($this->routes->all() as $route) {
            $path = $route->getPath();
            $methods = $route->getMethods();
            $handler = $route->getHandler();
            
            // Convert string handlers to callables resolving from the container
            if (is_string($handler)) {
                $handlerParts = explode('@', $handler);
                $controllerClass = $handlerParts[0];
                $method = $handlerParts[1] ?? 'handle';
                
                $handler = function (Request $request) use ($controllerClass, $method) {
                    $controller = $this->container->get($controllerClass);
                    
                    return $controller->$method($request);
                };
            }
            
            // Register the route with the AmPHP router
            $this->router->addRoute($methods, $path, $handler);
        }
    }

    /**
     * Handle the HTTP request
     *
     * @param Request $request
     * @return Response
     */
    public function handleRequest(Request $request): Response
    {
        // Ensure routes are registered
        $this->registerRoutes();
        
        try {
            // Handle the request using the AmPHP router
            return $this->router->handleRequest($request);
        } catch (\Throwable $e) {
            $this->logger->error('Router error: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => [
                    'method' => $request->getMethod(),
                    'uri' => (string)$request->getUri(),
                ]
            ]);
            
            // Return a 500 error response
            return new Response(
                Status::INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'Internal server error'])
            );
        }
    }
}
