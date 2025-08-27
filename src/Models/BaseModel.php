<?php

declare(strict_types=1);

/**
 * Model File: BaseModel.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Abstract base model class for all JTD-FormSecurity models providing
 * common functionality, PHP 8.2+ features, and standardized behavior.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JTD\FormSecurity\Contracts\ModelInterface;
use JTD\FormSecurity\Traits\PerformanceOptimized;

/**
 * BaseModel Abstract Class
 *
 * Provides common functionality for all JTD-FormSecurity models including
 * standardized casting, PHP 8.2+ features, and consistent behavior patterns.
 * All package models should extend this base class.
 */
abstract class BaseModel extends Model implements ModelInterface
{
    use HasFactory, PerformanceOptimized;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The storage format of the model's date columns.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * This should be overridden in child classes to specify
     * the actual fillable attributes for each model.
     */
    protected $fillable = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * This should be overridden in child classes to specify
     * sensitive attributes that should not be exposed.
     */
    protected $hidden = [];

    /**
     * Boot the model and apply global configurations
     */
    protected static function boot(): void
    {
        parent::boot();

        // Add any global model event listeners here
        static::creating(function (self $model) {
            // Ensure timestamps are set if not already provided
            if (is_null($model->created_at)) {
                $model->created_at = now();
            }
            if (is_null($model->updated_at)) {
                $model->updated_at = now();
            }
        });

        static::updating(function (self $model) {
            // Always update the updated_at timestamp
            $model->updated_at = now();
        });
    }

    /**
     * Get the model's created_at timestamp
     */
    public function getCreatedAt(): ?Carbon
    {
        return $this->created_at;
    }

    /**
     * Get the model's updated_at timestamp
     */
    public function getUpdatedAt(): ?Carbon
    {
        return $this->updated_at;
    }

    /**
     * Get a formatted version of the created_at timestamp
     */
    public function getFormattedCreatedAt(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->created_at?->format($format) ?? '';
    }

    /**
     * Get a formatted version of the updated_at timestamp
     */
    public function getFormattedUpdatedAt(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->updated_at?->format($format) ?? '';
    }

    /**
     * Get a human-readable time difference for created_at
     */
    public function getCreatedAtDiffForHumans(): string
    {
        return $this->created_at?->diffForHumans() ?? '';
    }

    /**
     * Get a human-readable time difference for updated_at
     */
    public function getUpdatedAtDiffForHumans(): string
    {
        return $this->updated_at?->diffForHumans() ?? '';
    }

    /**
     * Check if the model was created recently (within specified hours)
     */
    public function isRecentlyCreated(int $hours = 24): bool
    {
        if (! $this->created_at) {
            return false;
        }

        return $this->created_at->isAfter(now()->subHours($hours));
    }

    /**
     * Check if the model was updated recently (within specified hours)
     */
    public function isRecentlyUpdated(int $hours = 24): bool
    {
        if (! $this->updated_at) {
            return false;
        }

        return $this->updated_at->isAfter(now()->subHours($hours));
    }

    /**
     * Get the age of the model in days
     */
    public function getAgeInDays(): int
    {
        if (! $this->created_at) {
            return 0;
        }

        return (int) $this->created_at->diffInDays(now());
    }

    /**
     * Get the age of the model in hours
     */
    public function getAgeInHours(): int
    {
        if (! $this->created_at) {
            return 0;
        }

        return (int) $this->created_at->diffInHours(now());
    }

    /**
     * Convert the model to an array with formatted timestamps
     *
     * @return array<string, mixed>
     */
    public function toFormattedArray(): array
    {
        $array = $this->toArray();

        if (isset($array['created_at'])) {
            $array['created_at_formatted'] = $this->getFormattedCreatedAt();
            $array['created_at_human'] = $this->getCreatedAtDiffForHumans();
        }

        if (isset($array['updated_at'])) {
            $array['updated_at_formatted'] = $this->getFormattedUpdatedAt();
            $array['updated_at_human'] = $this->getUpdatedAtDiffForHumans();
        }

        return $array;
    }

    /**
     * Get model metadata for debugging and logging
     *
     * @return array<string, mixed>
     */
    public function getModelMetadata(): array
    {
        return [
            'model_class' => static::class,
            'table_name' => $this->getTable(),
            'primary_key' => $this->getKeyName(),
            'key_value' => $this->getKey(),
            'exists' => $this->exists,
            'was_recently_created' => $this->wasRecentlyCreated,
            'is_dirty' => $this->isDirty(),
            'dirty_attributes' => $this->getDirty(),
            'fillable_attributes' => $this->getFillable(),
            'hidden_attributes' => $this->getHidden(),
            'cast_attributes' => $this->getCasts(),
            'created_at' => $this->getFormattedCreatedAt(),
            'updated_at' => $this->getFormattedUpdatedAt(),
            'age_in_days' => $this->getAgeInDays(),
        ];
    }
}
