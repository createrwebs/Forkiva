<?php

namespace Modules\Dashboard\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Core\Http\Requests\Request;
use Modules\Dashboard\Enums\DashboardWidget;

class DashboardRequest extends Request
{
    /**
     * Normalize nested filters and dashboard defaults before validation.
     */
    protected function prepareForValidation(): void
    {
        $filters = (array) $this->input('filters', []);
        $today = CarbonImmutable::today();

        $this->merge([
            'branch_id' => $this->input('branch_id', $filters['branch_id'] ?? null),
            'from' => $this->input('from', $filters['from'] ?? $today->startOfYear()->toDateString()),
            'to' => $this->input('to', $filters['to'] ?? $today->toDateString()),
            'widgets' => $this->input('widgets', DashboardWidget::defaultValues()),
            'widget_options' => $this->input('widget_options', []),
            'refresh' => $this->boolean('refresh'),
        ]);
    }

    /**
     * Get validation rules for dashboard filters and widget options.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(true, true),
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'widgets' => ['required', 'array', 'min:1', 'max:'.count(DashboardWidget::cases())],
            'widgets.*' => ['bail', 'string', 'distinct', Rule::enum(DashboardWidget::class)],
            'widget_options' => ['nullable', 'array'],
            'widget_options.sales_analytics' => ['nullable', 'array'],
            'widget_options.sales_analytics.granularity' => ['nullable', Rule::in(['auto', 'hour', 'day', 'week', 'month'])],
            'widget_options.hourly_sales_trend' => ['nullable', 'array'],
            'widget_options.hourly_sales_trend.date' => ['nullable', 'date_format:Y-m-d'],
            'widget_options.top_selling_products' => ['nullable', 'array'],
            'widget_options.top_selling_products.limit' => ['nullable', 'integer', Rule::in([5, 10])],
            'widget_options.best_performing_branches' => ['nullable', 'array'],
            'widget_options.best_performing_branches.limit' => ['nullable', 'integer', Rule::in([5, 10])],
            'widget_options.low_stock_alerts' => ['nullable', 'array'],
            'widget_options.low_stock_alerts.limit' => ['nullable', 'integer', Rule::in([5, 10, 20])],
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
                $validator->errors()->add('to', __('dashboard::dashboards.validation.range'));
            }
        }];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'dashboard::dashboards.filters';
    }
}
