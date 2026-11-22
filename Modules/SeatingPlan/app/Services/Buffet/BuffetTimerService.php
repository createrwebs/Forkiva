<?php

namespace Modules\SeatingPlan\Services\Buffet;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;

class BuffetTimerService
{
    public function calculateHeadcountTotal(
        int $adults,
        string $adultPrice,
        int $children = 0,
        string $childPrice = '0.00'
    ): string {
        $adultTotal = BigDecimal::of($adultPrice)->multipliedBy(max(0, $adults));
        $childTotal = BigDecimal::of($childPrice)->multipliedBy(max(0, $children));

        return (string) $adultTotal->plus($childTotal)->toScale(2, RoundingMode::HALF_UP);
    }

    /**
     * Evaluate buffet session remaining time, 15-minute warning, and Table QR ordering lockout.
     *
     * @return array{ends_at: string, remaining_minutes: int, is_ending_soon: bool, is_ordering_locked: bool}
     */
    public function evaluateSession(
        CarbonInterface $startedAt,
        int $durationMinutes,
        CarbonInterface $now,
        int $warningThresholdMinutes = 15
    ): array {
        $endsAt = $startedAt->copy()->addMinutes($durationMinutes);
        $remaining = (int) $now->diffInMinutes($endsAt, false);
        $clamped = max(0, $remaining);

        return [
            'ends_at' => $endsAt->toIso8601String(),
            'remaining_minutes' => $clamped,
            'is_ending_soon' => $clamped > 0 && $clamped <= $warningThresholdMinutes,
            'is_ordering_locked' => $remaining <= 0,
        ];
    }
}
