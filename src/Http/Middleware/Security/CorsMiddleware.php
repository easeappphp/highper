<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Http\Middleware\Security;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Status;
use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use Psr\Log\LoggerInterface;

class CorsMiddleware implements Middleware
{
    /**
     * @var array The CORS configuration
     */
    protected array $config;
    
    /**
     * @var LoggerInterface The logger
     */
    protected LoggerInterface $logger;

    /**
     * Create a new CORS middleware
     *
     * @param ConfigProvider $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigProvider $config,
        LoggerInterface $logger
    ) {
        $this->config = $config->get('cors', []);
        $this->logger = $logger;
    }

    /**
     * Process the request
     *
     * @param Request $request
     * @param RequestHandler $requestHandler
     * @return Response
     */
    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        // Check if CORS is enabled
        if (!($this->config['enabled'] ?? false)) {
            return $requestHandler->handleRequest($request);
        }
        
        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflight($request);
        }
        
        // Handle actual requests
        $response = $requestHandler->handleRequest($request);
        
        // Add CORS headers to the response
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight requests
     *
     * @param Request $request
     * @return Response
     */
    protected function handlePreflight(Request $request): Response
    {
        // Check if the request is a valid CORS request
        if (!$this->isValidCorsRequest($request)) {
            $this->logger->info('Invalid CORS preflight request', [
                'origin' => $request->getHeader('Origin'),
            ]);
            
            return new Response(Status::FORBIDDEN);
        }
        
        // Create a response with CORS headers
        $response = new Response(Status::NO_CONTENT);
        
        // Add CORS headers
        $response = $this->addPreflightHeaders($request, $response);
        
        return $response;
    }

    /**
     * Add CORS headers to the response
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    protected function addCorsHeaders(Request $request, Response $response): Response
    {
        // Get the origin
        $origin = $request->getHeader('Origin');
        
        // If no origin, return the response as is
        if (!$origin) {
            return $response;
        }
        
        // Check if the origin is allowed
        if (!$this->isAllowedOrigin($origin)) {
            return $response;
        }
        
        // Add the Access-Control-Allow-Origin header
        $response->setHeader('Access-Control-Allow-Origin', $this->getAllowOriginHeaderValue($origin));
        
        // Add the Access-Control-Allow-Credentials header if enabled
        if ($this->config['allow_credentials'] ?? false) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }
        
        // Add the Access-Control-Expose-Headers header if configured
        $exposeHeaders = $this->config['expose_headers'] ?? [];
        
        if (!empty($exposeHeaders)) {
            $response->setHeader('Access-Control-Expose-Headers', implode(', ', $exposeHeaders));
        }
        
        return $response;
    }

    /**
     * Add preflight headers to the response
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    protected function addPreflightHeaders(Request $request, Response $response): Response
    {
        // Add standard CORS headers
        $response = $this->addCorsHeaders($request, $response);
        
        // Add the Access-Control-Allow-Methods header
        $allowMethods = $this->config['allow_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $allowMethods));
        
        // Add the Access-Control-Allow-Headers header
        $allowHeaders = $this->config['allow_headers'] ?? ['Content-Type', 'X-Requested-With', 'Authorization'];
        
        // Get the request headers
        $requestHeaders = $request->getHeader('Access-Control-Request-Headers');
        
        if ($requestHeaders) {
            // Split the request headers
            $requestHeaderList = array_map('trim', explode(',', $requestHeaders));
            
            // Filter out disallowed headers
            $allowedRequestHeaders = array_filter($requestHeaderList, function ($header) use ($allowHeaders) {
                return in_array($header, $allowHeaders) || strtolower($header) === 'content-type';
            });
            
            if (!empty($allowedRequestHeaders)) {
                $response->setHeader('Access-Control-Allow-Headers', implode(', ', $allowedRequestHeaders));
            }
        } else {
            $response->setHeader('Access-Control-Allow-Headers', implode(', ', $allowHeaders));
        }
        
        // Add the Access-Control-Max-Age header
        $maxAge = $this->config['max_age'] ?? 86400; // 24 hours
        $response->setHeader('Access-Control-Max-Age', (string) $maxAge);
        
        return $response;
    }

    /**
     * Check if the origin is allowed
     *
     * @param string $origin
     * @return bool
     */
    protected function isAllowedOrigin(string $origin): bool
    {
        $allowedOrigins = $this->config['allow_origins'] ?? ['*'];
        
        // If all origins are allowed
        if (in_array('*', $allowedOrigins)) {
            return true;
        }
        
        // Check if the origin is in the allowed list
        return in_array($origin, $allowedOrigins);
    }

    /**
     * Get the Access-Control-Allow-Origin header value
     *
     * @param string $origin
     * @return string
     */
    protected function getAllowOriginHeaderValue(string $origin): string
    {
        $allowedOrigins = $this->config['allow_origins'] ?? ['*'];
        
        // If all origins are allowed and wildcard is allowed
        if (in_array('*', $allowedOrigins) && !($this->config['allow_credentials'] ?? false)) {
            return '*';
        }
        
        // If the origin is allowed, return it
        if (in_array($origin, $allowedOrigins)) {
            return $origin;
        }
        
        // Default to the first allowed origin
        return $allowedOrigins[0] ?? '*';
    }

    /**
     * Check if the request is a valid CORS request
     *
     * @param Request $request
     * @return bool
     */
    protected function isValidCorsRequest(Request $request): bool
    {
        // Check if the request has an Origin header
        $origin = $request->getHeader('Origin');
        
        if (!$origin) {
            return false;
        }
        
        // Check if the origin is allowed
        if (!$this->isAllowedOrigin($origin)) {
            return false;
        }
        
        // Check if the method is allowed
        $method = $request->getHeader('Access-Control-Request-Method');
        
        if ($method) {
            $allowMethods = $this->config['allow_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
            
            if (!in_array($method, $allowMethods)) {
                return false;
            }
        }
        
        return true;
    }
}