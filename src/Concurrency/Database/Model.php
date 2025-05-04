<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\Database;

use EaseAppPHP\HighPer\Framework\Exceptions\DatabaseException;

/**
 * Base Model class for database operations
 */
abstract class Model
{
    /**
     * @var AsyncDatabase
     */
    protected static AsyncDatabase $db;
    
    /**
     * @var string The table name
     */
    protected static string $table = '';
    
    /**
     * @var string The primary key
     */
    protected static string $primaryKey = 'id';
    
    /**
     * @var array The model attributes
     */
    protected array $attributes = [];
    
    /**
     * @var array The original attributes
     */
    protected array $original = [];
    
    /**
     * @var array The dirty attributes
     */
    protected array $dirty = [];
    
    /**
     * Set the database instance
     *
     * @param AsyncDatabase $db The database instance
     */
    public static function setDatabase(AsyncDatabase $db): void
    {
        static::$db = $db;
    }
    
    /**
     * Constructor for Model
     *
     * @param array $attributes The model attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }
    
    /**
     * Fill the model with attributes
     *
     * @param array $attributes The attributes to fill
     * @return self
     */
    public function fill(array $attributes): self
    {
        $this->attributes = $attributes;
        $this->original = $attributes;
        $this->dirty = [];
        
        return $this;
    }
    
    /**
     * Get a query builder for this model
     *
     * @return QueryBuilder
     */
    public static function query(): QueryBuilder
    {
        return (new QueryBuilder(static::$db))->from(static::$table);
    }
    
    /**
     * Find a model by primary key
     *
     * @param mixed $id The primary key value
     * @return static|null The model or null if not found
     */
    public static async function find($id): ?static
    {
        $data = await static::$db->queryOne(
            "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?",
            [$id]
        );
        
        if (!$data) {
            return null;
        }
        
        return new static($data);
    }
    
    /**
     * Get all models
     *
     * @return array An array of models
     */
    public static async function all(): array
    {
        $data = await static::$db->query("SELECT * FROM " . static::$table);
        
        $models = [];
        foreach ($data as $item) {
            $models[] = new static($item);
        }
        
        return $models;
    }
    
    /**
     * Create a new model
     *
     * @param array $attributes The attributes to create
     * @return static The new model
     */
    public static async function create(array $attributes): static
    {
        $id = await static::$db->insert(static::$table, $attributes);
        
        $attributes[static::$primaryKey] = $id;
        return new static($attributes);
    }
    
    /**
     * Get an attribute
     *
     * @param string $key The attribute key
     * @return mixed The attribute value
     */
    public function __get(string $key)
    {
        return $this->attributes[$key] ?? null;
    }
    
    /**
     * Set an attribute
     *
     * @param string $key The attribute key
     * @param mixed $value The attribute value
     */
    public function __set(string $key, $value): void
    {
        if (!isset($this->attributes[$key]) || $this->attributes[$key] !== $value) {
            $this->dirty[$key] = true;
        }
        
        $this->attributes[$key] = $value;
    }
    
    /**
     * Check if an attribute exists
     *
     * @param string $key The attribute key
     * @return bool True if exists
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
    
    /**
     * Save the model
     *
     * @return bool True if saved
     * @throws DatabaseException If the save fails
     */
    public async function save(): bool
    {
        $pk = static::$primaryKey;
        
        if (isset($this->attributes[$pk])) {
            // Update
            $dirtyAttributes = array_intersect_key($this->attributes, $this->dirty);
            
            if (empty($dirtyAttributes)) {
                return true;
            }
            
            $affected = await static::$db->update(
                static::$table,
                $dirtyAttributes,
                $pk . " = ?",
                [$this->attributes[$pk]]
            );
            
            $this->original = $this->attributes;
            $this->dirty = [];
            
            return $affected > 0;
        } else {
            // Insert
            $id = await static::$db->insert(static::$table, $this->attributes);
            
            $this->attributes[$pk] = $id;
            $this->original = $this->attributes;
            $this->dirty = [];
            
            return true;
        }
    }
    
    /**
     * Delete the model
     *
     * @return bool True if deleted
     * @throws DatabaseException If the delete fails
     */
    public async function delete(): bool
    {
        $pk = static::$primaryKey;
        
        if (!isset($this->attributes[$pk])) {
            return false;
        }
        
        $affected = await static::$db->delete(
            static::$table,
            $pk . " = ?",
            [$this->attributes[$pk]]
        );
        
        return $affected > 0;
    }
    
    /**
     * Get the model attributes
     *
     * @return array The attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
    
    /**
     * Get the dirty attributes
     *
     * @return array The dirty attributes
     */
    public function getDirty(): array
    {
        return array_intersect_key($this->attributes, $this->dirty);
    }
    
    /**
     * Check if the model is dirty
     *
     * @return bool True if dirty
     */
    public function isDirty(): bool
    {
        return !empty($this->dirty);
    }
}