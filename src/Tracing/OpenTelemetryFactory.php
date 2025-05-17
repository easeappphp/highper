<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Tracing;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;
use Throwable;

/**
 * Factory for creating OpenTelemetry tracers.
 */
class OpenTelemetryFactory
{
    /**
     * Create a tracer based on configuration.
     *
     * @param array $config OpenTelemetry configuration array
     * @return TracerInterface The configured tracer
     */
    public static function create(array $config): TracerInterface
    {
        // If tracing is not enabled, return a no-op tracer
        if (!isset($config['enabled']) || $config['enabled'] !== true) {
            return self::createNoOpTracer();
        }

        try {
            // Create the tracer provider
            $tracerProvider = self::createTracerProvider($config);
            
            // Get a tracer from the provider
            $tracer = $tracerProvider->getTracer(
                $config['name'] ?? 'highper-framework',
                $config['version'] ?? '1.0.0'
            );
            
            return $tracer;
        } catch (Throwable $e) {
            // If anything goes wrong, return a no-op tracer
            error_log('OpenTelemetry initialization failed: ' . $e->getMessage());
            return self::createNoOpTracer();
        }
    }

    /**
     * Create a tracer provider based on configuration.
     *
     * @param array $config OpenTelemetry configuration array
     * @return TracerProviderInterface The configured tracer provider
     */
    private static function createTracerProvider(array $config): TracerProviderInterface
    {
        // Create resource info with service details
        $resourceInfo = ResourceInfoFactory::merge(
            ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => $config['service_name'] ?? 'highper-service',
                ResourceAttributes::SERVICE_VERSION => $config['service_version'] ?? '1.0.0',
                ResourceAttributes::DEPLOYMENT_ENVIRONMENT => $config['environment'] ?? 'production',
            ])),
            ResourceInfoFactory::defaultResource()
        );

        // Set up the exporter
        $endpoint = $config['endpoint'] ?? 'http://localhost:4318/v1/traces';
        $transport = (new OtlpHttpTransportFactory())->create($endpoint, 'application/json');
        $exporter = new SpanExporter($transport);

        // Create a span processor
        $spanProcessor = new BatchSpanProcessor(
            $exporter,
            ClockFactory::getDefault(),
            $config['batch_size'] ?? 512,
            $config['scheduled_delay'] ?? 5000,
            $config['export_timeout'] ?? 30000,
            $config['max_queue_size'] ?? 2048
        );

        // Create and configure the tracer provider
        $tracerProvider = new TracerProvider(
            [$spanProcessor],
            new AlwaysOnSampler(),
            $resourceInfo
        );

        return $tracerProvider;
    }

    /**
     * Create a no-op tracer that performs no operations.
     *
     * @return TracerInterface
     */
    public static function createNoOpTracer(): TracerInterface
    {
        // Create a no-op implementation of TracerInterface
        return new class implements TracerInterface {
            /**
             * {@inheritdoc}
             */
            public function spanBuilder(string $spanName): SpanBuilderInterface
            {
                return new class($spanName) implements SpanBuilderInterface {
                    private string $name;

                    public function __construct(string $name)
                    {
                        $this->name = $name;
                    }

                    public function setParent($parentContext): SpanBuilderInterface
                    {
                        return $this;
                    }

                    public function setNoParent(): SpanBuilderInterface
                    {
                        return $this;
                    }

                    public function addLink($context, array $attributes = []): SpanBuilderInterface
                    {
                        return $this;
                    }

                    public function setAttribute(string $key, $value): SpanBuilderInterface
                    {
                        return $this;
                    }

                    public function setAttributes(array $attributes): SpanBuilderInterface
                    {
                        return $this;
                    }

                    public function setStartTimestamp(int $timestampNanos): SpanBuilderInterface
                    {
                        return $this;
                    }

                    public function setSpanKind(int $spanKind): SpanBuilderInterface
                    {
                        return $this;
                    }

                    public function startSpan(): SpanInterface
                    {
                        return new class implements SpanInterface {
                            public function getContext()
                            {
                                return null;
                            }

                            public function isRecording(): bool
                            {
                                return false;
                            }

                            public function setAttribute(string $key, $value): SpanInterface
                            {
                                return $this;
                            }

                            public function setAttributes(array $attributes): SpanInterface
                            {
                                return $this;
                            }

                            public function addEvent(string $name, array $attributes = [], ?int $timestamp = null): SpanInterface
                            {
                                return $this;
                            }

                            public function recordException(Throwable $exception, array $attributes = []): SpanInterface
                            {
                                return $this;
                            }

                            public function updateName(string $name): SpanInterface
                            {
                                return $this;
                            }

                            public function setStatus(string $code, string $description = null): SpanInterface
                            {
                                return $this;
                            }

                            public function end(?int $endEpochNanos = null): void
                            {
                                // No-op
                            }
                        };
                    }
                };
            }

            /**
             * {@inheritdoc}
             */
            public function isEnabled(): bool
            {
                return false;
            }
        };
    }
}