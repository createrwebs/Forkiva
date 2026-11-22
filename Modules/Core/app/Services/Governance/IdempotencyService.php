<?php

namespace Modules\Core\Services\Governance;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\IdempotencyServiceInterface;
use Modules\Core\Enums\IdempotencyRecordStatus;
use Modules\Core\Exceptions\IdempotencyConflictException;
use Modules\Core\Models\IdempotencyRecord;
use Modules\Core\Support\DeterministicPayload;
use Throwable;

class IdempotencyService implements IdempotencyServiceInterface
{
    /** {@inheritDoc} */
    public function execute(
        string $scope,
        string $key,
        array $payload,
        Closure $operation,
        ?int $expiresInSeconds = null,
    ): array {
        $fingerprint = DeterministicPayload::fingerprint($payload);

        try {
            $record = DB::transaction(fn () => IdempotencyRecord::query()->create([
                'scope' => $scope,
                'key' => $key,
                'payload_fingerprint' => $fingerprint,
                'status' => IdempotencyRecordStatus::Processing,
                'expires_at' => $expiresInSeconds ? now()->addSeconds($expiresInSeconds) : null,
            ]));
        } catch (UniqueConstraintViolationException) {
            $record = IdempotencyRecord::query()
                ->where(['scope' => $scope, 'key' => $key])
                ->firstOrFail();

            $this->assertFingerprint($record, $fingerprint);
            if ($record->status === IdempotencyRecordStatus::Completed) {
                return ['response' => $record->response ?? [], 'replayed' => true];
            }
            if ($record->status === IdempotencyRecordStatus::Processing) {
                throw new IdempotencyConflictException(__('core::exceptions.idempotency_already_processing'));
            }
        }

        try {
            /** @var array<string, mixed> $response */
            $response = DB::transaction($operation);
            $record->update([
                'status' => IdempotencyRecordStatus::Completed,
                'response' => $response,
                'error' => null,
                'completed_at' => now(),
            ]);

            return ['response' => $response, 'replayed' => false];
        } catch (Throwable $exception) {
            $record->update([
                'status' => IdempotencyRecordStatus::Failed,
                'error' => $exception->getMessage(),
                'attempts' => $record->attempts + 1,
            ]);
            throw $exception;
        }
    }

    /**
     * Ensure a replayed key belongs to the same immutable payload.
     */
    private function assertFingerprint(IdempotencyRecord $record, string $fingerprint): void
    {
        if (! hash_equals($record->payload_fingerprint, $fingerprint)) {
            throw new IdempotencyConflictException(__('core::exceptions.idempotency_payload_mismatch'));
        }
    }
}
