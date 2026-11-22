<?php

namespace Modules\Currency\Tests\Unit;

use Modules\Currency\Services\CurrencyRateExchanger;
use Modules\Setting\Repositories\SettingRepository;
use Tests\TestCase;

class CurrencyExchangeRateServiceProviderTest extends TestCase
{
    public function test_exchanger_resolves_when_exchange_service_is_saved_as_null(): void
    {
        app()->instance('setting', new SettingRepository(collect([
            'currency_rate_exchange_service' => [
                'payload' => serialize(null),
                'is_encryptable' => false,
                'is_translatable' => false,
            ],
        ])));

        $this->assertInstanceOf(CurrencyRateExchanger::class, app(CurrencyRateExchanger::class));
    }

    public function test_exchanger_uses_configured_array_service(): void
    {
        config(['currency.services.array.latestRates' => ['USD/EUR' => '0.9']]);
        app()->instance('setting', new SettingRepository(collect([
            'currency_rate_exchange_service' => [
                'payload' => serialize('array'),
                'is_encryptable' => false,
                'is_translatable' => false,
            ],
        ])));

        $this->assertSame(0.9, app(CurrencyRateExchanger::class)->exchange('USD', 'EUR'));
    }
}
