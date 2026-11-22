<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\Exceptions\MissingTenantContextException;
use Modules\Core\Support\TenantContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TenantContextTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_default_context_is_null_and_require_id_throws(): void
    {
        $this->assertNull(TenantContext::id());

        $this->expectException(MissingTenantContextException::class);
        TenantContext::requireId();
    }

    public function test_run_for_sets_and_restores_nested_tenant_context(): void
    {
        TenantContext::set(10);

        $innerResult = TenantContext::runFor(20, function (): int {
            $this->assertSame(20, TenantContext::id());

            return TenantContext::requireId();
        });

        $this->assertSame(20, $innerResult);
        $this->assertSame(10, TenantContext::id());
    }

    public function test_run_for_restores_context_when_callback_throws(): void
    {
        TenantContext::set(10);

        try {
            TenantContext::runFor(25, function (): void {
                $this->assertSame(25, TenantContext::id());
                throw new RuntimeException('boom');
            });
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertSame(10, TenantContext::id());
    }

    public function test_without_tenant_temporarily_clears_and_restores_context(): void
    {
        TenantContext::set(42);

        $inside = TenantContext::withoutTenant(fn () => TenantContext::id());

        $this->assertNull($inside);
        $this->assertSame(42, TenantContext::id());
    }

    public function test_make_cache_key_produces_distinct_keys_per_tenant(): void
    {
        require_once dirname(__DIR__, 3).'/Support/helpers.php';

        TenantContext::set(1);
        $keyTenantOne = makeCacheKey(['branches', 'list'], false);

        TenantContext::set(2);
        $keyTenantTwo = makeCacheKey(['branches', 'list'], false);

        TenantContext::clear();
        $keyNoTenant = makeCacheKey(['branches', 'list'], false);

        $this->assertNotSame($keyTenantOne, $keyTenantTwo);
        $this->assertNotSame($keyTenantOne, $keyNoTenant);
        $this->assertNotSame($keyTenantTwo, $keyNoTenant);
    }
}
