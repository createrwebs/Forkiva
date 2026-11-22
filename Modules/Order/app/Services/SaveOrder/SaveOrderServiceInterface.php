<?php

namespace Modules\Order\Services\SaveOrder;

use Modules\Order\Models\Order;
use Throwable;

interface SaveOrderServiceInterface
{
    /**
     * Store a newly created resource in storage.
     *
     * @throws Throwable
     */
    public function create(array $data): Order;

    /**
     * Store a newly created order without requiring an authenticated actor.
     *
     * @throws Throwable
     */
    public function createFromSource(array $data): Order;

    /**
     * Update the specified translation in storage.
     *
     * @throws Throwable
     */
    public function update(int $id, array $data): Order;

    /**
     * Update the specified order without requiring an authenticated actor.
     *
     * @throws Throwable
     */
    public function updateFromSource(int $id, array $data): Order;
}
