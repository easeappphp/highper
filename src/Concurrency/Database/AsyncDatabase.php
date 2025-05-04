<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Database;

use Amp\Mysql\MysqlConnectionPool;
use Amp\Parallel\Worker\TaskWorkerPool;
use EaseAppPHP\HighPer\Framework\Concurrency\ParallelTask;
use EaseAppPHP\HighPer\Framework\Exceptions\DatabaseException;

/**
 * AsyncDatabase - Generic wrapper for non-blocking MySQL operations
 * 
 * Provides generic method wrappers for Amphp/MySQL that operate through
 * Amphp/Parallel to prevent blocking the event loop
 */
class AsyncDatabase
{
    private MysqlConnectionPool $connectionPool;
    private TaskWorkerPool $workerPool;
    
    /**
     * Constructor for AsyncDatabase
     *
     * @param MysqlConnectionPool $connectionPool The MySQL connection pool
     * @param TaskWorkerPool $workerPool The worker pool for parallel tasks
     */
    public function __construct(MysqlConnectionPool $connectionPool, TaskWorkerPool $workerPool)
    {
        $this->connectionPool = $connectionPool;
        $this->workerPool = $workerPool;
    }
    
    /**
     * Execute a query and return all results
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for the query
     * @return array The query results as an array of associative arrays
     * @throws DatabaseException If the query fails
     */
    public async function query(string $query, array $params = []): array
    {
        $task = new ParallelTask(
            $this->connectionPool,
            async function ($pool, $query, $params) {
                $connection = await $pool->getConnection();
                
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
                    throw new DatabaseException("Query error: {$e->getMessage()}", 0, $e);
                } finally {
                    $connection->close();
                }
            },
            [$query, $params]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Execute a query and return a single row
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for the query
     * @return array|null The first row as an associative array or null if no rows
     * @throws DatabaseException If the query fails
     */
    public async function queryOne(string $query, array $params = []): ?array
    {
        $task = new ParallelTask(
            $this->connectionPool,
            async function ($pool, $query, $params) {
                $connection = await $pool->getConnection();
                
                try {
                    if (empty($params)) {
                        $result = await $connection->query($query);
                    } else {
                        $statement = await $connection->prepare($query);
                        $result = await $statement->execute($params);
                    }
                    
                    return await $result->fetchRow();
                } catch (\Throwable $e) {
                    throw new DatabaseException("Query error: {$e->getMessage()}", 0, $e);
                } finally {
                    $connection->close();
                }
            },
            [$query, $params]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Execute a query and return a single column from first row
     *
     * @param string $query The SQL query to execute
     * @param array $params Optional parameters for the query
     * @param string|int $column The column name or index to return
     * @return mixed The value of the specified column or null if no rows
     * @throws DatabaseException If the query fails
     */
    public async function queryScalar(string $query, array $params = [], $column = 0): mixed
    {
        $task = new ParallelTask(
            $this->connectionPool,
            async function ($pool, $query, $params, $column) {
                $connection = await $pool->getConnection();
                
                try {
                    if (empty($params)) {
                        $result = await $connection->query($query);
                    } else {
                        $statement = await $connection->prepare($query);
                        $result = await $statement->execute($params);
                    }
                    
                    $row = await $result->fetchRow();
                    if (!$row) {
                        return null;
                    }
                    
                    if (is_string($column) && isset($row[$column])) {
                        return $row[$column];
                    }
                    
                    if (is_int($column)) {
                        $values = array_values($row);
                        return $values[$column] ?? null;
                    }
                    
                    return null;
                } catch (\Throwable $e) {
                    throw new DatabaseException("Query error: {$e->getMessage()}", 0, $e);
                } finally {
                    $connection->close();
                }
            },
            [$query, $params, $column]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Execute an INSERT query and return the last insert ID
     *
     * @param string $table The table to insert into
     * @param array $data Associative array of column => value pairs
     * @return int The ID of the newly inserted row
     * @throws DatabaseException If the query fails
     */
    public async function insert(string $table, array $data): int
    {
        $task = new ParallelTask(
            $this->connectionPool,
            async function ($pool, $table, $data) {
                $connection = await $pool->getConnection();
                
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
                    throw new DatabaseException("Insert error: {$e->getMessage()}", 0, $e);
                } finally {
                    $connection->close();
                }
            },
            [$table, $data]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Execute a batch INSERT query for multiple rows
     *
     * @param string $table The table to insert into
     * @param array $columns Array of column names
     * @param array $rows Array of rows, where each row is an array of values
     * @return int The number of affected rows
     * @throws DatabaseException If the query fails
     */
    public async function batchInsert(string $table, array $columns, array $rows): int
    {
        $task = new ParallelTask(
            $this->connectionPool,
            async function ($pool, $table, $columns, $rows) {
                $connection = await $pool->getConnection();
                
                try {
                    // Generate placeholders for each row
                    $rowPlaceholders = [];
                    $allValues = [];
                    
                    foreach ($rows as $row) {
                        $rowPlaceholders[] = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
                        $allValues = array_merge($allValues, array_values($row));
                    }
                    
                    $query = sprintf(
                        "INSERT INTO %s (%s) VALUES %s",
                        $table,
                        implode(', ', $columns),
                        implode(', ', $rowPlaceholders)
                    );
                    
                    $statement = await $connection->prepare($query);
                    $result = await $statement->execute($allValues);
                    
                    return $result->getAffectedRowCount();
                } catch (\Throwable $e) {
                    throw new DatabaseException("Batch insert error: {$e->getMessage()}", 0, $e);
                } finally {
                    $connection->close();
                }
            },
            [$table, $columns, $rows]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Execute an UPDATE query
     *
     * @param string $table The table to update
     * @param array $data Associative array of column => value pairs to update
     * @param string $condition The WHERE condition
     * @param array $params Parameters for the WHERE condition
     * @return int The number of affected rows
     * @throws DatabaseException If the query fails
     */
    public async function update(string $table, array $data, string $condition, array $params = []): int
    {
        $task = new ParallelTask(
            $this->connectionPool,
            async function ($pool, $table, $data, $condition, $params) {
                $connection = await $pool->getConnection();
                
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
                    throw new DatabaseException("Update error: {$e->getMessage()}", 0, $e);
                } finally {
                    $connection->close();
                }
            },
            [$table, $data, $condition, $params]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Execute a DELETE query
     *
     * @param string $table The table to delete from
     * @param string $condition The WHERE condition
     * @param array $params Parameters for the WHERE condition
     * @return int The number of affected rows
     * @throws DatabaseException If the query fails
     */
    public async function delete(string $table, string $condition, array $params = []): int
    {
        $task = new ParallelTask(
            $this->connectionPool,
            async function ($pool, $table, $condition, $params) {
                $connection = await $pool->getConnection();
                
                try {
                    $query = sprintf("DELETE FROM %s WHERE %s", $table, $condition);
                    
                    $statement = await $connection->prepare($query);
                    $result = await $statement->execute($params);
                    
                    return $result->getAffectedRowCount();
                } catch (\Throwable $e) {
                    throw new DatabaseException("Delete error: {$e->getMessage()}", 0, $e);
                } finally {
                    $connection->close();
                }
            },
            [$table, $condition, $params]
        );
        
        return await $this->workerPool->enqueue($task);
    }
    
    /**
     * Begin a transaction
     *
     * @return Transaction A transaction object that can be used for operations
     * @throws DatabaseException If starting the transaction fails
     */
    public async function beginTransaction(): Transaction
    {
        // Create a dedicated connection for the transaction
        $connection = await $this->connectionPool->getConnection();
        
        try {
            await $connection->query('START TRANSACTION');
            return new Transaction($connection, $this->workerPool);
        } catch (\Throwable $e) {
            $connection->close();
            throw new DatabaseException("Failed to start transaction: {$e->getMessage()}", 0, $e);
        }
    }
}