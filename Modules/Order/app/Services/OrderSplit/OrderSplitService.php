<?php

namespace Modules\Order\Services\OrderSplit;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class OrderSplitService
{
    /**
     * Split a gross order total into N equal shares while strictly conserving
     * the exact total (any indivisible satang remainder is assigned deterministically
     * to the earliest shares).
     *
     * @return list<string>
     */
    public function splitEqualAmounts(string $totalAmount, int $ways, int $scale = 2): array
    {
        if ($ways < 2) {
            throw new InvalidArgumentException('Split bill requires at least 2 shares.');
        }

        $total = BigDecimal::of($totalAmount)->toScale($scale, RoundingMode::HALF_UP);
        if ($total->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException('Order total must be positive to split.');
        }

        $factor = 10 ** $scale;
        $totalMinor = $total->multipliedBy($factor)->toBigInteger()->toInt();
        $baseMinor = intdiv($totalMinor, $ways);
        $remainder = $totalMinor % $ways;

        $parts = [];
        for ($index = 0; $index < $ways; $index++) {
            $shareMinor = $baseMinor + ($index < $remainder ? 1 : 0);
            $parts[] = (string) BigDecimal::of($shareMinor)->dividedBy($factor, $scale, RoundingMode::UNNECESSARY);
        }

        return $parts;
    }
}
