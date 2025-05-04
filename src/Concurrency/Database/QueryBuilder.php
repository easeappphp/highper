<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Database;

/**
 * Query Builder for building SQL queries
 */
class QueryBuilder implements QueryBuilderInterface
{
    private AsyncDatabase $db;
    private string $table = '';
    private array $columns = ['*'];
    private array $whereConditions = [];
    private array $whereParams = [];
    private array $orderByClauses = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    
    /**
     * Constructor for QueryBuilder
     *
     * @param AsyncDatabase $db The AsyncDatabase instance
     */
    public function __construct(AsyncDatabase $db)
    {
        $this->db = $db;
    }
    
    /**
     * {@inheritdoc}
     */
    public function select($columns): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function where(string $condition, array $params = []): self
    {
        $this->whereConditions[] = $condition;
        $this->whereParams = array_merge($this->whereParams, $params);
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderByClauses[] = "$column $direction";
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public async function get(): array
    {
        $query = $this->buildQuery();
        return await $this->db->query($query, $this->whereParams);
    }
    
    /**
     * {@inheritdoc}
     */
    public async function first(): ?array
    {
        $this->limit(1);
        $query = $this->buildQuery();
        return await $this->db->queryOne($query, $this->whereParams);
    }
    
    /**
     * {@inheritdoc}
     */
    public async function value($column): mixed
    {
        $this->limit(1);
        $query = $this->buildQuery();
        return await $this->db->queryScalar($query, $this->whereParams, $column);
    }
    
    /**
     * {@inheritdoc}
     */
    public async function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) as count'];
        
        $query = $this->buildQuery();
        $result = await $this->db->queryScalar($query, $this->whereParams, 'count');
        
        $this->columns = $originalColumns;
        return (int) $result;
    }
    
    /**
     * Build the query
     *
     * @return string The SQL query
     */
    private function buildQuery(): string
    {
        if (empty($this->table)) {
            throw new \InvalidArgumentException("No table specified for query");
        }
        
        $columnsStr = implode(', ', $this->columns);
        $query = "SELECT $columnsStr FROM {$this->table}";
        
        if (!empty($this->whereConditions)) {
            $whereStr = implode(' AND ', $this->whereConditions);
            $query .= " WHERE $whereStr";
        }
        
        if (!empty($this->orderByClauses)) {
            $query .= " ORDER BY " . implode(', ', $this->orderByClauses);
        }
        
        if ($this->limitValue !== null) {
            $query .= " LIMIT {$this->limitValue}";
        }
        
        if ($this->offsetValue !== null) {
            $query .= " OFFSET {$this->offsetValue}";
        }
        
        return $query;
    }
}