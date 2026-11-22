<?php

namespace Modules\Report;

use App\Forkiva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Modules\Branch\Models\Branch;
use Modules\Report\Contracts\ReportInterface;
use Modules\Report\Enums\ExportMethod;
use Modules\Support\Enums\GroupDateType;
use Modules\Support\GlobalStructureFilters;
use Modules\User\Models\User;

abstract class Report implements ReportInterface
{
    /**
     * User Assigned branch
     */
    public Branch $branch;

    /**
     * Determine if you use currency_rate or not
     */
    public bool $withRate = false;

    /**
     * Authentication user
     */
    public ?User $user = null;

    /**
     * Use currency
     */
    public string $currency;

    /**
     * Create a new instance of Report
     */
    public function __construct()
    {
        $this->user = auth()->user();

        if ($this->user->assignedToBranch()) {
            $this->branch = $this->user->branch;
            $this->currency = $this->branch->currency;
        } else {
            $this->currency = setting('default_currency');
            $this->withRate = true;
        }
    }

    /** {@inheritDoc} */
    public function permission(): string
    {
        return "admin.reports.{$this->key()}";
    }

    /** {@inheritDoc} */
    public function transAttributes(): array
    {
        return $this->attributes()
            ->mapWithKeys(fn ($attribute, $key) => [
                $key => __("report::attributes.{$this->key()}.$attribute"),
            ])
            ->toArray();
    }

    /** {@inheritDoc} */
    public function render(Request $request): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->transLabel(),
            'filters' => [
                ...$this->globalFilters(),
                ...$this->filters($request),
            ],
            'headers' => $this->attributes()
                ->map(fn ($attribute) => [
                    'title' => __("report::attributes.{$this->key()}.$attribute"),
                    'value' => $attribute,
                    'sortable' => false,
                ]),
            'export_methods' => $this->exportMethods(),
            'export_mode' => 'queued',
            'export_api_uri' => '/v1/exports/reports',
            'export_permission' => $this->permission(),
            'export_resource' => 'reports',
            'export_context' => ['report' => $this->key()],
            'has_search' => $this->hasSearch(),
            ...$this->data($request),
        ];
    }

    /** {@inheritDoc} */
    public function transLabel(): string
    {
        return __($this->label());
    }

    /** {@inheritDoc} */
    public function label(): string
    {
        return "report::reports.definitions.{$this->key()}.title";
    }

    /**
     * Get global filters
     */
    public function globalFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            GlobalStructureFilters::from(__('report::reports.filters.start_date')),
            GlobalStructureFilters::to(__('report::reports.filters.end_date')),
            ...($this->supportsDateGrouping() ? [GlobalStructureFilters::groupByDate()] : []),
        ];
    }

    /** {@inheritDoc} */
    public function filters(Request $request): array
    {
        return [];
    }

    /** {@inheritDoc} */
    public function hasSearch(): bool
    {
        return false;
    }

    /** {@inheritDoc} */
    public function data(Request $request): array
    {
        $data = $this->query($request)->paginate(Forkiva::paginate())->withQueryString();

        return [
            'data' => array_map(fn ($item) => $this->resource($item), $data->items()),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'from' => $data->firstItem(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'to' => $data->lastItem(),
                'total' => $data->total(),
            ],
        ];
    }

    /** {@inheritDoc} */
    public function query(Request $request): Builder
    {
        // A report may override the model's date column; do not inherit a previous report's override in a worker.
        $defaults = (new \ReflectionClass($this->model()))->getDefaultProperties();
        if (array_key_exists('defaultDateColumn', $defaults)) {
            $this->model()::$defaultDateColumn = $defaults['defaultDateColumn'];
        }
        try {
            $this->resolveDefaultDateColumn();
            $filters = $request->input('filters', []);
            if (! $this->supportsDateGrouping()) {
                unset($filters['group_by_date']);
            }

            $query = app(Pipeline::class)
                ->send($this->model()::query())
                ->through($this->through($request))
                ->thenReturn()
                ->with($this->with())
                ->selectRaw(implode(',', $this->columns()))
                ->filters($filters);

            $dateColumn = $query->qualifyColumn($this->model()::$defaultDateColumn ?? 'created_at');
            if ($this->supportsDateGrouping() || $query->getQuery()->groups) {
                $dateColumn = $query->getQuery()->getGrammar()->wrap($dateColumn);

                return $query->orderByRaw("MAX($dateColumn) DESC");
            }

            return $query->latest($dateColumn);
        } finally {
            if (array_key_exists('defaultDateColumn', $defaults)) {
                $this->model()::$defaultDateColumn = $defaults['defaultDateColumn'];
            }
        }
    }

    /**
     * Detail lists retain individual records; only aggregate reports offer date buckets.
     */
    protected function supportsDateGrouping(): bool
    {
        return (bool) preg_match('/\b(?:SUM|COUNT|MIN|MAX|AVG)\s*\(/i', implode(',', $this->columns()));
    }

    /**
     * Get available export methods.
     */
    public function exportMethods(): array
    {
        return ExportMethod::toArrayTrans();
    }

    /**
     * Resolve default date column
     */
    protected function resolveDefaultDateColumn(): void {}

    /** {@inheritDoc} */
    public function with(): array
    {
        return [];
    }

    /**
     * Determine if user has filter in group by date
     */
    protected function hasGroupByData(Request $request): bool
    {
        return isset($request->input('filters', [])['group_by_date']) && in_array($request->input('filters', [])['group_by_date'], GroupDateType::values());
    }
}
