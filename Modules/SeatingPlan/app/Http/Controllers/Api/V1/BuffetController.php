<?php

namespace Modules\SeatingPlan\Http\Controllers\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\SeatingPlan\Services\Buffet\BuffetTimerService;
use Modules\Support\ApiResponse;

class BuffetController extends Controller
{
    public function __construct(private BuffetTimerService $buffetTimer) {}

    public function evaluate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'started_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'adults' => ['required', 'integer', 'min:0'],
            'adult_price' => ['required', 'numeric', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'child_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $startedAt = CarbonImmutable::parse((string) $validated['started_at']);

        return ApiResponse::success([
            'headcount_total' => $this->buffetTimer->calculateHeadcountTotal(
                adults: (int) $validated['adults'],
                adultPrice: (string) $validated['adult_price'],
                children: (int) ($validated['children'] ?? 0),
                childPrice: (string) ($validated['child_price'] ?? '0.00')
            ),
            'timer' => $this->buffetTimer->evaluateSession(
                startedAt: $startedAt,
                durationMinutes: (int) $validated['duration_minutes'],
                now: CarbonImmutable::now()
            ),
        ]);
    }
}
