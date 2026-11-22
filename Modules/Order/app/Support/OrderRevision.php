<?php

namespace Modules\Order\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Order\Models\Order;

final class OrderRevision
{
    /**
     * Include related financial state; timestamps alone cannot detect rapid edits.
     */
    public static function for(Order $order): string
    {
        $fresh = $order->fresh(['products.options.values', 'products.taxes', 'taxes', 'charges', 'discount']);

        return hash('sha256', json_encode(CheckoutInput::canonical(self::state($fresh)), JSON_THROW_ON_ERROR));
    }

    /**
     * Capture raw persisted attributes and relations without presentation-dependent accessors.
     */
    private static function state(Model $model): array
    {
        $state = $model->getAttributes();
        foreach ($model->getRelations() as $key => $relation) {
            $state[$key] = $relation instanceof Collection
                ? $relation->sortBy('id')->map(fn ($item) => self::state($item))->values()->all()
                : ($relation instanceof Model ? self::state($relation) : null);
        }

        return $state;
    }
}
