<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\MVC\Model;

interface ModelInterface
{
    /**
     * Find a model by its primary key
     *
     * @param mixed $id
     * @return mixed
     */
    public function find(mixed $id): mixed;

    /**
     * Get all models
     *
     * @param array $criteria
     * @return array
     */
    public function all(array $criteria = []): array;

    /**
     * Create a new model
     *
     * @param array $data
     * @return mixed
     */
    public function create(array $data): mixed;

    /**
     * Update a model
     *
     * @param mixed $id
     * @param array $data
     * @return bool
     */
    public function update(mixed $id, array $data): bool;

    /**
     * Delete a model
     *
     * @param mixed $id
     * @return bool
     */
    public function delete(mixed $id): bool;
    
    /**
     * Get the table name
     *
     * @return string
     */
    public function getTable(): string;
    
    /**
     * Get the primary key
     *
     * @return string
     */
    public function getPrimaryKey(): string;
    
    /**
     * Get the fillable attributes
     *
     * @return array
     */
    public function getFillable(): array;
    
    /**
     * Get the attributes that should be cast
     *
     * @return array
     */
    public function getCasts(): array;
}