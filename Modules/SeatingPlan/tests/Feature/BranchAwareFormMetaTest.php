<?php

namespace Modules\SeatingPlan\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\SeatingPlan\Models\Floor;
use Modules\SeatingPlan\Services\Table\TableServiceInterface;
use Modules\User\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BranchAwareFormMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_form_meta_loads_only_the_assigned_branch_dependencies(): void
    {
        $assignedBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $assignedFloor = Floor::factory()->create([
            'branch_id' => $assignedBranch->id,
            'is_active' => true,
            'name' => ['en' => 'Assigned floor', 'ar' => 'طابق الفرع'],
        ]);
        Floor::factory()->create([
            'branch_id' => $otherBranch->id,
            'is_active' => true,
            'name' => ['en' => 'Other floor', 'ar' => 'طابق آخر'],
        ]);
        auth()->setUser(User::factory()->create(['branch_id' => $assignedBranch->id]));

        $service = app(TableServiceInterface::class);
        $meta = $service->getFormMeta();

        $this->assertSame([$assignedBranch->id], $meta['branches']->pluck('id')->all());
        $this->assertArrayNotHasKey('floors', $meta);

        $branchMeta = $service->getFormMeta($assignedBranch->id);

        $this->assertSame([$assignedFloor->id], $branchMeta['floors']->pluck('id')->all());
    }

    public function test_table_form_meta_rejects_another_branch_for_an_assigned_user(): void
    {
        $assignedBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        auth()->setUser(User::factory()->create(['branch_id' => $assignedBranch->id]));

        try {
            app(TableServiceInterface::class)->getFormMeta($otherBranch->id);
            $this->fail('An assigned user must not resolve another branch.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }
}
