<?php

namespace Modules\Export\Services\Export;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Modules\Export\Contracts\GeneratesExportFilesInterface;
use Modules\Export\Enums\ExportFormat;
use Modules\Export\Enums\ExportStatus;
use Modules\Export\Jobs\GenerateExportJob;
use Modules\Export\Models\Export;
use Modules\Export\Support\ExportSourceResolver;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService implements ExportServiceInterface
{
    /**
     * Create a new service instance.
     */
    public function __construct(protected ExportSourceResolver $resolver) {}

    /** {@inheritDoc} */
    public function queue(
        string $resource,
        ExportFormat $format,
        array $filters = [],
        ?array $sorts = [],
        array $context = [],
        string $contextKey = '',
    ): Export {
        $source = $this->resolver->resolve($resource);
        abort_unless(auth()->user()->can($source->exportPermission()), 403, __('admin::messages.action_unauthorized'));
        abort_if(
            $format === ExportFormat::Json && ! $source instanceof GeneratesExportFilesInterface,
            422,
            __('export::exports.messages.unsupported_format')
        );

        if ($source instanceof GeneratesExportFilesInterface) {
            $source->validateExport($filters, $sorts, $context);
        }

        $export = Export::query()->create([
            'user_id' => auth()->id(),
            'resource_key' => $resource,
            'context_key' => $contextKey,
            'format' => $format,
            'status' => ExportStatus::Pending,
            'filters' => $filters,
            'sorts' => $sorts,
            'context' => $context,
        ]);

        GenerateExportJob::dispatch($export)->afterCommit();

        return $export->fresh();
    }

    /** {@inheritDoc} */
    public function paginate(string $resource, string $contextKey, array $filters = []): LengthAwarePaginator
    {
        $source = $this->resolver->resolve($resource);
        abort_unless(auth()->user()->can($source->exportPermission()), 403, __('admin::messages.action_unauthorized'));

        return Export::query()
            ->where('user_id', auth()->id())
            ->where('resource_key', $resource)
            ->where('context_key', $contextKey)
            ->when(
                ! empty($filters['format']) && in_array($filters['format'], ExportFormat::values(), true),
                fn (Builder $query) => $query->where('format', $filters['format'])
            )
            ->when(
                array_key_exists('is_downloaded', $filters) && $filters['is_downloaded'] !== '' && ! is_null($filters['is_downloaded']),
                function (Builder $query) use ($filters) {
                    $downloaded = filter_var($filters['is_downloaded'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                    if ($downloaded === true) {
                        $query->whereNotNull('downloaded_at');
                    } elseif ($downloaded === false) {
                        $query->whereNull('downloaded_at');
                    }
                }
            )
            ->latest('id')
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function latest(string $resource, string $contextKey): ?Export
    {
        $source = $this->resolver->resolve($resource);
        abort_unless(auth()->user()->can($source->exportPermission()), 403, __('admin::messages.action_unauthorized'));

        return Export::query()
            ->where('user_id', auth()->id())
            ->where('resource_key', $resource)
            ->where('context_key', $contextKey)
            ->latest('id')
            ->first();
    }

    /** {@inheritDoc} */
    public function download(int $id): StreamedResponse
    {
        $export = $this->findOrFail($id);

        abort_unless($export->status === ExportStatus::Completed && $export->disk && $export->path, 409, __('export::exports.messages.not_ready'));

        if (is_null($export->downloaded_at)) {
            $export->update([
                'downloaded_at' => now(),
            ]);
        }

        return Storage::disk($export->disk)->download($export->path, $export->file_name);
    }

    /** {@inheritDoc} */
    public function findOrFail(int $id): Export
    {
        $export = Export::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $source = $this->resolver->resolve($export->resource_key);
        abort_unless(auth()->user()->can($source->exportPermission()), 403, __('admin::messages.action_unauthorized'));

        return $export;
    }
}
