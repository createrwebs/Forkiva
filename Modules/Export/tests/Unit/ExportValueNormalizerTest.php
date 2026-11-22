<?php

namespace Modules\Export\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Export\Support\ExportValueNormalizer;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Support\Money;
use Tests\TestCase;

class ExportValueNormalizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'default_locale' => 'en',
                    'default_currency' => 'USD',
                    'default_date_format' => 'Y-m-d',
                    'default_time_format' => 'H:i',
                    default => $default,
                };
            }
        });
    }

    public function test_normalizes_money_enum_boolean_and_datetime_values(): void
    {
        $normalizer = app(ExportValueNormalizer::class);
        $normalizedBoolean = $normalizer->normalize(true, ['type' => 'boolean']);

        $this->assertSame('Cash', $normalizer->normalize(PaymentMethod::Cash));
        $this->assertIsString($normalizedBoolean);
        $this->assertNotSame('', trim($normalizedBoolean));
        $this->assertSame('2026-04-28 13:45', $normalizer->normalize(Carbon::parse('2026-04-28 13:45:00'), ['type' => 'datetime']));
        $this->assertSame(42.5, $normalizer->normalize(new Money(42.5, 'USD'), ['type' => 'money']));
    }

    public function test_normalizes_null_and_array_values(): void
    {
        $normalizer = app(ExportValueNormalizer::class);

        $this->assertSame('', $normalizer->normalize(null));
        $this->assertSame('{"foo":"bar"}', $normalizer->normalize(['foo' => 'bar']));
    }
}
