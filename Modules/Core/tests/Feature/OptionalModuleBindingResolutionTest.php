<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Core\Contracts\FinancialExpenseSummaryReaderInterface;
use Modules\Core\Contracts\FinancialInventoryBalanceReaderInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Core\Contracts\FinancialPostingStatusReaderInterface;
use Modules\Core\Services\Accounting\NullFinancialAccountLookup;
use Modules\Core\Services\Accounting\NullFinancialExpenseSummaryReader;
use Modules\Core\Services\Accounting\NullFinancialInventoryBalanceReader;
use Modules\Core\Services\Accounting\NullFinancialPostingBridge;
use Modules\Core\Services\Accounting\NullFinancialPostingStatusReader;
use Modules\Core\Services\Delivery\NullDeliveryFeatureService;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Laravel\Module as LaravelModule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OptionalModuleBindingResolutionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return iterable<string, array{string, bool, class-string, class-string}>
     */
    public static function optionalModuleBindings(): iterable
    {
        yield 'Accounting expense summary disabled' => [
            'Accounting',
            false,
            FinancialExpenseSummaryReaderInterface::class,
            NullFinancialExpenseSummaryReader::class,
        ];
        yield 'Accounting expense summary enabled' => [
            'Accounting',
            true,
            FinancialExpenseSummaryReaderInterface::class,
            'Modules\\Accounting\\Services\\FinancialExpenseSummaryReader\\FinancialExpenseSummaryReader',
        ];
        yield 'Accounting disabled' => [
            'Accounting',
            false,
            FinancialAccountLookupInterface::class,
            NullFinancialAccountLookup::class,
        ];
        yield 'Accounting enabled' => [
            'Accounting',
            true,
            FinancialAccountLookupInterface::class,
            'Modules\\Accounting\\Services\\FinancialAccountLookup\\FinancialAccountLookup',
        ];
        yield 'Accounting posting disabled' => [
            'Accounting',
            false,
            FinancialPostingBridgeInterface::class,
            NullFinancialPostingBridge::class,
        ];
        yield 'Accounting status disabled' => [
            'Accounting',
            false,
            FinancialPostingStatusReaderInterface::class,
            NullFinancialPostingStatusReader::class,
        ];
        yield 'Accounting inventory balance disabled' => [
            'Accounting',
            false,
            FinancialInventoryBalanceReaderInterface::class,
            NullFinancialInventoryBalanceReader::class,
        ];
        yield 'Delivery disabled' => [
            'Delivery',
            false,
            DeliveryFeatureInterface::class,
            NullDeliveryFeatureService::class,
        ];
        yield 'Delivery enabled' => [
            'Delivery',
            true,
            DeliveryFeatureInterface::class,
            'Modules\\Delivery\\Services\\Delivery\\DeliveryService',
        ];
    }

    public function test_expenses_remain_unavailable_when_accounting_is_absent(): void
    {
        Module::shouldReceive('find')->with('Accounting')->andReturn(null);
        Module::shouldReceive('find')->with('accounting')->andReturn(null);
        app()->forgetInstance(FinancialExpenseSummaryReaderInterface::class);
        $reader = app(FinancialExpenseSummaryReaderInterface::class);
        $this->assertInstanceOf(NullFinancialExpenseSummaryReader::class, $reader);
        $this->assertNull($reader->forDate(1, '2026-10-11'));
    }

    /**
     * @param  class-string  $contract
     * @param  class-string  $expectedService
     */
    #[DataProvider('optionalModuleBindings')]
    public function test_optional_module_binding_resolves_the_service_or_fallback(
        string $moduleName,
        bool $enabled,
        string $contract,
        string $expectedService,
    ): void {
        $module = Mockery::mock(
            LaravelModule::class,
            [app(), $moduleName, base_path("Modules/$moduleName")],
        )->makePartial();
        $module->shouldReceive('isEnabled')->andReturn($enabled);

        Module::shouldReceive('find')
            ->once()
            ->with($moduleName)
            ->andReturn($module);

        app()->forgetInstance($contract);

        $this->assertInstanceOf($expectedService, app($contract));
    }
}
