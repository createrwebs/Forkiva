<?php

namespace Modules\Order\Http\Requests\Api\V1;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Http\Requests\Request;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Services\Checkout\CheckoutConfirmation;
use Modules\Order\Services\Order\OrderServiceInterface;
use Modules\Order\Support\CheckoutInput;
use Modules\Payment\Enums\RefundPaymentMethod;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Enums\PosSubmitAction;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\Support\ApiResponse;
use Modules\User\Enums\DefaultRole;

class SaveOrderRequest extends Request
{
    /**
     * Replay an authorized committed submission before checking mutable sale state.
     * Shape validation still rejects altered inputs under the same submission key.
     */
    protected function prepareForValidation(): void
    {
        if ($this->is('*/quote') || ! $this->input('idempotency_key')) {
            return;
        }
        $user = $this->user();
        if ($user->assignedToBranch()) {
            $this->merge(['branch_id' => $user->branch_id]);
        }
        app(BranchAccessServiceInterface::class)->resolve((int) $this->input('branch_id'), true);
        $id = $this->route('orderId');
        if ($id) {
            Order::findOrFail($id);
        }
        // Validate shape without requiring the original session/table to remain open on replay.
        $rules = [
            ...CheckoutInput::rules(),
            'branch_id' => 'required|integer',
            'menu_id' => 'required|integer',
            'register_id' => 'required|integer',
            'session_id' => 'required|integer',
            'submit_action' => ['required', Rule::enum(PosSubmitAction::class)],
            'type' => ['required', Rule::enum(OrderType::class)],
            'table_id' => 'nullable|integer', 'waiter_id' => 'nullable|integer',
            'refund_payment_method' => ['nullable', Rule::in(RefundPaymentMethod::values())],
            'gift_card_code' => ['required_if:refund_payment_method,gift_card', 'nullable', 'string', 'max:255'],
            'notes' => 'nullable|string|max:1000', 'guest_count' => 'nullable|integer|min:1',
            'car_plate' => 'nullable|string|max:200', 'car_description' => 'nullable|string|max:200',
            'scheduled_at' => 'nullable|date_format:Y-m-d H:i:s',
        ];
        $input = Validator::make($this->all(), $rules)->validate();
        $result = app(CheckoutConfirmation::class)->replay(
            'pos:'.$user->id.':'.($id ? 'edit:'.$id : 'create'), $input
        );
        if ($result !== null) {
            throw new HttpResponseException(ApiResponse::success($result));
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = auth()->user();

        if ($user->assignedToBranch()) {
            $this->offsetSet('branch_id', $user->branch_id);
        }

        $branchId = $this->input('branch_id');

        /** @var Branch $branch */
        $branch = Branch::withoutGlobalActive()->find($branchId);

        $orderTypes = $branch?->order_types ?: [];

        if ($user->hasRole(DefaultRole::Waiter->value) && in_array(OrderType::DineIn->value, $orderTypes)) {
            $orderTypes = [OrderType::DineIn->value];
        } elseif (! is_null($branch)) {
            $orderTypes = app(DeliveryFeatureInterface::class)->filterSupportedOrderTypes($branch, $orderTypes);
        }

        $order = null;
        if ($this->route('orderId')) {
            $order = app(OrderServiceInterface::class)->findOrFail($this->route('orderId'));

            if ($order->status !== OrderStatus::Pending) {
                $orderTypes = [$order->type->value];
            }
        }

        return [
            ...CheckoutInput::rules(),
            'submit_action' => ['required', Rule::enum(PosSubmitAction::class)],
            'refund_payment_method' => ['nullable', Rule::in(RefundPaymentMethod::values())],
            'gift_card_code' => ['required_if:refund_payment_method,gift_card', 'nullable', 'string', 'max:255'],
            'branch_id' => 'bail|required|integer|exists:branches,id,deleted_at,NULL,is_active,1',
            'menu_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('menus', 'id')
                    ->where('branch_id', $branchId)
//                    ->where('is_active', true) TODO: Remove comment on add is default menu and keep is active and id not active not pos can use
                    ->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::in($orderTypes)],
            'table_id' => [
                'bail',
                $this->is('*/quote') ? 'nullable' : 'required_if:type,dine_in',
                'nullable',
                'integer',
                Rule::exists('tables', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('branch_id', $branchId)
                    ->when(
                        is_null($order) || (int) $order->table_id !== (int) $this->input('table_id'),
                        fn ($query) => $query->where('status', TableStatus::Available->value)
                    ),
            ],
            'register_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('pos_registers', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('branch_id', $branchId),
            ],
            'session_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('pos_sessions', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', PosSessionStatus::Open->value)
                    ->where('branch_id', $branchId)
                    ->where('pos_register_id', $this->input('register_id')),
            ],
            'waiter_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->whereNull('deleted_at')
                    ->where('branch_id', $branchId)
                    ->where('is_active', true),
            ],
            'notes' => 'nullable|string|max:1000',
            'guest_count' => 'nullable|integer|min:1',
            'car_plate' => 'nullable|string|max:200',
            'car_description' => 'nullable|string|max:200',
            'scheduled_at' => ($this->is('*/quote') ? '' : 'required_if:type,pre_order|').'nullable|date|after_or_equal:today|date_format:Y-m-d H:i:s',
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'order::attributes.orders';
    }
}
