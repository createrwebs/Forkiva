<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Modules\Core\Contracts\IdempotencyServiceInterface;
use Modules\Core\Contracts\ImmutableAuditLoggerInterface;
use Modules\Core\Exceptions\IdempotencyConflictException;
use Tests\TestCase;

/**
 * Verifies the generic governance services retained by the Core module.
 */
class CoreGovernanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure sensitive evidence is reasoned, chained, and append-only.
     */
    public function test_sensitive_audit_events_require_reasons_are_hash_chained_and_immutable(): void
    {
        $audit = app(ImmutableAuditLoggerInterface::class);
        $first = $audit->record('payment.reversed', 'Duplicate bank transfer.', 20, branchId: 1);
        $second = $audit->record('period.reopened', 'Approved audit adjustment.', 21, branchId: 1);

        $this->assertNull($first->previous_hash);
        $this->assertSame($first->event_hash, $second->previous_hash);
        $this->assertNotSame($first->event_hash, $second->event_hash);

        $this->expectException(LogicException::class);
        $first->update(['reason' => 'Changed']);
    }

    /**
     * Ensure equivalent requests replay while changed payloads are rejected.
     */
    public function test_idempotent_operations_replay_the_original_result_and_reject_payload_conflicts(): void
    {
        $idempotency = app(IdempotencyServiceInterface::class);
        $executions = 0;
        $first = $idempotency->execute('payments', 'pay-100', ['amount' => 10, 'currency' => 'USD'], function () use (&$executions): array {
            $executions++;

            return ['payment_id' => 500];
        });
        $replay = $idempotency->execute('payments', 'pay-100', ['currency' => 'USD', 'amount' => 10], function () use (&$executions): array {
            $executions++;

            return ['payment_id' => 999];
        });

        $this->assertFalse($first['replayed']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame(['payment_id' => 500], $replay['response']);
        $this->assertSame(1, $executions);

        $this->expectException(IdempotencyConflictException::class);
        $idempotency->execute('payments', 'pay-100', ['amount' => 11], fn (): array => []);
    }
}
