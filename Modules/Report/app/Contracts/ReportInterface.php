<?php

namespace Modules\Report\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface ReportInterface
{
    /**
     * Get report key
     */
    public function key(): string;

    /**
     * Get report permission
     */
    public function permission(): string;

    /**
     * Get report label
     */
    public function label(): string;

    /**
     * Get report trans label
     */
    public function transLabel(): string;

    /**
     * Get report attributes
     */
    public function attributes(): Collection;

    /**
     * Get report trans attributes
     */
    public function transAttributes(): array;

    /**
     * Get report filters
     */
    public function filters(Request $request): array;

    /**
     * Get report data
     */
    public function data(Request $request): array;

    /**
     * Get the filtered report query used for pagination and exports.
     */
    public function query(Request $request): Builder;

    /**
     * Get select columns
     */
    public function columns(): array;

    /**
     * Get model
     */
    public function model(): string;

    /**
     * Get relationship
     */
    public function with(): array;

    /**
     * Parse data
     */
    public function resource(Model $model): array;

    /**
     * Get a pipeline through
     */
    public function through(Request $request): array;

    /**
     * Render report
     */
    public function render(Request $request): array;

    /**
     * Determine has report search
     */
    public function hasSearch(): bool;
}
