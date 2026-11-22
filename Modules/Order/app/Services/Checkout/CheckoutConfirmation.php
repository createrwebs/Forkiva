<?php

namespace Modules\Order\Services\Checkout;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Order\Support\CheckoutInput;
use Modules\Order\Support\CheckoutPricingFingerprint;
use Throwable;

final class CheckoutConfirmation
{
    /**
     * Require a new preview when the inputs, scope, price or token validity changed.
     *
     * @throws JsonException
     */
    public function confirm(array $input, array $calculation, string $scope): void
    {
        try {
            $token = json_decode(Crypt::decryptString($input['quote_token'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $token = [];
        }
        $reason = match (true) {
            ! is_array($token), empty($token) => 'quote_invalid',
            ($token['scope'] ?? '') !== hash('sha256', $scope) => 'quote_context_changed',
            ($token['expires'] ?? 0) < now()->timestamp => 'quote_expired',
            ($token['input'] ?? '') !== CheckoutInput::hash($input) => 'quote_input_changed',
            ($token['pricing'] ?? '') !== CheckoutPricingFingerprint::hash($calculation) => 'price_changed',
            default => null,
        };
        if ($reason !== null) {
            $this->reject($input, $calculation, $scope, $reason);
        }
    }

    private function reject(array $input, array $calculation, string $scope, string $reason): never
    {
        throw new HttpResponseException(response()->json([
            'body' => $this->quote($input, $calculation, $scope),
            'message' => __("order::checkout.$reason"),
            'errors' => ['reason' => 'quote_changed', 'detail' => $reason],
        ], 409));
    }

    /**
     * Bind the selected inputs and calculated prices to a one-hour authenticated token.
     *
     * @throws JsonException
     */
    public function quote(array $input, array $calculation, string $scope): array
    {
        $expiresAt = now()->addHour();
        $claims = [
            'scope' => hash('sha256', $scope),
            'input' => CheckoutInput::hash($input),
            'pricing' => CheckoutPricingFingerprint::hash($calculation),
            'expires' => $expiresAt->timestamp,
        ];

        return [
            'pricing' => $calculation,
            'quote_token' => Crypt::encryptString(json_encode($claims, JSON_THROW_ON_ERROR)),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Confirm the displayed breakdown in the initial POS request without trusting it for persistence.
     *
     * @throws JsonException
     */
    public function confirmDisplayed(array $input, array $calculation, string $scope): void
    {
        try {
            $displayed = json_decode($input['displayed_pricing'], true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($displayed)) {
                throw new JsonException;
            }
            $expected = CheckoutPricingFingerprint::hash($displayed);
        } catch (Throwable) {
            throw ValidationException::withMessages(['displayed_pricing' => __('order::checkout.invalid_input')]);
        }

        $comparison = $calculation;
        if (($comparison['discount']['id'] ?? null) === null) {
            $comparison['discount']['value'] = null;
        }

        if (! hash_equals(CheckoutPricingFingerprint::hash($comparison), $expected)) {
            $this->reject($input, $calculation, $scope, 'price_changed');
        }
    }

    /**
     * The unique receipt and saved order commit together; uncertain retries replay before repricing.
     *
     * @throws JsonException
     * @throws Throwable
     */
    public function once(string $scope, array $input, callable $save): array
    {
        abort_unless(Str::isUuid($input['idempotency_key'] ?? ''), 422, __('order::checkout.invalid_submission'));
        $scope = hash('sha256', $scope);
        $hash = CheckoutInput::hash($input);

        return DB::transaction(function () use ($scope, $hash, $input, $save) {
            DB::table('order_submissions')->insertOrIgnore([
                'scope' => $scope,
                'idempotency_key' => $input['idempotency_key'],
                'request_hash' => $hash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $receipt = DB::table('order_submissions')
                ->where('scope', $scope)
                ->where('idempotency_key', $input['idempotency_key'])
                ->lockForUpdate()
                ->first();
            abort_unless($receipt && hash_equals($receipt->request_hash, $hash), 409, __('order::checkout.invalid_submission'));
            if ($receipt->response !== null) {
                return json_decode($receipt->response, true, 512, JSON_THROW_ON_ERROR);
            }
            $result = $save();
            DB::table('order_submissions')->where('id', $receipt->id)->update([
                'order_id' => $result['id'],
                'response' => json_encode($result, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return $result;
        }, 3);
    }

    /**
     * Authorization belongs to the caller. A receipt remains valid after mutable sale state changes.
     *
     * @throws JsonException
     */
    public function replay(string $scope, array $input): ?array
    {
        if (! Str::isUuid($input['idempotency_key'] ?? '')) {
            return null;
        }
        $receipt = DB::table('order_submissions')->where('scope', hash('sha256', $scope))
            ->where('idempotency_key', $input['idempotency_key'])->first();
        if (! $receipt) {
            return null;
        }
        abort_unless(hash_equals($receipt->request_hash, CheckoutInput::hash($input)), 409, __('order::checkout.invalid_submission'));

        return $receipt->response === null ? null : json_decode($receipt->response, true, 512, JSON_THROW_ON_ERROR);
    }
}
