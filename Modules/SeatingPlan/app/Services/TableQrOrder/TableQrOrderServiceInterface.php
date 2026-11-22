<?php

namespace Modules\SeatingPlan\Services\TableQrOrder;

use Illuminate\Http\Request;
use Modules\SeatingPlan\Exceptions\TableQrOrderException;

interface TableQrOrderServiceInterface
{
    /**
     * Get the public ordering context for a table QR code.
     *
     * @return array<string, mixed>
     *
     * @throws TableQrOrderException
     */
    public function context(string $uuid, ?string $token, Request $request): array;

    /**
     * Get the table and active-order tracking state for a QR code.
     *
     * @return array<string, mixed>
     *
     * @throws TableQrOrderException
     */
    public function tracking(string $uuid, ?string $token): array;

    /**
     * Calculate a QR order quote without submitting the order.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws TableQrOrderException
     */
    public function quote(string $uuid, ?string $token, array $data): array;

    /**
     * Submit a QR order for a table.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws TableQrOrderException
     */
    public function submit(string $uuid, ?string $token, array $data, Request $request): array;
}
