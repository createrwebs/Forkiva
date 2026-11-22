<?php

namespace Modules\GiftCard\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Core\Http\Requests\Request;
use Modules\GiftCard\Enums\GiftCardAnalyticsWidget;

class GiftCardAnalyticsRequest extends Request
{
    /**
     * Normalize legacy nested filters and analytics defaults before validation.
     */
    protected function prepareForValidation(): void
    {
        $filters = (array) $this->input('filters', []);
        $today = CarbonImmutable::today();

        $this->merge([
            'branch_id' => $this->input('branch_id', $filters['branch_id'] ?? null),
            'from' => $this->input('from', $filters['from'] ?? $today->startOfYear()->toDateString()),
            'to' => $this->input('to', $filters['to'] ?? $today->toDateString()),
            'widgets' => $this->input('widgets', GiftCardAnalyticsWidget::defaultValues()),
            'refresh' => $this->boolean('refresh'),
        ]);
    }

    /**
     * Get validation rules for Gift Card Analytics filters and widget selection.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(true, true),
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'widgets' => ['required', 'array', 'min:1', 'max:'.count(GiftCardAnalyticsWidget::cases())],
            'widgets.*' => ['bail', 'string', 'distinct', Rule::enum(GiftCardAnalyticsWidget::class)],
            'section' => ['nullable', 'string', Rule::in([
                'overview',
                'cards',
                'transactions',
                'batches',
                'branches',
                'expiry',
                'sales_over_time',
                'redemption_over_time',
                'sales_by_branch',
                'liability_by_currency',
                'usage_by_day_of_week',
            ])],
            'refresh' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Validate the bounded synchronous reporting window.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['from', 'to'])) {
                return;
            }

            $from = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('from'));
            $to = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('to'));

            if ($from && $to && $from->diffInDays($to) > 366) {
                $validator->errors()->add('to', __('giftcard::gift_cards.analytics.validation.range'));
            }
        }];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'giftcard::gift_cards.analytics.filters';
    }
}
