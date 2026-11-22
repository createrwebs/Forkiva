<?php

namespace Modules\Translation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Modules\Core\Http\Controllers\Controller;
use Modules\Support\ApiResponse;
use Modules\Translation\Http\Requests\Api\V1\ExportAllTranslationRequest;
use Modules\Translation\Http\Requests\Api\V1\ExportTranslationRequest;
use Modules\Translation\Http\Requests\Api\V1\ImportTranslationRequest;
use Modules\Translation\Http\Requests\Api\V1\IndexTranslationRequest;
use Modules\Translation\Http\Requests\Api\V1\UpdateTranslationRequest;
use Modules\Translation\Services\Translation\Export\TranslationExportServiceInterface;
use Modules\Translation\Services\Translation\Import\TranslationImportServiceInterface;
use Modules\Translation\Services\Translation\TranslationServiceInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TranslationController extends Controller
{
    /**
     * Create a new instance of TranslationController.
     */
    public function __construct(
        protected TranslationServiceInterface $service,
        protected TranslationExportServiceInterface $exportService,
        protected TranslationImportServiceInterface $importService,
    ) {}

    /**
     * Display a listing of the translations.
     */
    public function index(IndexTranslationRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $filters['status'] = $request->status()->value;

        return ApiResponse::pagination($this->service->paginate($filters));
    }

    /**
     * Update the specified translation in storage.
     */
    public function update(UpdateTranslationRequest $request, string $key): JsonResponse
    {
        $this->service->update($key, $request->validated('locale'), (string) $request->validated('value'));

        return ApiResponse::success(null, __('admin::messages.resource_updated', ['resource' => $this->service->label()]));
    }

    /**
     * Export one locale as flat JSON.
     */
    public function export(ExportTranslationRequest $request): StreamedResponse
    {
        $locale = $request->validated('_locale');
        $mode = $request->mode();
        $payload = $this->exportService->exportLocale($locale, $mode);
        $filename = "translations-$locale-$mode->value.json";

        return response()
            ->streamDownload(
                fn () => print (
                    json_encode(
                        $payload,
                        JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ).PHP_EOL
                ),
                $filename,
                ['Content-Type' => 'application/json; charset=UTF-8'],
            );
    }

    /**
     * Export all supported locales as a ZIP archive.
     */
    public function exportAll(ExportAllTranslationRequest $request): BinaryFileResponse|JsonResponse
    {
        try {
            $mode = $request->mode();
            $path = $this->exportService->exportAllLocales($mode);

            return response()
                ->download(
                    $path,
                    "translations-$mode->value.zip",
                    ['Content-Type' => 'application/zip']
                )
                ->deleteFileAfterSend();
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::errors(null, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Preview a flat JSON translation import.
     */
    public function previewImport(ImportTranslationRequest $request): JsonResponse
    {
        try {
            return ApiResponse::success(
                $this->importService->previewImport(
                    $request->validated('_locale'),
                    $request->file('file'),
                )
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::errors(['file' => [$exception->getMessage()]], $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Import a flat JSON translation file.
     */
    public function import(ImportTranslationRequest $request): JsonResponse
    {
        try {
            return ApiResponse::success(
                $this->importService->import(
                    $request->validated('_locale'),
                    $request->file('file'),
                    $request->mode(),
                ),
                __('admin::messages.resource_updated', ['resource' => $this->service->label()]),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::errors(['file' => [$exception->getMessage()]], $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Get application translations.
     */
    public function getAppTranslations(): JsonResponse
    {
        return ApiResponse::success($this->service->getAppTranslations());
    }

    /**
     * Get employee application translations only.
     */
    public function getEmployeeAppTranslations(): JsonResponse
    {
        return ApiResponse::success($this->service->getEmployeeAppTranslations());
    }

    /**
     * Get driver application translations only.
     */
    public function getDriverAppTranslations(): JsonResponse
    {
        return ApiResponse::success($this->service->getDriverAppTranslations());
    }
}
