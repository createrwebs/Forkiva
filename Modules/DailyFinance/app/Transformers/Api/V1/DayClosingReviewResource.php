<?php

namespace Modules\DailyFinance\Transformers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Enums\DateTimeFormat;

class DayClosingReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource,
            'date_formatted' => dateTimeFormat(Carbon::parse($this->resource['summary']['meta']['date'], $this->resource['summary']['meta']['timezone']), DateTimeFormat::Date, timezone: $this->resource['summary']['meta']['timezone']),
            'summary' => new DailyFinanceResource($this->resource['summary']),
            'closing' => $this->resource['closing'] ? new DayClosingResource($this->resource['closing']) : null,
        ];
    }
}
