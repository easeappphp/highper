<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Database;

/**
 * Query Builder interface for building SQL queries
 */
interface QueryBuilderInterface
{
    /**
     * Select columns from a table
     *
     * @param string|array $columns The columns to select
     * @return self
     */
    public function select($columns): self;
    
    /**
     * From a specific table
     *
     * @param string $table The table name
     * @return self
     */
    public function from(string $table): self;
    
    /**
     * Add a WHERE condition
     *
     * @param string $condition The condition
     * @param array $params Parameters for the condition
     * @return self
     */
    public function where(string $condition, array $params = []): self;
    
    /**
     * Add an ORDER BY clause
     *
     * @param string $column The column to order by
     * @param string $direction The order direction (ASC or DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self;
    
    /**
     * Add a LIMIT clause
     *
     * @param int $limit The limit
     * @return self
     */
    public function limit(int $limit): self;
    
    /**
     * Add an OFFSET clause
     *
     * @param int $offset The offset
     * @return self
     */
    public function offset(int $offset): self;
    
    /**
     * Execute the query and get all results
     *
     * @return array The query results
     */
    public function get(): array;
    
    /**
     * Execute the query and get the first result
     *
     * @return array|null The first result or null
     */
    public function first(): ?array;
    
    /**
     * Execute the query and get a single column value
     *
     * @param string|int $column The column name or index
     * @return mixed The column value
     */
    public function value($column): mixed;
    
    /**
     * Count the number of rows
     *
     * @return int The count
     */
    public function count(): int;
}