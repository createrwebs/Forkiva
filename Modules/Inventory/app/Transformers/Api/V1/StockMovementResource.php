<?php

namespace Modules\Inventory\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Models\StockMovement;

/** @mixin StockMovement */
class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $unit = $this->relationLoaded('unit') ? $this->unit : null;
        $unitSymbol = $unit
            ? (is_array($unit->symbol)
                ? ucfirst($unit->getTranslation('symbol', locale()))
                : ucfirst($unit->symbol))
            : null;

        return [
            'id' => $this->id,
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->relationLoaded('branch') ? $this->branch?->name : '',
            ],
            'ingredient' => [
                'id' => $this->ingredient_id,
                'name' => $this->relationLoaded('ingredient') ? $this->ingredient->name : '',
                'symbol' => $unitSymbol,
            ],
            'type' => $this->type->toTrans(),
            'quantity' => $this->quantity,
            'display_quantity' => trim($this->quantity.($unitSymbol ? " $unitSymbol" : '')),
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'currency' => $this->currency,
            'currency_rate' => $this->currency_rate,
            'cost_snapshot_source' => $this->cost_snapshot_source,
            'cost_snapshot_remediated_at' => dateTimeFormat($this->cost_snapshot_remediated_at),
            'note' => $this->note,
            'source' => ! is_null($this->source_id)
                ? [
                    'id' => $this->source_id,
                    'type' => $this->source_type,
                    'name' => $this->getSourceName(),
                ] : null,
            'updated_at' => dateTimeFormat($this->updated_at),
            'created_at' => dateTimeFormat($this->created_at),
        ];
    }
}
