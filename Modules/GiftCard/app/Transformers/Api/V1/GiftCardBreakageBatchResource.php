<?php

namespace Modules\GiftCard\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GiftCard\Models\GiftCardBreakageBatch;
use Modules\Support\Enums\DateTimeFormat;

/** @mixin GiftCardBreakageBatch */
class GiftCardBreakageBatchResource extends JsonResource
{
    /**
     * Transform the breakage batch into its stable administrative API shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'status' => $this->status->toTrans(),
            'currency' => $this->currency,
            'currency_rate' => $this->currency_rate,
            'total_amount' => $this->total_amount->withConvertedDefaultCurrency($this->currency_rate),
            'economic_date' => dateTimeFormat($this->economic_date, DateTimeFormat::Date),
            'review_note' => $this->review_note,
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->relationLoaded('branch') ? $this->branch?->name : null,
            ],
            'created_by' => [
                'id' => $this->created_by,
                'name' => $this->relationLoaded('createdBy') ? $this->createdBy?->name : null,
            ],
            'created_at' => dateTimeFormat($this->created_at),
            'reviewed_by' => [
                'id' => $this->reviewed_by,
                'name' => $this->relationLoaded('reviewedBy') ? $this->reviewedBy?->name : null,
            ],
            'reviewed_at' => dateTimeFormat($this->reviewed_at),
            'posted_by' => [
                'id' => $this->posted_by,
                'name' => $this->relationLoaded('postedBy') ? $this->postedBy?->name : null,
            ],
            'posted_at' => dateTimeFormat($this->posted_at),
            'reversal' => $this->relationLoaded('reversal') && $this->reversal ? [
                'id' => $this->reversal->id,
                'reference_no' => $this->reversal->reference_no,
                'status' => $this->reversal->status->toTrans(),
            ] : null,
            'reversal_of' => $this->relationLoaded('reversalOf') && $this->reversalOf ? [
                'id' => $this->reversalOf->id,
                'reference_no' => $this->reversalOf->reference_no,
                'status' => $this->reversalOf->status->toTrans(),
            ] : null,
            'lines' => GiftCardBreakageBatchLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
