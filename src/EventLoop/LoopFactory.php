<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\EventLoop;

use Revolt\EventLoop\Driver;
use Revolt\EventLoop\UvDriver;
use Revolt\EventLoop\DriverFactory;

class LoopFactory
{
    /**
     * Create a new event loop driver instance
     *
     * @return Driver
     */
    public static function create(): Driver
    {
        // Use UvDriver if libuv is available for optimal performance
        if (extension_loaded('uv')) {
            return new UvDriver();
        }
        
        // Fall back to the default driver factory
        return (new DriverFactory())->create();
    }
}
