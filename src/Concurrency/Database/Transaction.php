<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Database;

use Amp\Mysql\MysqlConnection;
use Amp\Parallel\Worker\TaskWorkerPool;
use EaseAppPHP\HighPer\Framework\Concurrency\ParallelTask;
use EaseAppPHP\HighPer\Framework\Exceptions\DatabaseException;

/**
 * Transaction - Handles database transactions in a non-blocking way
 */
class Transaction
{
    private MysqlConnection $connection;
    private TaskWorkerPool $workerPool;
    private bool $active = true;
    
    /**
     * Constructor for Transaction
     *
     * @param MysqlConnection $connection The MySQL connection
     * @param TaskWorkerPool $workerPool The worker pool for parallel tasks
     */
    public function __construct(MysqlConnection $connection, TaskWorkerPool $workerPool)
    {
        $this->connection = $connection;
        $this->workerPool = $workerPool;
    }
    
    /**
     * Execute a query within the transaction and return all results
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for the query
     * @return array The query results as an array of associative arrays
     * @throws DatabaseException If the query fails or transaction is not active
     */
    public async function query(string $query, array $params = []): array
    {
        $this->ensureActive();
        
        $task = new ParallelTask(
            $this->connection,
            async function ($connection, $query, $params) {
                try {
                    if (empty($params)) {
                        $result = await $connection->query($query);
                    } else {
                        $statement = await $connection->prepare($query);
                        $result = await $statement->execute($params);
                    }
                    
                    $rows = [];
                    while ($row = await $result->fetchRow()) {
                        $rows[] = $row;
                    }
                    
                    return $rows;
                } catch (\Throwable $e) {
                    throw new DatabaseException("Transaction query error: {$e->getMessage()}", 0, $e);
                }
            },
            [$query, $params]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Execute a query within the transaction and return a single row
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for the query
     * @return array|null The first row as an associative array or null if no rows
     * @throws DatabaseException If the query fails or transaction is not active
     */
    public async function queryOne(string $query, array $params = []): ?array
    {
        $this->ensureActive();
        
        $task = new ParallelTask(
            $this->connection,
            async function ($connection, $query, $params) {
                try {
                    if (empty($params)) {
                        $result = await $connection->query($query);
                    } else {
                        $statement = await $connection->prepare($query);
                        $result = await $statement->execute($params);
                    }
                    
                    return await $result->fetchRow();
                } catch (\Throwable $e) {
                    throw new DatabaseException("Transaction query error: {$e->getMessage()}", 0, $e);
                }
            },
            [$query, $params]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Execute an INSERT query within the transaction
     *
     * @param string $table The table to insert into
     * @param array $data Associative array of column => value pairs
     * @return int The ID of the newly inserted row
     * @throws DatabaseException If the query fails or transaction is not active
     */
    public async function insert(string $table, array $data): int
    {
        $this->ensureActive();
        
        $task = new ParallelTask(
            $this->connection,
            async function ($connection, $table, $data) {
                try {
                    $columns = array_keys($data);
                    $placeholders = array_fill(0, count($columns), '?');
                    
                    $query = sprintf(
                        "INSERT INTO %s (%s) VALUES (%s)",
                        $table,
                        implode(', ', $columns),
                        implode(', ', $placeholders)
                    );
                    
                    $statement = await $connection->prepare($query);
                    $result = await $statement->execute(array_values($data));
                    
                    return $result->getLastInsertId();
                } catch (\Throwable $e) {
                    throw new DatabaseException("Transaction insert error: {$e->getMessage()}", 0, $e);
                }
            },
            [$table, $data]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Execute an UPDATE query within the transaction
     *
     * @param string $table The table to update
     * @param array $data Associative array of column => value pairs to update
     * @param string $condition The WHERE condition
     * @param array $params Parameters for the WHERE condition
     * @return int The number of affected rows
     * @throws DatabaseException If the query fails or transaction is not active
     */
    public async function update(string $table, array $data, string $condition, array $params = []): int
    {
        $this->ensureActive();
        
        $task = new ParallelTask(
            $this->connection,
            async function ($connection, $table, $data, $condition, $params) {
                try {
                    $setClauses = [];
                    $values = [];
                    
                    foreach ($data as $column => $value) {
                        $setClauses[] = "$column = ?";
                        $values[] = $value;
                    }
                    
                    $query = sprintf(
                        "UPDATE %s SET %s WHERE %s",
                        $table,
                        implode(', ', $setClauses),
                        $condition
                    );
                    
                    // Combine data values with condition params
                    $allParams = array_merge($values, $params);
                    
                    $statement = await $connection->prepare($query);
                    $result = await $statement->execute($allParams);
                    
                    return $result->getAffectedRowCount();
                } catch (\Throwable $e) {
                    throw new DatabaseException("Transaction update error: {$e->getMessage()}", 0, $e);
                }
            },
            [$table, $data, $condition, $params]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Execute a DELETE query within the transaction
     *
     * @param string $table The table to delete from
     * @param string $condition The WHERE condition
     * @param array $params Parameters for the WHERE condition
     * @return int The number of affected rows
     * @throws DatabaseException If the query fails or transaction is not active
     */
    public async function delete(string $table, string $condition, array $params = []): int
    {
        $this->ensureActive();
        
        $task = new ParallelTask(
            $this->connection,
            async function ($connection, $table, $condition, $params) {
                try {
                    $query = sprintf("DELETE FROM %s WHERE %s", $table, $condition);
                    
                    $statement = await $connection->prepare($query);
                    $result = await $statement->execute($params);
                    
                    return $result->getAffectedRowCount();
                } catch (\Throwable $e) {
                    throw new DatabaseException("Transaction delete error: {$e->getMessage()}", 0, $e);
                }
            },
            [$table, $condition, $params]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Commit the transaction
     *
     * @throws DatabaseException If committing fails
     */
    public async function commit(): void
    {
        $this->ensureActive();
        
        try {
            await $this->connection->query('COMMIT');
            $this->active = false;
        } catch (\Throwable $e) {
            throw new DatabaseException("Failed to commit transaction: {$e->getMessage()}", 0, $e);
        } finally {
            $this->connection->close();
        }
    }
    
    /**
     * Rollback the transaction
     *
     * @throws DatabaseException If rollback fails
     */
    public async function rollback(): void
    {
        $this->ensureActive();
        
        try {
            await $this->connection->query('ROLLBACK');
            $this->active = false;
        } catch (\Throwable $e) {
            throw new DatabaseException("Failed to rollback transaction: {$e->getMessage()}", 0, $e);
        } finally {
            $this->connection->close();
        }
    }
    
    /**
     * Check if the transaction is still active
     *
     * @return bool True if the transaction is active
     */
    public function isActive(): bool
    {
        return $this->active;
    }
    
    /**
     * Ensure the transaction is active before executing operations
     *
     * @throws DatabaseException If the transaction is not active
     */
    private function ensureActive(): void
    {
        if (!$this->active) {
            throw new DatabaseException("Transaction is not active. It has been committed or rolled back.");
        }
    }
    
    /**
     * Automatically rollback the transaction if it's still active when the object is destroyed
     */
    public function __destruct()
    {
        if ($this->active) {
            try {
                // Cannot use await in __destruct, so we use synchronous methods
                $this->connection->query('ROLLBACK');
                $this->connection->close();
            } catch (\Throwable $e) {
                // Cannot throw in __destruct, just close the connection
                $this->connection->close();
            }
        }
    }
}