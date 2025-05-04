<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Config;

class ConfigProvider
{
    /**
     * @var array The configuration items
     */
    protected array $items = [];

    /**
     * Create a new config provider
     *
     * @param string $basePath
     */
    public function __construct(protected string $basePath)
    {
        $this->load();
    }

    /**
     * Load configuration files
     *
     * @return void
     */
    protected function load(): void
    {
        $configPath = $this->basePath . '/config';
        
        // Check if config directory exists
        if (!is_dir($configPath)) {
            return;
        }
        
        // Scan the config directory for PHP files
        $files = glob($configPath . '/*.php');
        
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $config = require $file;
            
            if (is_array($config)) {
                $this->items[$key] = $config;
            }
        }
    }

    /**
     * Get a configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // If the key contains a dot, we'll assume it's being used to reference a nested array
        if (str_contains($key, '.')) {
            return $this->getFromDottedNotation($key, $default);
        }
        
        return $this->items[$key] ?? $default;
    }

    /**
     * Get a configuration value using dotted notation
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getFromDottedNotation(string $key, mixed $default): mixed
    {
        $keys = explode('.', $key);
        $value = $this->items;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            
            $value = $value[$segment];
        }
        
        return $value;
    }

    /**
     * Set a configuration value
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        // If the key contains a dot, we'll assume it's being used to reference a nested array
        if (str_contains($key, '.')) {
            $this->setWithDottedNotation($key, $value);
            return;
        }
        
        $this->items[$key] = $value;
    }

    /**
     * Set a configuration value using dotted notation
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setWithDottedNotation(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $arrayRef = &$this->items;
        
        foreach ($keys as $index => $segment) {
            if ($index === count($keys) - 1) {
                $arrayRef[$segment] = $value;
                break;
            }
            
            if (!isset($arrayRef[$segment]) || !is_array($arrayRef[$segment])) {
                $arrayRef[$segment] = [];
            }
            
            $arrayRef = &$arrayRef[$segment];
        }
    }

    /**
     * Check if a configuration value exists
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * Get all configuration items
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }
}
