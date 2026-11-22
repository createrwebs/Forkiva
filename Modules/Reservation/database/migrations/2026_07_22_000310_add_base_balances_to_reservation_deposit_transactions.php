<?php

use Brick\Math\BigDecimal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_deposit_transactions', function (Blueprint $table): void {
            $table->decimal('base_amount', 24, 8)->default(0)->after('amount');
            $table->decimal('base_balance_before', 24, 8)->default(0)->after('balance_before');
            $table->decimal('base_balance_after', 24, 8)->default(0)->after('balance_after');
        });

        $baseBalances = [];
        DB::table('reservation_deposit_transactions')
            ->orderBy('reservation_id')
            ->orderBy('id')
            ->chunk(250, function ($transactions) use (&$baseBalances): void {
                foreach ($transactions as $transaction) {
                    $baseBefore = $baseBalances[$transaction->reservation_id] ?? BigDecimal::zero();
                    $baseAmount = BigDecimal::of((string) $transaction->amount)
                        ->multipliedBy((string) $transaction->currency_rate);
                    $direction = in_array($transaction->type, ['opening', 'collection'], true) ? 1 : -1;
                    if ($transaction->type === 'reversal' && $transaction->original_transaction_id) {
                        $original = DB::table('reservation_deposit_transactions')->find($transaction->original_transaction_id);
                        $direction = in_array($original?->type, ['opening', 'collection'], true) ? -1 : 1;
                    }
                    $baseAfter = $direction > 0 ? $baseBefore->plus($baseAmount) : $baseBefore->minus($baseAmount);
                    DB::table('reservation_deposit_transactions')->where('id', $transaction->id)->update([
                        'base_amount' => (string) $baseAmount,
                        'base_balance_before' => (string) $baseBefore,
                        'base_balance_after' => (string) $baseAfter,
                    ]);
                    $baseBalances[$transaction->reservation_id] = $baseAfter;
                }
            });
    }

    public function down(): void
    {
        Schema::table('reservation_deposit_transactions', function (Blueprint $table): void {
            $table->dropColumn(['base_amount', 'base_balance_before', 'base_balance_after']);
        });
    }
};
