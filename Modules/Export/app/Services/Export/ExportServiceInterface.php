<?php

namespace Modules\Export\Services\Export;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Export\Enums\ExportFormat;
use Modules\Export\Models\Export;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ExportServiceInterface
{
    /**
     * Queue a new export.
     */
    public function queue(
        string $resource,
        ExportFormat $format,
        array $filters = [],
        ?array $sorts = [],
        array $context = [],
        string $contextKey = '',
    ): Export;

    /**
     * Paginate exports for the active user and table context.
     *
     * @param  array<string,mixed>  $filters
     *
     * @throws BindingResolutionException
     */
    public function paginate(string $resource, string $contextKey, array $filters = []): LengthAwarePaginator;

    /**
     * Get the latest export for the active user and table context.
     */
    public function latest(string $resource, string $contextKey): ?Export;

    /**
     * Find a single export owned by the current user.
     */
    public function findOrFail(int $id): Export;

    /**
     * Download a completed export file.
     */
    public function download(int $id): StreamedResponse;
}
