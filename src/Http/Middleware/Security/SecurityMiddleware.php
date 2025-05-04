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

class SecurityMiddleware implements Middleware
{
    /**
     * @var array The security configuration
     */
    protected array $config;
    
    /**
     * @var LoggerInterface The logger
     */
    protected LoggerInterface $logger;

    /**
     * Create a new security middleware
     *
     * @param ConfigProvider $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigProvider $config,
        LoggerInterface $logger
    ) {
        $this->config = $config->get('security', []);
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
        // Apply security headers
        $response = $requestHandler->handleRequest($request);
        
        // Add security headers
        $response = $this->addSecurityHeaders($response);
        
        return $response;
    }

    /**
     * Add security headers to the response
     *
     * @param Response $response
     * @return Response
     */
    protected function addSecurityHeaders(Response $response): Response
    {
        // Default security headers
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];
        
        // Content Security Policy
        if (isset($this->config['csp']) && $this->config['csp']['enabled'] ?? false) {
            $headers['Content-Security-Policy'] = $this->buildCspHeader();
        }
        
        // Strict Transport Security
        if (isset($this->config['hsts']) && $this->config['hsts']['enabled'] ?? false) {
            $maxAge = $this->config['hsts']['max_age'] ?? 31536000; // 1 year
            $includeSubdomains = $this->config['hsts']['include_subdomains'] ?? true;
            $preload = $this->config['hsts']['preload'] ?? false;
            
            $hsts = "max-age={$maxAge}";
            
            if ($includeSubdomains) {
                $hsts .= '; includeSubDomains';
            }
            
            if ($preload) {
                $hsts .= '; preload';
            }
            
            $headers['Strict-Transport-Security'] = $hsts;
        }
        
        // Feature Policy
        if (isset($this->config['feature_policy']) && $this->config['feature_policy']['enabled'] ?? false) {
            $headers['Feature-Policy'] = $this->buildFeaturePolicyHeader();
        }
        
        // Permissions Policy
        if (isset($this->config['permissions_policy']) && $this->config['permissions_policy']['enabled'] ?? false) {
            $headers['Permissions-Policy'] = $this->buildPermissionsPolicyHeader();
        }
        
        // Apply headers to the response
        foreach ($headers as $name => $value) {
            $response->setHeader($name, $value);
        }
        
        return $response;
    }

    /**
     * Build the Content Security Policy header
     *
     * @return string
     */
    protected function buildCspHeader(): string
    {
        $csp = $this->config['csp'] ?? [];
        $directives = [];
        
        $defaultDirectives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'"],
            'style-src' => ["'self'"],
            'img-src' => ["'self'"],
            'font-src' => ["'self'"],
            'connect-src' => ["'self'"],
            'media-src' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
        ];
        
        // Merge default directives with configured directives
        $directiveConfig = array_merge($defaultDirectives, $csp['directives'] ?? []);
        
        // Build the header value
        foreach ($directiveConfig as $directive => $sources) {
            if (!empty($sources)) {
                $directives[] = $directive . ' ' . implode(' ', $sources);
            }
        }
        
        return implode('; ', $directives);
    }

    /**
     * Build the Feature Policy header
     *
     * @return string
     */
    protected function buildFeaturePolicyHeader(): string
    {
        $policy = $this->config['feature_policy'] ?? [];
        $features = [];
        
        $defaultFeatures = [
            'camera' => ["'none'"],
            'microphone' => ["'none'"],
            'geolocation' => ["'none'"],
            'accelerometer' => ["'none'"],
            'gyroscope' => ["'none'"],
            'magnetometer' => ["'none'"],
            'payment' => ["'none'"],
        ];
        
        // Merge default features with configured features
        $featureConfig = array_merge($defaultFeatures, $policy['features'] ?? []);
        
        // Build the header value
        foreach ($featureConfig as $feature => $allowList) {
            if (!empty($allowList)) {
                $features[] = $feature . '=(' . implode(' ', $allowList) . ')';
            }
        }
        
        return implode('; ', $features);
    }

    /**
     * Build the Permissions Policy header
     *
     * @return string
     */
    protected function buildPermissionsPolicyHeader(): string
    {
        $policy = $this->config['permissions_policy'] ?? [];
        $permissions = [];
        
        $defaultPermissions = [
            'camera' => [],
            'microphone' => [],
            'geolocation' => [],
            'accelerometer' => [],
            'gyroscope' => [],
            'magnetometer' => [],
            'payment' => [],
        ];
        
        // Merge default permissions with configured permissions
        $permissionConfig = array_merge($defaultPermissions, $policy['permissions'] ?? []);
        
        // Build the header value
        foreach ($permissionConfig as $permission => $allowList) {
            if (empty($allowList)) {
                $permissions[] = $permission . '=()';
            } else {
                $allowListStr = implode(' ', array_map(function ($origin) {
                    return "\"$origin\"";
                }, $allowList));
                
                $permissions[] = $permission . '=(' . $allowListStr . ')';
            }
        }
        
        return implode(', ', $permissions);
    }
}