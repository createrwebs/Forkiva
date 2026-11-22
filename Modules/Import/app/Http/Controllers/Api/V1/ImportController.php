<?php

namespace Modules\Import\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Http\Requests\EditImportRowRequest;
use Modules\Import\Http\Requests\ListImportRowsRequest;
use Modules\Import\Http\Requests\ListImportsRequest;
use Modules\Import\Http\Requests\MapImportRequest;
use Modules\Import\Http\Requests\UploadImportRequest;
use Modules\Import\Http\Requests\ValidateImportRequest;
use Modules\Import\Services\Import\ImportServiceInterface;
use Modules\Import\Transformers\Api\V1\ImportResource;
use Modules\Import\Transformers\Api\V1\ImportRowResource;
use Modules\Support\ApiResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(private ImportServiceInterface $service) {}

    /**
     * List fields the actor may map for the selected resource.
     */
    public function fields(Request $request, string $resource): JsonResponse
    {
        return ApiResponse::success($this->service->fields($resource, $request->only('menu_id')));
    }

    /**
     * Download a template for an authorized resource.
     */
    public function template(string $resource): BinaryFileResponse
    {
        return $this->service->template($resource);
    }

    /**
     * List the actor's imports within the requested resource context.
     */
    public function index(ListImportsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $context = isset($data['menu_id']) ? ['menu_id' => $data['menu_id']] : [];

        return ApiResponse::pagination(
            paginator: $this->service->paginate($data['resource'], $context),
            resource: ImportResource::class,
        );
    }

    /**
     * Store an upload after validating its resource-specific context.
     */
    public function upload(UploadImportRequest $request, string $resource): JsonResponse
    {
        $data = $request->validated();
        $import = $this->service->upload($resource, $data['file'], $data['context'] ?? []);

        return ApiResponse::created((new ImportResource($import))->resolve());
    }

    /**
     * Show an import still owned and accessible by the actor.
     */
    public function show(int $id): JsonResponse
    {
        return ApiResponse::success((new ImportResource($this->service->find($id)))->resolve());
    }

    /**
     * Return a bounded page of rows from an authorized import.
     */
    public function rows(ListImportRowsRequest $request, int $id): JsonResponse
    {
        $paginator = $this->service->rows($id, (int) $request->validated('per_page', 50));

        return ApiResponse::pagination($paginator, ImportRowResource::class);
    }

    /**
     * Replace a mapping and invalidate any earlier staging revision.
     */
    public function map(MapImportRequest $request, int $id): JsonResponse
    {
        $import = $this->service->map($this->service->find($id), $request->validated('mapping'));

        return ApiResponse::success((new ImportResource($import))->resolve());
    }

    /**
     * Correct one preview row before it is applied.
     */
    public function editRow(EditImportRowRequest $request, int $id, int $rowId): JsonResponse
    {
        $row = $this->service->editRow($this->service->find($id), $rowId, $request->validated('data'));

        return ApiResponse::success((new ImportRowResource($row))->resolve());
    }

    /**
     * Begin validation for the selected import behavior.
     */
    public function validateImport(ValidateImportRequest $request, int $id): JsonResponse
    {
        $import = $this->service->validate(
            $this->service->find($id),
            ImportMode::from($request->validated('mode')),
        );

        return ApiResponse::success((new ImportResource($import))->resolve());
    }

    /**
     * Queue an import whose rows all passed validation.
     */
    public function queue(int $id): JsonResponse
    {
        $import = $this->service->queue($this->service->find($id));

        return ApiResponse::success((new ImportResource($import))->resolve());
    }

    /**
     * Resume an import from its persisted progress.
     */
    public function advance(int $id): JsonResponse
    {
        return ApiResponse::success((new ImportResource($this->service->advance($this->service->find($id))))->resolve());
    }

    /**
     * Resume an import from its persisted progress.
     */
    public function retry(int $id): JsonResponse
    {
        $import = $this->service->retry($this->service->find($id));

        return ApiResponse::success((new ImportResource($import))->resolve());
    }

    /**
     * Cancel an import before row application begins.
     */
    public function cancel(int $id): JsonResponse
    {
        $import = $this->service->cancel($this->service->find($id));

        return ApiResponse::success((new ImportResource($import))->resolve());
    }

    /**
     * Download the actor's annotated validation workbook.
     */
    public function errors(int $id): StreamedResponse
    {
        return $this->service->download($this->service->find($id), true);
    }

    /**
     * Download the actor's original uploaded file.
     */
    public function download(int $id): StreamedResponse
    {
        return $this->service->download($this->service->find($id));
    }
}
