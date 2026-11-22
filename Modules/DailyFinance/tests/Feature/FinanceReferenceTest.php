<?php

namespace Modules\DailyFinance\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Modules\Branch\Models\Branch;
use Modules\DailyFinance\Models\CashHandover;
use Modules\DailyFinance\Models\DayClosing;
use Modules\DailyFinance\Models\PaymentSettlement;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinanceReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->actingAs($this->user);
    }

    public static function references(): array
    {
        return [
            'cash handover' => [CashHandover::class, 'CH'],
            'day closing' => [DayClosing::class, 'DC'],
            'payment settlement' => [PaymentSettlement::class, 'PS'],
        ];
    }

    #[DataProvider('references')]
    public function test_references_are_stored_after_creation_and_cannot_be_reassigned(string $class, string $prefix): void
    {
        $record = $this->record($class);
        $reference = $prefix.'-0001';
        $this->assertSame($reference, $record->getAttribute('reference_no'));
        $this->assertSame($reference, $record->fresh()->getAttribute('reference_no'));
        $this->assertSame($record->getRawOriginal('created_at'), $record->getRawOriginal('updated_at'));
        $this->assertFalse($record->isDirty('reference_no'));

        $this->expectException(LogicException::class);
        $record->forceFill(['reference_no' => 'REASSIGNED'])->save();
    }

    #[DataProvider('references')]
    public function test_large_ids_are_not_truncated_and_references_are_unique(string $class, string $prefix): void
    {
        $first = $this->record($class, 12345);
        $second = $this->record($class);
        $this->assertSame($prefix.'-12345', $first->fresh()->getAttribute('reference_no'));
        $this->assertNotSame($first->getAttribute('reference_no'), $second->getAttribute('reference_no'));

        $this->expectException(QueryException::class);
        $second->newModelQuery()->whereKey($second->getKey())->toBase()
            ->update(['reference_no' => $first->getAttribute('reference_no')]);
    }

    /**
     * Build records with distinct business keys so reference constraints are tested independently.
     *
     * @param  class-string<Model>  $class
     */
    protected function record(string $class, ?int $id = null): Model
    {
        $attributes = match ($class) {
            CashHandover::class => [
                'pos_session_id' => PosSession::factory()->create([
                    'branch_id' => $this->branch->id,
                    'pos_register_id' => PosRegister::factory()->create(['branch_id' => $this->branch->id])->id,
                    'opened_by' => $this->user->id,
                    'opened_at' => now(),
                ])->id,
                'cashier_id' => $this->user->id,
                'receiver_id' => $this->user->id,
                'counted_cash' => '10.345',
                'amount' => '6.345',
                'status' => 'pending',
            ],
            DayClosing::class => [
                'date' => now()->subDays(DayClosing::count())->toDateString(),
                'timezone' => 'Asia/Amman',
                'review_hash' => hash('sha256', 'review'),
                'snapshot' => ['saved' => 'audit evidence'],
            ],
            PaymentSettlement::class => [
                'method' => 'card',
                'timezone' => 'Asia/Amman',
                'reference' => 'GATEWAY-001',
                'destination_name' => 'Bank',
                'account_fingerprint' => hash('sha256', 'account'),
                'gross_amount' => '10.345',
                'fees' => '0.045',
                'expected_amount' => '10.300',
                'request_hash' => hash('sha256', 'request'),
            ],
        };
        $record = new $class([
            'branch_id' => $this->branch->id,
            'currency' => 'JOD',
            'request_key' => (string) Str::uuid(),
            ...$attributes,
        ]);
        if ($id !== null) {
            $record->setAttribute('id', $id);
        }
        $record->save();

        return $record;
    }
}
