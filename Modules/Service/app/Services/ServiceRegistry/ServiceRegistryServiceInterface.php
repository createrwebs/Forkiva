<?php

namespace Modules\Service\Services\ServiceRegistry;

interface ServiceRegistryServiceInterface
{
    /**
     * List all registered service categories.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get one registered service.
     *
     * @return array<string, mixed>
     */
    public function show(string $category, string $service): array;

    /**
     * Build dynamic validation rules for a service settings update.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function rules(string $category, string $service, array $data): array;

    /**
     * Build dynamic validation attribute labels.
     *
     * @return array<string, string>
     */
    public function attributes(string $category, string $service): array;

    /**
     * Persist service settings and return the fresh service payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $category, string $service, array $data): array;
}
