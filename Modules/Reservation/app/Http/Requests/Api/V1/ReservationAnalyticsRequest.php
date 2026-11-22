<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Reservation\Enums\ReservationAnalyticsWidget;

class ReservationAnalyticsRequest extends Request
{
    /**
     * Normalize nested filters and analytics defaults before validation.
     */
    protected function prepareForValidation(): void
    {
        $filters = (array) $this->input('filters', []);
        $today = CarbonImmutable::today();

        $this->merge([
            'branch_id' => $this->input('branch_id', $filters['branch_id'] ?? null),
            'from' => $this->input('from', $filters['from'] ?? $today->startOfMonth()->toDateString()),
            'to' => $this->input('to', $filters['to'] ?? $today->endOfMonth()->toDateString()),
            'widgets' => $this->input('widgets', ReservationAnalyticsWidget::defaultValues()),
        ]);
    }

    /**
     * Get validation rules for reservation analytics filters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(true, true),
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'widgets' => ['required', 'array', 'min:1', 'max:'.count(ReservationAnalyticsWidget::cases())],
            'widgets.*' => ['bail', 'string', 'distinct', Rule::enum(ReservationAnalyticsWidget::class)],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'reservation::frontend.analytics';
    }
}
