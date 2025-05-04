<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Http\Client;

use Amp\Http\Client\HttpClient as AmpHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\TimeoutCancellation;
use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Log\LoggerInterface;

class Client
{
    /**
     * @var AmpHttpClient The AmPHP HTTP client
     */
    protected AmpHttpClient $client;
    
    /**
     * @var ConfigProvider The config provider
     */
    protected ConfigProvider $config;
    
    /**
     * @var LoggerInterface The logger
     */
    protected LoggerInterface $logger;
    
    /**
     * @var TracerInterface The tracer
     */
    protected TracerInterface $tracer;
    
    /**
     * @var array Default request options
     */
    protected array $defaultOptions = [
        'timeout' => 30,
        'follow_redirects' => 5,
        'headers' => [],
    ];

    /**
     * Create a new HTTP client
     *
     * @param ConfigProvider $config
     * @param LoggerInterface $logger
     * @param TracerInterface $tracer
     */
    public function __construct(
        ConfigProvider $config,
        LoggerInterface $logger,
        TracerInterface $tracer
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->tracer = $tracer;
        
        $this->loadConfig();
        $this->initialize();
    }

    /**
     * Load the HTTP client configuration
     *
     * @return void
     */
    protected function loadConfig(): void
    {
        $httpConfig = $this->config->get('http.client', []);
        
        if (isset($httpConfig['timeout'])) {
            $this->defaultOptions['timeout'] = $httpConfig['timeout'];
        }
        
        if (isset($httpConfig['follow_redirects'])) {
            $this->defaultOptions['follow_redirects'] = $httpConfig['follow_redirects'];
        }
        
        if (isset($httpConfig['headers'])) {
            $this->defaultOptions['headers'] = $httpConfig['headers'];
        }
    }

    /**
     * Initialize the HTTP client
     *
     * @return void
     */
    protected function initialize(): void
    {
        // Create the HTTP client
        $builder = HttpClientBuilder::buildDefault();
        
        // Add middlewares if needed
        // For example, logging, retries, circuit breakers, etc.
        
        $this->client = $builder->build();
    }

    /**
     * Send a request
     *
     * @param Request $request
     * @param array $options
     * @return Response
     */
    public function send(Request $request, array $options = []): Response
    {
        $options = array_merge($this->defaultOptions, $options);
        
        // Apply default headers
        foreach ($this->defaultOptions['headers'] as $name => $value) {
            if (!$request->hasHeader($name)) {
                $request->setHeader($name, $value);
            }
        }
        
        // Apply tracing
        $span = $this->tracer->spanBuilder("HTTP {$request->getMethod()}")
            ->setAttribute('http.method', $request->getMethod())
            ->setAttribute('http.url', (string) $request->getUri())
            ->startSpan();
            
        // Set timeout cancellation
        $cancellation = new TimeoutCancellation($options['timeout']);
        
        try {
            // Log the request
            $this->logger->info('Sending HTTP request', [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
            ]);
            
            // Send the request
            $response = $this->client->request($request, $cancellation);
            
            // Log the response
            $this->logger->info('Received HTTP response', [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
                'status' => $response->getStatus(),
            ]);
            
            // Set span attributes
            $span->setAttribute('http.status_code', $response->getStatus());
            
            return $response;
        } catch (\Throwable $e) {
            // Log the error
            $this->logger->error('HTTP request failed', [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
                'exception' => $e,
            ]);
            
            // Record the exception
            $span->recordException($e);
            
            throw $e;
        } finally {
            // End the span
            $span->end();
        }
    }

    /**
     * Send a GET request
     *
     * @param string $url
     * @param array $headers
     * @param array $options
     * @return Response
     */
    public function get(string $url, array $headers = [], array $options = []): Response
    {
        $request = new Request($url);
        $request->setMethod('GET');
        
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        
        return $this->send($request, $options);
    }

    /**
     * Send a POST request
     *
     * @param string $url
     * @param mixed $body
     * @param array $headers
     * @param array $options
     * @return Response
     */
    public function post(string $url, mixed $body = null, array $headers = [], array $options = []): Response
    {
        $request = new Request($url);
        $request->setMethod('POST');
        
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        
        if ($body !== null) {
            $this->setRequestBody($request, $body);
        }
        
        return $this->send($request, $options);
    }

    /**
     * Send a PUT request
     *
     * @param string $url
     * @param mixed $body
     * @param array $headers
     * @param array $options
     * @return Response
     */
    public function put(string $url, mixed $body = null, array $headers = [], array $options = []): Response
    {
        $request = new Request($url);
        $request->setMethod('PUT');
        
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        
        if ($body !== null) {
            $this->setRequestBody($request, $body);
        }
        
        return $this->send($request, $options);
    }

    /**
     * Send a PATCH request
     *
     * @param string $url
     * @param mixed $body
     * @param array $headers
     * @param array $options
     * @return Response
     */
    public function patch(string $url, mixed $body = null, array $headers = [], array $options = []): Response
    {
        $request = new Request($url);
        $request->setMethod('PATCH');
        
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        
        if ($body !== null) {
            $this->setRequestBody($request, $body);
        }
        
        return $this->send($request, $options);
    }

    /**
     * Send a DELETE request
     *
     * @param string $url
     * @param mixed $body
     * @param array $headers
     * @param array $options
     * @return Response
     */
    public function delete(string $url, mixed $body = null, array $headers = [], array $options = []): Response
    {
        $request = new Request($url);
        $request->setMethod('DELETE');
        
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        
        if ($body !== null) {
            $this->setRequestBody($request, $body);
        }
        
        return $this->send($request, $options);
    }

    /**
     * Set the request body
     *
     * @param Request $request
     * @param mixed $body
     * @return void
     */
    protected function setRequestBody(Request $request, mixed $body): void
    {
        if (is_string($body)) {
            $request->setBody($body);
        } elseif (is_array($body) || is_object($body)) {
            $request->setHeader('Content-Type', 'application/json');
            $request->setBody(json_encode($body));
        } else {
            throw new \InvalidArgumentException('Unsupported request body type');
        }
    }
}
