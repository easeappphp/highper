# Highper Project Structure

## Framework Library Structure

```
highper/framework/
├── composer.json                   # Composer configuration
├── LICENSE                         # MIT license file
├── README.md                       # Framework documentation
├── src/                            # Source code
│   ├── API/                        # API components
│   │   ├── ApiController.php       # Base API controller
│   │   ├── JsonFormatter.php       # JSON response formatter
│   │   └── MessagePackFormatter.php # MessagePack formatter
│   ├── Benchmark/                  # Benchmarking tools
│   │   └── BenchmarkTool.php       # Performance benchmarking
│   ├── Concurrency/                # Async operations
│   │   ├── Cache/                  # Cache components
│   │   │   ├── AsyncCache.php      # Async cache implementation
│   │   │   ├── CacheDriverInterface.php # Cache driver interface
│   │   │   ├── FilesystemDriver.php # File-based cache driver
│   │   │   ├── MemcachedDriver.php # Memcached driver
│   │   │   ├── MemoryDriver.php    # In-memory cache driver
│   │   │   └── RedisDriver.php     # Redis cache driver
│   │   ├── Database/               # Database components
│   │   │   └── AsyncDatabase.php   # Async database wrapper
│   │   ├── File/                   # Filesystem components
│   │   │   └── AsyncFileSystem.php # Async file operations
│   │   ├── Pool/                   # Connection pooling
│   │   │   ├── ConnectionPool.php  # Base connection pool
│   │   │   └── WorkerPool.php      # Worker thread pool
│   │   └── Queue/                  # Message queues
│   │       ├── PullQueue.php       # Consumer queue
│   │       └── PushQueue.php       # Producer queue
│   ├── Config/                     # Configuration components
│   │   ├── ConfigProvider.php      # Config provider
│   │   └── DotEnvLoader.php        # Environment loader
│   ├── Console/                    # Console commands
│   │   └── Commands/               # Command implementations
│   │       └── BenchmarkCommand.php # Benchmark command
│   ├── Core/                       # Core components
│   │   ├── Application.php         # Main application class
│   │   ├── Bootstrap.php           # Application bootstrapper
│   │   └── ServiceProvider.php     # Service provider base class
│   ├── Error/                      # Error handling
│   │   ├── ErrorHandler.php        # Error handler
│   │   └── WhoopsIntegration.php   # Whoops integration
│   ├── EventLoop/                  # Event loop components
│   │   └── LoopFactory.php         # Event loop factory
│   ├── Http/                       # HTTP components
│   │   ├── Client/                 # HTTP client
│   │   │   └── Client.php          # PSR-18 HTTP client
│   │   ├── Middleware/             # HTTP middleware
│   │   │   ├── MiddlewareDispatcher.php # Middleware dispatcher
│   │   │   ├── MiddlewarePipeline.php # Middleware pipeline
│   │   │   └── Security/           # Security middleware
│   │   │       ├── CorsMiddleware.php # CORS middleware
│   │   │       ├── CsrfMiddleware.php # CSRF protection
│   │   │       ├── RateLimitingMiddleware.php # Rate limiting
│   │   │       └── SecurityMiddleware.php # Security headers
│   │   ├── Router/                 # Routing components
│   │   │   ├── Route.php           # Route class
│   │   │   ├── RouteCollection.php # Route collection
│   │   │   └── Router.php          # Router implementation
│   │   └── Server/                 # HTTP server
│   │       ├── Request.php         # PSR-7 request wrapper
│   │       ├── Response.php        # PSR-7 response wrapper
│   │       └── Server.php          # HTTP server implementation
│   ├── Logging/                    # Logging components
│   │   ├── AsyncLogger.php         # Async logger
│   │   ├── JsonFormatter.php       # JSON log formatter
│   │   ├── LineFormatter.php       # Text log formatter
│   │   └── LoggerFactory.php       # Logger factory
│   ├── MVC/                        # MVC components
│   │   ├── Controller/             # Controller components
│   │   │   ├── BaseController.php  # Base controller
│   │   │   └── ControllerInterface.php # Controller interface
│   │   ├── Model/                  # Model components
│   │   │   └── ModelInterface.php  # Model interface
│   │   └── View/                   # View components
│   │       ├── PhpView.php         # PHP template engine
│   │       ├── ViewFactory.php     # View factory
│   │       └── ViewInterface.php   # View interface
│   ├── Serialization/              # Serialization components
│   │   ├── JsonSerializer.php      # JSON serializer
│   │   ├── MessagePackSerializer.php # MessagePack serializer
│   │   └── SerializerInterface.php # Serializer interface
│   ├── Tracing/                    # Distributed tracing
│   │   └── OpenTelemetryFactory.php # OpenTelemetry factory
│   └── WebSocket/                  # WebSocket components
│       ├── WebSocketConnection.php # WebSocket connection
│       ├── WebSocketHandlerInterface.php # Handler interface
│       └── WebSocketServer.php     # WebSocket server
└── tests/                          # Test suite
    ├── Unit/                       # Unit tests
    ├── Integration/                # Integration tests
    ├── Performance/                # Performance tests
    └── Benchmark/                  # Benchmark tests
```

The framework library is designed to be installed as a Composer package.