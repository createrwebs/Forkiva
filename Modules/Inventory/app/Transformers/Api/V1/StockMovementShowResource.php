<?php

namespace Modules\Inventory\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Contracts\FinancialPostingStatusReaderInterface;
use Modules\Inventory\Models\StockMovement;

/** @mixin StockMovement */
class StockMovementShowResource extends JsonResource
{
    /**
     * Transform the committed movement into its audit representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unit = $this->relationLoaded('unit') ? $this->unit : null;
        $branch = $this->relationLoaded('branch') ? $this->branch : null;
        $ingredient = $this->relationLoaded('ingredient') ? $this->ingredient : null;
        $originalMovement = $this->relationLoaded('originalMovement') ? $this->originalMovement : null;
        $reversals = $this->relationLoaded('reversals') ? $this->reversals : collect();
        $createdBy = $this->relationLoaded('createdBy') ? $this->createdBy : null;

        $unitSymbol = is_array($unit?->symbol)
            ? ucfirst($unit->getTranslation('symbol', locale()))
            : $unit?->symbol;

        return [
            'id' => $this->id,
            'branch' => [
                'id' => $this->branch_id,
                'name' => $branch?->name,
            ],
            'ingredient' => [
                'id' => $this->ingredient_id,
                'name' => $ingredient?->name,
                'symbol' => $unitSymbol,
            ],
            'type' => $this->type->toTrans(),
            'quantity' => $this->quantity,
            'display_quantity' => trim($this->quantity.($unitSymbol ? " $unitSymbol" : '')),
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'currency' => $this->currency,
            'currency_rate' => $this->currency_rate,
            'currency_rate_source' => $this->currency_rate_source,
            'currency_rate_reviewed_at' => dateTimeFormat($this->currency_rate_reviewed_at),
            'cost_snapshot_source' => $this->cost_snapshot_source,
            'cost_snapshot_remediated_at' => dateTimeFormat($this->cost_snapshot_remediated_at),
            'note' => $this->note,
            'source' => $this->source_id ? [
                'id' => $this->source_id,
                'type' => $this->source_type,
                'name' => $this->getSourceName(),
            ] : null,
            'idempotency_key' => $this->idempotency_key,
            'accounting_status' => app(FinancialPostingStatusReaderInterface::class)
                ->latest('stock_movement', $this->id, 'created'),
            'original_movement' => $originalMovement ? [
                'id' => $originalMovement->id,
                'type' => $originalMovement->type->toTrans(),
            ] : null,
            'reversals' => $reversals->map(fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'type' => $movement->type->toTrans(),
                'quantity' => $movement->quantity,
            ]),
            'created_by' => $createdBy ? [
                'id' => $createdBy->id,
                'name' => $createdBy->name,
            ] : null,
            'occurred_at' => dateTimeFormat($this->occurred_at),
            'committed_at' => dateTimeFormat($this->committed_at),
            'created_at' => dateTimeFormat($this->created_at),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }
}
