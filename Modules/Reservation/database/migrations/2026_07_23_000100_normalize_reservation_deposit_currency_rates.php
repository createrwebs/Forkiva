<?php

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationDepositTransaction;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoPostedLegacyCurrencyJournals();
        $this->invertRatesAndRebuildBaseBalances('source_to_default');
    }

    public function down(): void
    {
        $this->invertRatesAndRebuildBaseBalances('default_to_source');
    }

    /**
     * Invert the previous default-to-source snapshots and rebuild every base balance.
     *
     * This transformation is intentionally its own inverse so rollback restores the
     * preceding rate direction and its matching base-balance calculation.
     */
    private function invertRatesAndRebuildBaseBalances(string $rateDirection): void
    {
        $reservationsTable = (new Reservation)->getTable();
        $transactionsTable = (new ReservationDepositTransaction)->getTable();
        $defaultCurrency = strtoupper((string) setting('default_currency', 'JOD'));
        $baseBalances = [];
        $baseAmounts = [];
        $effectiveRates = [];

        DB::table($transactionsTable)
            ->orderBy('id')
            ->chunkById(250, function ($transactions) use (
                $defaultCurrency,
                $rateDirection,
                $transactionsTable,
                &$baseBalances,
                &$baseAmounts,
                &$effectiveRates,
            ): void {
                foreach ($transactions as $transaction) {
                    $oldRate = BigDecimal::of((string) $transaction->currency_rate);
                    if ($oldRate->isLessThanOrEqualTo(BigDecimal::zero())) {
                        throw new RuntimeException("Reservation deposit transaction [$transaction->id] has an invalid currency rate.");
                    }
                    $newRate = strtoupper((string) $transaction->currency) === $defaultCurrency
                        ? BigDecimal::one()
                        : BigDecimal::one()->dividedBy($oldRate, 8, RoundingMode::HalfUp);
                    $amount = BigDecimal::of((string) $transaction->amount);
                    $baseAmount = $transaction->type === 'reversal' && $transaction->original_transaction_id
                        ? ($baseAmounts[$transaction->original_transaction_id] ?? $amount->multipliedBy($newRate))
                        : $amount->multipliedBy($newRate);
                    $baseAmount = $baseAmount->toScale(8, RoundingMode::HalfUp);
                    $baseBefore = $baseBalances[$transaction->reservation_id] ?? BigDecimal::zero();
                    $direction = in_array($transaction->type, ['opening', 'collection'], true) ? 1 : -1;

                    if ($transaction->type === 'reversal' && $transaction->original_transaction_id) {
                        $original = DB::table($transactionsTable)->find($transaction->original_transaction_id);
                        $direction = in_array($original?->type, ['opening', 'collection'], true) ? -1 : 1;
                    }

                    $baseAfter = ($direction > 0
                        ? $baseBefore->plus($baseAmount)
                        : $baseBefore->minus($baseAmount))
                        ->toScale(8, RoundingMode::HalfUp);
                    $sourceSnapshot = json_decode((string) $transaction->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
                    $sourceSnapshot = [
                        ...$sourceSnapshot,
                        'currency_rate' => (string) $newRate,
                        'currency_rate_direction' => $rateDirection,
                        'base_amount' => (string) $baseAmount,
                        'base_balance_before' => (string) $baseBefore->toScale(8, RoundingMode::HalfUp),
                        'base_balance_after' => (string) $baseAfter,
                    ];

                    DB::table($transactionsTable)
                        ->where('id', $transaction->id)
                        ->update([
                            'currency_rate' => (string) $newRate,
                            'base_amount' => (string) $baseAmount,
                            'base_balance_before' => (string) $baseBefore->toScale(8, RoundingMode::HalfUp),
                            'base_balance_after' => (string) $baseAfter,
                            'source_snapshot' => json_encode($sourceSnapshot, JSON_THROW_ON_ERROR),
                        ]);

                    $baseAmounts[$transaction->id] = $baseAmount;
                    $baseBalances[$transaction->reservation_id] = $baseAfter;
                    $balanceAfter = BigDecimal::of((string) $transaction->balance_after);
                    $effectiveRates[$transaction->reservation_id] = $balanceAfter->isGreaterThan(BigDecimal::zero())
                        ? $baseAfter->dividedBy($balanceAfter, 8, RoundingMode::HalfUp)
                        : $newRate;
                }
            }, 'id');

        DB::table($reservationsTable)
            ->orderBy('id')
            ->chunkById(250, function ($reservations) use ($defaultCurrency, $reservationsTable, $effectiveRates): void {
                foreach ($reservations as $reservation) {
                    $oldRate = BigDecimal::of((string) $reservation->deposit_currency_rate);
                    if ($oldRate->isLessThanOrEqualTo(BigDecimal::zero())) {
                        throw new RuntimeException("Reservation [$reservation->id] has an invalid deposit currency rate.");
                    }
                    $rate = $effectiveRates[$reservation->id]
                        ?? (strtoupper((string) $reservation->deposit_currency) === $defaultCurrency
                            ? BigDecimal::one()
                            : BigDecimal::one()->dividedBy($oldRate, 8, RoundingMode::HalfUp));

                    DB::table($reservationsTable)
                        ->where('id', $reservation->id)
                        ->update([
                            'deposit_currency_rate' => (string) $rate->toScale(8, RoundingMode::HalfUp),
                        ]);
                }
            }, 'id');
    }

    /**
     * Prevent a silent mismatch between corrected source balances and immutable journals.
     */
    private function assertNoPostedLegacyCurrencyJournals(): void
    {
        $transactionsTable = (new ReservationDepositTransaction)->getTable();
        // Accounting is optional. Use the optional table name instead of its
        // model so a clean installation can migrate without Accounting code.
        $postingLogsTable = 'accounting_posting_logs';

        if (! Schema::hasTable($postingLogsTable)) {
            return;
        }

        $defaultCurrency = strtoupper((string) setting('default_currency', 'JOD'));
        $hasPostedLegacyCurrencyJournal = DB::table($postingLogsTable)
            ->join($transactionsTable, "$transactionsTable.id", '=', "$postingLogsTable.source_id")
            ->where("$postingLogsTable.source_type", 'reservation_deposit_transaction')
            ->whereNotNull("$postingLogsTable.journal_entry_id")
            ->whereRaw("upper($transactionsTable.currency) <> ?", [$defaultCurrency])
            ->exists();

        if ($hasPostedLegacyCurrencyJournal) {
            throw new RuntimeException(
                'Posted reservation deposit journals use the legacy FX direction. '
                .'Complete reviewed append-only Accounting remediation before this migration.'
            );
        }
    }
};
