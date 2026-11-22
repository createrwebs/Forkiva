<?php

namespace Modules\DailyFinance\Transformers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\DailyFinance\Models\DayClosing;
use Modules\Support\Enums\DateTimeFormat;

/** @mixin DayClosing */
class DayClosingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snapshot = $this->snapshot;
        // Viewing a historical review must never reveal expense data to a newly restricted actor.
        if (! auth()->user()->can('admin.accounting_reports.index')) {
            $snapshot['summary']['expenses'] = null;
            $snapshot['summary']['permissions']['expenses'] = false;
        }

        return [
            'id' => $this->id,
            'reference' => $this->reference_no,
            'branch_id' => $this->branch_id,
            'date' => $this->date->toDateString(),
            'date_formatted' => dateTimeFormat(Carbon::parse($this->date->toDateString(), $this->timezone), DateTimeFormat::Date, timezone: $this->timezone),
            'status' => ['id' => 'closed', 'name' => __('dailyfinance::day_closings.closed')],
            'created_at' => dateTimeFormat($this->created_at, timezone: $this->timezone),
            'created_by' => ['id' => $this->created_by, 'name' => $this->relationLoaded('createdBy') ? ($this->createdBy?->name ?? '') : ''],
            'notes' => $this->notes,
            'summary' => new DailyFinanceResource($snapshot['summary']),
            'checks' => $snapshot['checks'],
        ];
    }
}
