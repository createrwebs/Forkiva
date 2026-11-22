<?php

namespace Modules\Pos\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Order\Enums\OrderType;
use Modules\Order\Facades\Pricing;
use Modules\Pos\Services\PosViewer\PosViewerService;
use Modules\User\Models\User;
use Tests\TestCase;

class PosCartInitializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_configuration_returns_branch_and_order_type_without_persisting_a_cart(): void
    {
        $branch = Branch::factory()->create([
            'currency' => 'USD',
            'is_active' => true,
            'order_types' => [OrderType::Takeaway->value],
        ]);
        $this->actingAs(User::factory()->create(['branch_id' => $branch->id]));

        $configuration = Pricing::mutate(fn () => app(PosViewerService::class)->getConfiguration($branch->id));

        $this->assertSame($branch->id, $configuration['branch_id']);
        $this->assertEquals($branch->id, Pricing::branch()->id());
        $this->assertSame('USD', Pricing::branch()->currency());
        $this->assertSame(OrderType::Takeaway->value, Pricing::orderType()->value());
        $this->assertTrue($configuration['sounds']['enabled']);
        $this->assertSame(65, $configuration['sounds']['volume']);
        $this->assertSame('pop', $configuration['sounds']['actions']['item_added']['sound']);
        $this->assertTrue($configuration['sounds']['actions']['payment_completed']['enabled']);
        $this->assertTrue(Pricing::isEmpty());
    }
}
