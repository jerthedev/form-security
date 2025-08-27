<?php

declare(strict_types=1);

/**
 * Contract File: ModelInterface.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Base contract interface for all JTD-FormSecurity models defining
 * common functionality, query scopes, and business logic methods.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Contracts;

/**
 * ModelInterface Contract
 *
 * Defines the common interface that all JTD-FormSecurity models should implement.
 * This ensures consistency across models and provides a standard set of methods
 * for analytics, caching, and business logic operations.
 */
interface ModelInterface
{
    /**
     * Get the model's table name
     */
    public function getTable();

    /**
     * Get the model's primary key name
     */
    public function getKeyName();

    /**
     * Get the model's primary key value
     */
    public function getKey();

    /**
     * Get the model's fillable attributes
     *
     * @return array<string>
     */
    public function getFillable();

    /**
     * Get the model's cast attributes
     *
     * @return array<string, string>
     */
    public function getCasts();

    /**
     * Get the model's hidden attributes
     *
     * @return array<string>
     */
    public function getHidden();

    /**
     * Convert the model to an array
     *
     * @return array<string, mixed>
     */
    public function toArray();

    /**
     * Convert the model to JSON
     */
    public function toJson($options = 0);

    /**
     * Get the model's created_at timestamp
     */
    public function getCreatedAt();

    /**
     * Get the model's updated_at timestamp
     */
    public function getUpdatedAt();

    /**
     * Determine if the model has been modified since it was last saved
     */
    public function isDirty();

    /**
     * Save the model to the database
     */
    public function save(array $options = []);

    /**
     * Update the model in the database
     */
    public function update(array $attributes = [], array $options = []);

    /**
     * Delete the model from the database
     */
    public function delete();

    /**
     * Refresh the model from the database
     */
    public function refresh();
}
