<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Config;

use Dotenv\Dotenv;

class DotEnvLoader
{
    /**
     * @var string The base path
     */
    protected string $basePath;

    /**
     * Create a new DotEnv loader
     *
     * @param string $basePath
     */
    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Load environment variables
     *
     * @return void
     */
    public function load(): void
    {
        // Check if .env file exists
        if (file_exists($this->basePath . '/.env')) {
            // Create and load the dotenv instance
            $dotenv = Dotenv::createImmutable($this->basePath);
            $dotenv->load();
            
            // Load environment-specific .env file if exists
            $env = env('APP_ENV', 'production');
            $envFile = ".env.{$env}";
            
            if (file_exists($this->basePath . '/' . $envFile)) {
                $dotenv = Dotenv::createImmutable($this->basePath, $envFile);
                $dotenv->load();
            }
        }
    }
}
