<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Core\Http\Requests\Request;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Reservation\Enums\ReservationDepositTransactionType;

class RecordReservationDepositRequest extends Request
{
    /**
     * Get validation rules for recording a deposit ledger transaction.
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                ReservationDepositTransactionType::Collection->value,
                ReservationDepositTransactionType::Application->value,
                ReservationDepositTransactionType::Refund->value,
                ReservationDepositTransactionType::Forfeiture->value,
            ])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'tender_method' => [
                'nullable',
                Rule::enum(PaymentMethod::class),
                Rule::notIn([PaymentMethod::GiftCard->value, PaymentMethod::ReservationDeposit->value]),
            ],
            'financial_account_key' => ['nullable', 'string', 'max:255'],
            'economic_date' => ['required', 'date', 'before_or_equal:today'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'note' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * Add transaction-type dependent validation after base rules pass.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') === ReservationDepositTransactionType::Application->value
                && ! $this->filled('order_id')) {
                $validator->errors()->add('order_id', __('validation.required', ['attribute' => 'order_id']));
            }
            if (in_array($this->input('type'), [ReservationDepositTransactionType::Collection->value, ReservationDepositTransactionType::Refund->value], true)
                && ! $this->filled('tender_method')) {
                $validator->errors()->add('tender_method', __('validation.required', ['attribute' => 'tender_method']));
            }
        });
    }

    /**
     * Get the translation namespace for validation attributes.
     */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservation_deposit_transactions';
    }
}
