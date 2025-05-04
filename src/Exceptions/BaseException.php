<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Exceptions;

/**
 * BaseException - Base exception class for Highper framework
 *
 * This class integrates with the existing ErrorHandler and provides
 * a foundation for all Highper-specific exceptions.
 */
class BaseException extends \Exception
{
    /**
     * @var string A more user-friendly error message
     */
    protected string $userMessage;
    
    /**
     * @var array Additional context information about the exception
     */
    protected array $context = [];
    
    /**
     * @var int The HTTP status code for this exception
     */
    protected int $statusCode = 500;
    
    /**
     * @var bool Whether this exception should be logged
     */
    protected bool $shouldLog = true;
    
    /**
     * Create a new base exception
     *
     * @param string $message The technical exception message
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     * @param string|null $userMessage A user-friendly error message
     * @param array $context Additional context information
     * @param bool $shouldLog Whether this exception should be logged
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $userMessage = null,
        array $context = [],
        bool $shouldLog = true
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->userMessage = $userMessage ?? $message;
        $this->context = $context;
        $this->shouldLog = $shouldLog;
    }
    
    /**
     * Get the user-friendly error message
     *
     * @return string The user-friendly message
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
    
    /**
     * Set the user-friendly error message
     *
     * @param string $message The user-friendly message
     * @return $this
     */
    public function setUserMessage(string $message): self
    {
        $this->userMessage = $message;
        return $this;
    }
    
    /**
     * Get the additional context information
     *
     * @return array The context
     */
    public function getContext(): array
    {
        return $this->context;
    }
    
    /**
     * Add additional context information
     *
     * @param string $key The context key
     * @param mixed $value The context value
     * @return $this
     */
    public function addContext(string $key, $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }
    
    /**
     * Get the HTTP status code for this exception
     *
     * @return int The HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    /**
     * Set the HTTP status code for this exception
     *
     * @param int $statusCode The HTTP status code
     * @return $this
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }
    
    /**
     * Whether this exception should be logged
     *
     * @return bool True if this exception should be logged
     */
    public function shouldLog(): bool
    {
        return $this->shouldLog;
    }
    
    /**
     * Create a 404 Not Found exception
     *
     * @param string $message The exception message
     * @param string|null $userMessage The user-friendly message
     * @return static
     */
    public static function notFound(string $message, ?string $userMessage = null): static
    {
        return (new static(
            $message,
            404,
            null,
            $userMessage ?? 'The requested resource was not found'
        ))->setStatusCode(404);
    }
    
    /**
     * Create a 403 Forbidden exception
     */
    public static function forbidden(string $message, ?string $userMessage = null): static
    {
        return (new static(
            $message,
            403,
            null,
            $userMessage ?? 'You do not have permission to access this resource'
        ))->setStatusCode(403);
    }
    
    /**
     * Create a 401 Unauthorized exception
     */
    public static function unauthorized(string $message, ?string $userMessage = null): static
    {
        return (new static(
            $message,
            401,
            null,
            $userMessage ?? 'Authentication is required to access this resource'
        ))->setStatusCode(401);
    }
    
    /**
     * Create a 400 Bad Request exception
     */
    public static function badRequest(string $message, ?string $userMessage = null, array $context = []): static
    {
        return (new static(
            $message,
            400,
            null,
            $userMessage ?? 'The request could not be processed due to invalid input',
            $context
        ))->setStatusCode(400);
    }
    
    /**
     * Create a 500 Internal Server Error exception
     */
    public static function internal(string $message, ?\Throwable $previous = null, ?string $userMessage = null): static
    {
        return (new static(
            $message,
            500,
            $previous,
            $userMessage ?? 'An internal server error occurred. Please try again later.'
        ))->setStatusCode(500);
    }
}