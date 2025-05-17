<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Tracing;

use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use Throwable;

/**
 * Implementation of the SpanBuilder interface.
 */
class SpanBuilder implements SpanBuilderInterface
{
    private string $name;
    private $parent = null;
    private bool $noParent = false;
    private array $links = [];
    private array $attributes = [];
    private ?int $startTime = null;
    private int $spanKind = 0; // Default: INTERNAL

    /**
     * Constructor.
     *
     * @param string $name The span name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function setParent($parentContext): SpanBuilderInterface
    {
        $this->parent = $parentContext;
        $this->noParent = false;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setNoParent(): SpanBuilderInterface
    {
        $this->noParent = true;
        $this->parent = null;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addLink($context, array $attributes = []): SpanBuilderInterface
    {
        $this->links[] = [
            'context' => $context,
            'attributes' => $attributes,
        ];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute(string $key, $value): SpanBuilderInterface
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttributes(array $attributes): SpanBuilderInterface
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setStartTimestamp(int $timestampNanos): SpanBuilderInterface
    {
        $this->startTime = $timestampNanos;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setSpanKind(int $spanKind): SpanBuilderInterface
    {
        $this->spanKind = $spanKind;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function startSpan(): SpanInterface
    {
        // In a real implementation, this would create an actual span
        // For compatibility with the framework, we return a no-op span here
        return new NoopSpan($this->name);
    }
}

/**
 * A no-op implementation of SpanInterface.
 */
class NoopSpan implements SpanInterface
{
    private string $name;

    /**
     * Constructor.
     *
     * @param string $name The span name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function isRecording(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute(string $key, $value): SpanInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttributes(array $attributes): SpanInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addEvent(string $name, array $attributes = [], ?int $timestamp = null): SpanInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function recordException(Throwable $exception, array $attributes = []): SpanInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function updateName(string $name): SpanInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(string $code, string $description = null): SpanInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function end(?int $endEpochNanos = null): void
    {
        // No-op
    }
}