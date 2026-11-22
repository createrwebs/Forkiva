<?php

namespace Modules\Media\Services\Media;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use LogicException;
use Modules\Media\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface MediaServiceInterface
{
    /**
     * Label for the resource.
     */
    public function label(): string;

    /**
     * Model for the resource.
     */
    public function model(): string;

    /**
     * Get a new instance of the model.
     */
    public function getModel(): Media;

    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], int $skip = 0): Collection;

    /**
     * Store a newly created media in storage.
     */
    public function store(UploadedFile $file, ?int $mediaId = null): ?Media;

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id, string $name): Media;

    /**
     * Store a newly created folder media in storage.
     */
    public function storeFolder(array $data): Media;

    /**
     * Destroy resource's by given id.
     *
     * @throws ModelNotFoundException
     * @throws LogicException
     */
    public function destroy(int|array|string $ids): bool;

    /**
     * Download the file associated with the given media ID.
     *
     * This method returns a streamed response for downloading a media item
     * of type 'file' based on the provided ID.
     *
     * @param  int  $id  The ID of the media file to download.
     */
    public function downloadFile(int $id): StreamedResponse;

    /**
     * Render SVG media through the sandboxed signed preview endpoint.
     */
    public function previewSvg(int $id): Response;
}
