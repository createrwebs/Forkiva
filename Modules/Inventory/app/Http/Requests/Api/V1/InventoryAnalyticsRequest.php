<?php

namespace Modules\Inventory\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Inventory\Enums\InventoryAnalyticsWidget;

class InventoryAnalyticsRequest extends Request
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
            'from' => $this->input('from', $filters['from'] ?? $today->subDays(29)->toDateString()),
            'to' => $this->input('to', $filters['to'] ?? $today->toDateString()),
            'widgets' => $this->input('widgets', InventoryAnalyticsWidget::defaultValues()),
        ]);
    }

    /**
     * Get validation rules for inventory analytics filters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(true, true),
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'widgets' => ['required', 'array', 'min:1', 'max:'.count(InventoryAnalyticsWidget::cases())],
            'widgets.*' => ['bail', 'string', 'distinct', Rule::enum(InventoryAnalyticsWidget::class)],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'inventory::inventories.analytics';
    }
}
