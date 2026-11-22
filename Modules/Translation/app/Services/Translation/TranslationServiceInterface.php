<?php

namespace Modules\Translation\Services\Translation;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

interface TranslationServiceInterface
{
    /**
     * Label for the resource.
     */
    public function label(): string;

    /**
     * Display a listing of the translations.
     */
    public function retrieve(): array;

    /**
     * Display a paginated listing of translations.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator;

    /**
     * Get application translations.
     */
    public function getAppTranslations(): array;

    /**
     * Get employee application translations only.
     */
    public function getEmployeeAppTranslations(): array;

    /**
     * Get driver application translations only.
     */
    public function getDriverAppTranslations(): array;

    /**
     * Get application translations version.
     */
    public function getAppTranslationsVersion(): string;

    /**
     * Update the specified translation in storage.
     *
     * @throws ModelNotFoundException
     */
    public function update(string $key, string $locale, string $value): bool;
}
