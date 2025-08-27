<?php

declare(strict_types=1);

/**
 * Service Provider File: ModelObserverServiceProvider.php
 *
 * EPIC: EPIC-001-foundation-infrastructure
 * SPEC: SPEC-001-database-schema-models
 * SPRINT: Sprint-003-models-configuration-management
 * TICKET: 1012-model-classes-relationships
 *
 * Description: Service provider for registering model observers for audit trails,
 * cache management, and event-driven functionality.
 *
 * @see docs/Planning/Epics/EPIC-001-foundation-infrastructure.md
 * @see docs/Planning/Specs/Infrastructure-System/SPEC-001-database-schema-models.md
 * @see docs/Planning/Sprints/003-models-configuration-management.md
 * @see docs/Planning/Tickets/Foundation-Infrastructure/Implementation/1012-model-classes-relationships.md
 */

namespace JTD\FormSecurity\Providers;

use Illuminate\Support\ServiceProvider;
use JTD\FormSecurity\Models\BlockedSubmission;
use JTD\FormSecurity\Models\IpReputation;
use JTD\FormSecurity\Models\SpamPattern;
use JTD\FormSecurity\Observers\BlockedSubmissionObserver;
use JTD\FormSecurity\Observers\IpReputationObserver;
use JTD\FormSecurity\Observers\SpamPatternObserver;

/**
 * ModelObserverServiceProvider Class
 *
 * Registers model observers for handling model events, audit trails,
 * cache management, and other event-driven functionality.
 */
class ModelObserverServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // No services to register
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        $this->registerModelObservers();
    }

    /**
     * Register model observers
     */
    private function registerModelObservers(): void
    {
        // Register BlockedSubmission observer
        BlockedSubmission::observe(BlockedSubmissionObserver::class);

        // Register IpReputation observer
        IpReputation::observe(IpReputationObserver::class);

        // Register SpamPattern observer
        SpamPattern::observe(SpamPatternObserver::class);
    }
}
