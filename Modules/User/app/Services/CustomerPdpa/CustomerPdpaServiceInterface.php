<?php

namespace Modules\User\Services\CustomerPdpa;

use Modules\User\Models\User;

interface CustomerPdpaServiceInterface
{
    /**
     * Collect the personal data held about a customer for a PDPA access request.
     *
     * @return array<string, mixed>
     */
    public function exportPersonalData(int $id): array;

    /**
     * Erase a customer's personal data (PDPA right to erasure) while keeping the
     * record and its financial references intact for legal retention.
     */
    public function anonymize(int $id): User;
}
