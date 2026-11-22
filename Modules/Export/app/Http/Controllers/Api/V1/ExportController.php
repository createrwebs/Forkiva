<?php

namespace Modules\Export\Http\Controllers\Api\V1;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Export\Enums\ExportFormat;
use Modules\Export\Http\Requests\Api\V1\StoreExportRequest;
use Modules\Export\Services\Export\ExportServiceInterface;
use Modules\Export\Transformers\Api\V1\ExportResource;
use Modules\Support\ApiResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected ExportServiceInterface $service) {}

    /**
     * List recent exports for the active table context.
     *
     * @throws BindingResolutionException
     */
    public function index(Request $request): JsonResponse
    {
        $resource = (string) $request->input('resource');
        $contextKey = (string) $request->input('context_key');
        $filters = $request->input('filters', []);

        $latest = $this->service->latest($resource, $contextKey);

        return ApiResponse::pagination(
            paginator: $this->service->paginate($resource, $contextKey, is_array($filters) ? $filters : []),
            resource: ExportResource::class,
            extra: [
                'latest' => $latest ? (new ExportResource($latest))->resolve() : null,
            ],
        );
    }

    /**
     * Queue a new export.
     */
    public function store(StoreExportRequest $request, string $resource): JsonResponse
    {
        $validated = $request->validated();

        $export = $this->service->queue(
            resource: $resource,
            format: ExportFormat::from($validated['format']),
            filters: $validated['filters'] ?? [],
            sorts: $validated['sorts'] ?? [],
            context: $validated['context'] ?? [],
            contextKey: $validated['context_key'],
        );

        return ApiResponse::created(
            body: (new ExportResource($export))->resolve(),
            resource: __('export::exports.export'),
            message: __('export::exports.messages.queued'),
        );
    }

    /**
     * Get a single export.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            body: (new ExportResource($this->service->findOrFail($id)))->resolve()
        );
    }

    /**
     * Download the generated export file.
     */
    public function download(int $id): StreamedResponse
    {
        return $this->service->download($id);
    }
}
