<?php

namespace Modules\Pos\Services\HoldCart;

use Illuminate\Support\Collection;
use Modules\Pos\Models\PosHeldCart;

class PosHoldCartService
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function hold(int $branchId, string $label, array $items): PosHeldCart
    {
        return PosHeldCart::query()->create([
            'branch_id' => $branchId,
            'label' => $label,
            'items' => $items,
        ]);
    }

    /**
     * @return Collection<int, PosHeldCart>
     */
    public function listActive(int $branchId): Collection
    {
        return PosHeldCart::query()
            ->where('branch_id', $branchId)
            ->whereNull('resumed_at')
            ->latest('id')
            ->get();
    }

    /**
     * @return array{id: int, label: string, items: array<int, mixed>}
     */
    public function resume(int $heldCartId): array
    {
        $cart = PosHeldCart::query()->whereNull('resumed_at')->findOrFail($heldCartId);
        $cart->update(['resumed_at' => now()]);

        return [
            'id' => $cart->id,
            'label' => $cart->label,
            'items' => $cart->items,
        ];
    }
}
