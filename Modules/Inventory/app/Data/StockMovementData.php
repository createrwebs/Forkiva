<?php

namespace Modules\Inventory\Data;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Modules\Inventory\Enums\StockMovementType;

/**
 * Trusted input used to record an immutable stock movement.
 */
readonly class StockMovementData
{
    public string $quantity;

    public ?string $unitCost;

    public string $currencyRate;

    /**
     * Create a trusted stock movement input.
     *
     * @param  int  $branchId  Owning branch identifier.
     * @param  int  $ingredientId  Ingredient identifier in the branch stock unit.
     * @param  StockMovementType  $type  Direction and operational reason.
     * @param  int|float|string  $quantity  Positive quantity in the ingredient stock unit.
     * @param  string  $sourceType  Source model class or stable source key.
     * @param  int|string  $sourceId  Source record identifier.
     * @param  string  $idempotencyKey  Deterministic operation key.
     * @param  CarbonInterface  $occurredAt  Operational occurrence time.
     * @param  int|float|string  $currencyRate  Frozen source-to-base currency rate.
     * @param  int|null  $unitId  Trusted stock-unit snapshot for reversals and source corrections.
     * @param  int|float|string|null  $unitCost  Trusted original cost when source policy requires it.
     * @param  string|null  $currency  Trusted source currency when a cost is supplied.
     * @param  int|null  $reversalOfId  Original committed movement being reversed.
     * @param  string|null  $note  Human-readable audit note.
     * @param  bool  $emitAccounting  Whether generic movement Accounting owns the journal.
     */
    public function __construct(
        public int $branchId,
        public int $ingredientId,
        public StockMovementType $type,
        int|float|string $quantity,
        public string $sourceType,
        public int|string $sourceId,
        public string $idempotencyKey,
        public CarbonInterface $occurredAt,
        int|float|string $currencyRate,
        public ?int $unitId = null,
        int|float|string|null $unitCost = null,
        public ?string $currency = null,
        public ?int $reversalOfId = null,
        public ?string $note = null,
        public bool $emitAccounting = true,
    ) {
        $this->quantity = self::decimal($quantity);
        $this->unitCost = is_null($unitCost) ? null : self::decimal($unitCost);
        $this->currencyRate = self::decimal($currencyRate, 8);
    }

    /**
     * Normalize a decimal without crossing a binary floating-point boundary.
     */
    private static function decimal(int|float|string $value, int $scale = 6): string
    {
        return (string) BigDecimal::of((string) $value)->toScale($scale, RoundingMode::HalfUp);
    }
}
