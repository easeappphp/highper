<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Exceptions;

/**
 * DatabaseException - Exception for database-related errors
 *
 * This exception is thrown when database operations fail.
 */
class DatabaseException extends BaseException
{
    /**
     * @var string|null The SQL query that caused the error
     */
    protected ?string $query = null;
    
    /**
     * @var array|null The parameters used in the query
     */
    protected ?array $params = null;
    
    /**
     * @var int|null The error code from the database driver
     */
    protected ?int $driverCode = null;
    
    /**
     * @var string|null The error state from the database driver
     */
    protected ?string $sqlState = null;
    
    /**
     * Create a new database exception
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     * @param string|null $query The SQL query that caused the error
     * @param array|null $params The parameters used in the query
     * @param int|null $driverCode The error code from the database driver
     * @param string|null $sqlState The SQL state code
     * @param string|null $userMessage A user-friendly error message
     * @param array $context Additional context information
     * @param bool $shouldLog Whether this exception should be logged
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $query = null,
        ?array $params = null,
        ?int $driverCode = null,
        ?string $sqlState = null,
        ?string $userMessage = null,
        array $context = [],
        bool $shouldLog = true
    ) {
        parent::__construct($message, $code, $previous, $userMessage, $context, $shouldLog);
        
        $this->query = $query;
        $this->params = $params;
        $this->driverCode = $driverCode;
        $this->sqlState = $sqlState;
        $this->statusCode = 500; // Default to 500 for database errors
        
        // Add database error info to context for logging
        if ($query !== null) {
            $this->context['query'] = $query;
        }
        
        if ($params !== null) {
            $this->context['params'] = $params;
        }
        
        if ($driverCode !== null) {
            $this->context['driverCode'] = $driverCode;
        }
        
        if ($sqlState !== null) {
            $this->context['sqlState'] = $sqlState;
        }
    }
    
    /**
     * Get the SQL query that caused the error
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }
    
    /**
     * Get the parameters used in the query
     */
    public function getParams(): ?array
    {
        return $this->params;
    }
    
    /**
     * Get the error code from the database driver
     */
    public function getDriverCode(): ?int
    {
        return $this->driverCode;
    }
    
    /**
     * Get the SQL state code
     */
    public function getSqlState(): ?string
    {
        return $this->sqlState;
    }
    
    /**
     * Create exception from a MySQL error
     */
    public static function fromMysqlError(
        \Throwable $error, 
        ?string $query = null, 
        ?array $params = null
    ): static {
        // Extract MySQL error information if possible
        $driverCode = null;
        $sqlState = null;
        
        // Handle Amphp MySQL exceptions
        if (method_exists($error, 'getCode')) {
            $driverCode = $error->getCode();
        }
        
        if (method_exists($error, 'getSqlState')) {
            $sqlState = $error->getSqlState();
        }
        
        return new static(
            $error->getMessage(),
            0,
            $error,
            $query,
            $params,
            $driverCode,
            $sqlState
        );
    }
    
    /**
     * Check if the exception is due to a connection error
     */
    public function isConnectionError(): bool
    {
        // Common MySQL connection error codes
        $connectionErrorCodes = [
            1042, // Unable to connect to database server
            1043, // Bad handshake
            1044, // Access denied for user to database
            1045, // Access denied for user using password
            1046, // No database selected
            2002, // Connection refused
            2003, // Can't connect to MySQL server
            2005, // Unknown MySQL server host
            2006, // MySQL server has gone away
            2013  // Lost connection during query
        ];
        
        return $this->driverCode !== null && in_array($this->driverCode, $connectionErrorCodes);
    }
    
    /**
     * Check if the exception is due to a constraint violation
     */
    public function isConstraintViolation(): bool
    {
        // Common MySQL constraint violation error codes
        $constraintViolationCodes = [
            1062, // Duplicate entry for key
            1216, // Foreign key constraint fails (child)
            1217, // Foreign key constraint fails (parent)
            1451, // Cannot delete or update a parent row (FK constraint)
            1452, // Cannot add or update a child row (FK constraint)
            1557, // Foreign key constraint for fails
            3819  // CHECK constraint violation
        ];
        
        return $this->driverCode !== null && in_array($this->driverCode, $constraintViolationCodes);
    }
    
    /**
     * Create a database connection exception
     */
    public static function connectionError(string $message, ?\Throwable $previous = null): static
    {
        return new static(
            "Database connection error: $message",
            0,
            $previous,
            null,
            null,
            null,
            null,
            "Failed to connect to the database. Please try again later."
        );
    }
    
    /**
     * Create a database query preparation exception
     */
    public static function queryPreparationError(
        string $message, 
        ?string $query = null, 
        ?\Throwable $previous = null
    ): static {
        return new static(
            "Query preparation error: $message", 
            0, 
            $previous,
            $query,
            null,
            null,
            null,
            "Failed to prepare database query. Please check your query syntax."
        );
    }
    
    /**
     * Create a database query execution exception
     */
    public static function queryExecutionError(
        string $message, 
        ?string $query = null, 
        ?array $params = null, 
        ?\Throwable $previous = null
    ): static {
        return new static(
            "Query execution error: $message", 
            0, 
            $previous,
            $query,
            $params,
            null,
            null,
            "Failed to execute database query. Please try again later."
        );
    }
    
    /**
     * Create a database transaction exception
     */
    public static function transactionError(string $message, ?\Throwable $previous = null): static
    {
        return new static(
            "Transaction error: $message", 
            0, 
            $previous,
            null,
            null,
            null,
            null,
            "A database transaction error occurred. Please try again later."
        );
    }
}