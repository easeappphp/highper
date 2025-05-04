<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Tracing;

use EaseAppPHP\HighPer\Framework\Config\ConfigProvider;
use OpenTelemetry\API\Common\Instrumentation\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Psr\Container\ContainerInterface;

class OpenTelemetryFactory
{
    /**
     * @var ContainerInterface The container
     */
    protected ContainerInterface $container;
    
    /**
     * @var ConfigProvider The config provider
     */
    protected ConfigProvider $config;

    /**
     * Create a new OpenTelemetry factory
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get(ConfigProvider::class);
    }

    /**
     * Create a new tracer
     *
     * @return TracerInterface
     */
    public function create(): TracerInterface
    {
        // Check if tracing is enabled
        if (!$this->config->get('tracing.enabled', false)) {
            return $this->createNoOpTracer();
        }
        
        // Create a resource
        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfoFactory::defaultResource()
        );
        
        // Create the OTLP exporter
        $transport = (new OtlpHttpTransportFactory())->create(
            $this->config->get('tracing.endpoint', 'http://localhost:4318/v1/traces')
        );
        
        $exporter = new SpanExporter($transport);
        
        // Create the span processor
        $spanProcessor = new BatchSpanProcessor(
            $exporter,
            $this->config->get('tracing.batch_size', 512),
            $this->config->get('tracing.export_timeout', 30000)
        );
        
        // Create the tracer provider
        $tracerProvider = new TracerProvider(
            [$spanProcessor],
            new AlwaysOnSampler(),
            $resource,
            Attributes::create([])
        );
        
        // Set the global tracer provider
        Globals::setTracerProvider($tracerProvider);
        
        // Create and return the tracer
        return $tracerProvider->getTracer(
            $this->config->get('app.name', 'Highper'),
            $this->config->get('app.version', '1.0.0')
        );
    }

    /**
     * Create a no-op tracer
     *
     * @return TracerInterface
     */
    protected function createNoOpTracer(): TracerInterface
    {
        return new class implements TracerInterface {
            public function spanBuilder(string $name): SpanBuilder
            {
                return new class implements SpanBuilder {
                    public function setParent($context): SpanBuilder
                    {
                        return $this;
                    }
                    
                    public function setNoParent(): SpanBuilder
                    {
                        return $this;
                    }
                    
                    public function setSpanKind(int $spanKind): SpanBuilder
                    {
                        return $this;
                    }
                    
                    public function setAttributes(array $attributes): SpanBuilder
                    {
                        return $this;
                    }
                    
                    public function addAttributes(iterable $attributes): SpanBuilder
                    {
                        return $this;
                    }
                    
                    public function setAttribute(string $key, $value): SpanBuilder
                    {
                        return $this;
                    }
                    
                    public function setStartTimestamp(int $timestamp): SpanBuilder
                    {
                        return $this;
                    }
                    
                    public function startSpan(): Span
                    {
                        return new class implements Span {
                            public function end(int $endTime = null): void
                            {
                            }
                            
                            public function isRecording(): bool
                            {
                                return false;
                            }
                            
                            public function setAttribute(string $key, $value): Span
                            {
                                return $this;
                            }
                            
                            public function setAttributes(iterable $attributes): Span
                            {
                                return $this;
                            }
                            
                            public function addEvent(string $name, iterable $attributes = [], int $timestamp = null): Span
                            {
                                return $this;
                            }
                            
                            public function recordException(\Throwable $exception, iterable $attributes = [], int $timestamp = null): Span
                            {
                                return $this;
                            }
                            
                            public function updateName(string $name): Span
                            {
                                return $this;
                            }
                            
                            public function setStatus(string $code, string $description = null): Span
                            {
                                return $this;
                            }
                            
                            public function spanContext(): SpanContext
                            {
                                return new class implements SpanContext {
                                    public function getTraceId(): string
                                    {
                                        return '';
                                    }
                                    
                                    public function getSpanId(): string
                                    {
                                        return '';
                                    }
                                    
                                    public function getTraceFlags(): int
                                    {
                                        return 0;
                                    }
                                    
                                    public function getTraceState(): TraceState
                                    {
                                        return new class implements TraceState {
                                            public function get(string $key): ?string
                                            {
                                                return null;
                                            }
                                            
                                            public function with(string $key, string $value): TraceState
                                            {
                                                return $this;
                                            }
                                            
                                            public function without(string $key): TraceState
                                            {
                                                return $this;
                                            }
                                            
                                            public function isEmpty(): bool
                                            {
                                                return true;
                                            }
                                        };
                                    }
                                    
                                    public function isValid(): bool
                                    {
                                        return false;
                                    }
                                    
                                    public function isRemote(): bool
                                    {
                                        return false;
                                    }
                                };
                            }
                        };
                    }
                };
            }
        };
    }
}
