<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Http\Middleware\Security;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Status;
use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use EaseAppPHP\HighPer\Framework\Concurrency\Cache\AsyncCache;
use Psr\Log\LoggerInterface;

class RateLimitingMiddleware implements Middleware
{
    /**
     * @var array The rate limiting configuration
     */
    protected array $config;
    
    /**
     * @var AsyncCache The cache
     */
    protected AsyncCache $cache;
    
    /**
     * @var LoggerInterface The logger
     */
    protected LoggerInterface $logger;

    /**
     * Create a new rate limiting middleware
     *
     * @param ConfigProvider $config
     * @param AsyncCache $cache
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigProvider $config,
        AsyncCache $cache,
        LoggerInterface $logger
    ) {
        $this->config = $config->get('rate_limiting', []);
        $this->cache = $cache;
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
        // Check if rate limiting is enabled
        if (!($this->config['enabled'] ?? false)) {
            return $requestHandler->handleRequest($request);
        }
        
        // Get the client identifier
        $identifier = $this->getClientIdentifier($request);
        
        // Check if the client is rate limited
        if ($this->isRateLimited($identifier)) {
            $this->logger->warning('Client rate limited', [
                'identifier' => $identifier,
            ]);
            
            return $this->createRateLimitedResponse();
        }
        
        // Increment the request count
        $this->incrementRequestCount($identifier);
        
        // Process the request
        $response = $requestHandler->handleRequest($request);
        
        // Add rate limit headers to the response
        return $this->addRateLimitHeaders($response, $identifier);
    }

    /**
     * Get the client identifier
     *
     * @param Request $request
     * @return string
     */
    protected function getClientIdentifier(Request $request): string
    {
        $identifierType = $this->config['identifier'] ?? 'ip';
        
        // Get the identifier based on the type
        switch ($identifierType) {
            case 'ip':
                return $this->getClientIp($request);
            
            case 'user':
                // Get the user ID from the request attributes
                return (string) ($request->getAttribute('user_id') ?? 'guest');
            
            case 'token':
                // Get the API token from the request
                $token = $request->getHeader('Authorization');
                return $token ? substr($token, 7) : 'guest'; // Remove "Bearer " prefix
            
            default:
                return $this->getClientIp($request);
        }
    }

    /**
     * Get the client IP address
     *
     * @param Request $request
     * @return string
     */
    protected function getClientIp(Request $request): string
    {
        // Check for X-Forwarded-For header
        $forwardedFor = $request->getHeader('X-Forwarded-For');
        
        if ($forwardedFor) {
            // Get the first IP in the list
            $ips = array_map('trim', explode(',', $forwardedFor));
            return $ips[0];
        }
        
        // Get the client address from the connection
        return $request->getClient()->getRemoteAddress()->getHost();
    }

    /**
     * Check if the client is rate limited
     *
     * @param string $identifier
     * @return bool
     */
    protected function isRateLimited(string $identifier): bool
    {
        // Get the rate limit key
        $key = $this->getRateLimitKey($identifier);
        
        // Get the current request count
        $count = $this->getRequestCount($key);
        
        // Get the rate limit
        $limit = $this->config['limit'] ?? 60;
        
        // Check if the count exceeds the limit
        return $count >= $limit;
    }

    /**
     * Get the rate limit key
     *
     * @param string $identifier
     * @return string
     */
    protected function getRateLimitKey(string $identifier): string
    {
        // Get the current time window
        $window = $this->getCurrentTimeWindow();
        
        // Create the key
        return "rate_limit:{$identifier}:{$window}";
    }

    /**
     * Get the current time window
     *
     * @return int
     */
    protected function getCurrentTimeWindow(): int
    {
        // Get the window duration in seconds
        $duration = $this->config['duration'] ?? 60;
        
        // Calculate the current window
        return (int) (time() / $duration);
    }

    /**
     * Get the request count for the given key
     *
     * @param string $key
     * @return int
     */
    protected function getRequestCount(string $key): int
    {
        // Get the count from the cache
        $count = $this->cache->get($key);
        
        // If no count, return 0
        if ($count === null) {
            return 0;
        }
        
        return (int) $count;
    }

    /**
     * Increment the request count
     *
     * @param string $identifier
     * @return void
     */
    protected function incrementRequestCount(string $identifier): void
    {
        // Get the rate limit key
        $key = $this->getRateLimitKey($identifier);
        
        // Get the current count
        $count = $this->getRequestCount($key);
        
        // Increment the count
        $count++;
        
        // Get the window duration in seconds
        $duration = $this->config['duration'] ?? 60;
        
        // Set the new count in the cache
        $this->cache->set($key, $count, $duration);
    }

    /**
     * Create a rate limited response
     *
     * @return Response
     */
    protected function createRateLimitedResponse(): Response
    {
        // Get the status code
        $statusCode = $this->config['status_code'] ?? Status::TOO_MANY_REQUESTS;
        
        // Create the response
        $response = new Response($statusCode, [
            'Content-Type' => 'application/json',
        ], json_encode([
            'error' => 'Too many requests',
            'status' => $statusCode,
        ]));
        
        // Get the retry after time
        $retryAfter = $this->config['retry_after'] ?? 60;
        
        // Add the Retry-After header
        $response->setHeader('Retry-After', (string) $retryAfter);
        
        return $response;
    }

    /**
     * Add rate limit headers to the response
     *
     * @param Response $response
     * @param string $identifier
     * @return Response
     */
    protected function addRateLimitHeaders(Response $response, string $identifier): Response
    {
        // Get the rate limit
        $limit = $this->config['limit'] ?? 60;
        
        // Get the rate limit key
        $key = $this->getRateLimitKey($identifier);
        
        // Get the current count
        $count = $this->getRequestCount($key);
        
        // Calculate remaining requests
        $remaining = max(0, $limit - $count);
        
        // Get the window duration in seconds
        $duration = $this->config['duration'] ?? 60;
        
        // Calculate reset time
        $resetTime = (($this->getCurrentTimeWindow() + 1) * $duration) - time();
        
        // Add the headers
        $response->setHeader('X-RateLimit-Limit', (string) $limit);
        $response->setHeader('X-RateLimit-Remaining', (string) $remaining);
        $response->setHeader('X-RateLimit-Reset', (string) $resetTime);
        
        return $response;
    }
}