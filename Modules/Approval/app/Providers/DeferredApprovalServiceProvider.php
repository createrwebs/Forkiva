<?php

namespace Modules\Approval\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Approval\Services\ApprovalWorkflow\ApprovalWorkflowService;
use Modules\Approval\Services\ApprovalWorkflow\ApprovalWorkflowServiceInterface;

class DeferredApprovalServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Boot the application events.
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: ApprovalWorkflowServiceInterface::class,
            concrete: fn ($app) => $app->make(ApprovalWorkflowService::class)
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ApprovalWorkflowServiceInterface::class,
        ];
    }
}
