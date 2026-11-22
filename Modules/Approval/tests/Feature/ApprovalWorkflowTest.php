<?php

namespace Modules\Approval\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Approval\Enums\ApprovalRequestStatus;
use Modules\Approval\Enums\ApprovalStepStatus;
use Modules\Approval\Exceptions\ApprovalException;
use Modules\Approval\Services\ApprovalWorkflow\ApprovalWorkflowServiceInterface;
use Modules\Branch\Models\Branch;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Tests\TestCase;

/**
 * Covers the standalone Approval module's reusable maker-checker contract.
 */
class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify exact threshold evaluation and maker-checker enforcement.
     */
    public function test_approval_levels_thresholds_and_maker_checker_are_enforced(): void
    {
        $branch = Branch::factory()->create();
        $maker = User::factory()->create();
        $approvals = app(ApprovalWorkflowServiceInterface::class);
        $request = $approvals->request(
            workflow: 'supplier_payment',
            makerId: $maker->id,
            payload: ['supplier_id' => 7, 'amount' => '100.00000001'],
            levels: [
                ['name' => 'Supervisor', 'maximum_amount' => '100.00000000'],
                ['name' => 'Finance manager', 'minimum_amount' => '100.00000001'],
            ],
            subject: $branch,
            branchId: $branch->id,
            amount: '100.00000001',
            currency: 'usd',
        );

        $this->assertSame(ApprovalRequestStatus::Pending, $request->status);
        $this->assertSame(ApprovalStepStatus::Skipped, $request->steps[0]->status);
        $this->assertSame(ApprovalStepStatus::Pending, $request->steps[1]->status);
        $this->assertSame('USD', $request->currency);

        $this->expectException(ApprovalException::class);
        $approvals->approve($request, $maker->id, 'Maker cannot approve.');
    }

    /**
     * Verify versioning, immutable audit evidence, and material-change invalidation.
     */
    public function test_approval_is_versioned_audited_and_invalidated_after_material_change(): void
    {
        $branch = Branch::factory()->create();
        $maker = User::factory()->create();
        $reviewer = User::factory()->create();
        $approvals = app(ApprovalWorkflowServiceInterface::class);
        $payload = ['supplier_id' => 7, 'amount' => '150.00'];
        $request = $approvals->request(
            'supplier_payment',
            $maker->id,
            $payload,
            [['name' => 'Finance manager', 'minimum_amount' => 100]],
            subject: $branch,
            branchId: $branch->id,
            amount: '150.00',
            currency: 'USD',
        );

        $approved = $approvals->approve($request, $reviewer->id, 'Supporting documents verified.');
        $this->assertSame(ApprovalRequestStatus::Approved, $approved->status);
        $approvals->assertApproved($approved);

        $invalidated = $approvals->invalidateIfChanged(
            $approved,
            ['supplier_id' => 7, 'amount' => '175.00'],
            $maker->id,
            'Payment amount changed.',
        );
        $this->assertSame(ApprovalRequestStatus::Invalidated, $invalidated->status);
        $this->assertSame(ApprovalStepStatus::Invalidated, $invalidated->steps->last()->status);

        $replacement = $approvals->request(
            'supplier_payment',
            $maker->id,
            ['supplier_id' => 7, 'amount' => '175.00'],
            [['name' => 'Finance manager', 'minimum_amount' => 100]],
            subject: $branch,
            branchId: $branch->id,
            amount: '175.00',
            currency: 'USD',
        );
        $this->assertSame(2, $replacement->version);
        $this->assertDatabaseHas('immutable_audit_events', ['action' => 'approval.invalidated']);
    }

    /**
     * Verify that a level's configured permission is mandatory.
     */
    public function test_approval_level_permission_is_enforced(): void
    {
        $maker = User::factory()->create();
        $reviewer = User::factory()->create();
        $permission = Permission::query()->firstOrCreate([
            'name' => 'finance.payments.approve',
            'guard_name' => 'api',
        ]);
        $request = app(ApprovalWorkflowServiceInterface::class)->request(
            'supplier_payment',
            $maker->id,
            ['amount' => '50.00'],
            [['name' => 'Finance reviewer', 'permission' => $permission->name]],
        );

        try {
            app(ApprovalWorkflowServiceInterface::class)->approve($request, $reviewer->id, 'Reviewed.');
            $this->fail('A reviewer without the configured permission was allowed to approve.');
        } catch (ApprovalException) {
            $this->assertTrue(true);
        }

        $reviewer->permissions()->attach($permission->id);
        $approved = app(ApprovalWorkflowServiceInterface::class)->approve($request->fresh(), $reviewer->id, 'Reviewed.');
        $this->assertSame(ApprovalRequestStatus::Approved, $approved->status);
    }

    /**
     * Verify rejection reasons and reviewer relationships are persisted.
     */
    public function test_rejection_requires_a_reason_and_records_the_reviewer(): void
    {
        $maker = User::factory()->create();
        $reviewer = User::factory()->create();
        $approvals = app(ApprovalWorkflowServiceInterface::class);
        $request = $approvals->request(
            'manual_journal',
            $maker->id,
            ['journal_id' => 100],
            [['name' => 'Accounting reviewer']],
        );

        try {
            $approvals->reject($request, $reviewer->id, '  ');
            $this->fail('A blank rejection reason was accepted.');
        } catch (ApprovalException) {
            $this->assertTrue(true);
        }

        $rejected = $approvals->reject($request->fresh(), $reviewer->id, 'Unsupported journal evidence.');
        $this->assertSame(ApprovalRequestStatus::Rejected, $rejected->status);
        $this->assertSame($reviewer->id, $rejected->steps->first()->reviewer->id);
        $this->assertSame('Unsupported journal evidence.', $rejected->steps->first()->reason);
    }

    /**
     * Verify malformed level definitions are rejected before any rows are written.
     */
    public function test_invalid_threshold_definition_is_rejected_without_partial_evidence(): void
    {
        $maker = User::factory()->create();

        try {
            app(ApprovalWorkflowServiceInterface::class)->request(
                'supplier_payment',
                $maker->id,
                ['amount' => 50],
                [['name' => 'Invalid level', 'minimum_amount' => 100, 'maximum_amount' => 50]],
                amount: 50,
            );
            $this->fail('An inverted threshold range was accepted.');
        } catch (ApprovalException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('approval_requests', 0);
        $this->assertDatabaseCount('approval_steps', 0);
    }
}
