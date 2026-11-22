<?php

namespace Modules\GiftCard\Services\GiftCard;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\GiftCard\Models\GiftCard;
use Modules\Support\Money;
use Throwable;

/**
 * Contract for gift card catalog, balance, and operational actions.
 */
interface GiftCardServiceInterface extends TableExportSourceInterface
{
    /**
     * Get the human-readable module label.
     */
    public function label(): string;

    /**
     * Get the fully qualified gift card model class name.
     *
     * @return class-string<GiftCard>
     */
    public function model(): string;

    /**
     * Create a new gift card model instance.
     */
    public function getModel(): GiftCard;

    /**
     * Get paginated gift cards using filters and sorting options.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>|null  $sorts
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Show a single gift card by database identifier, UUID, or supported lookup key.
     */
    public function show(int|string $id): GiftCard;

    /**
     * Resolve a gift card or fail by database identifier, UUID, or code.
     */
    public function findOrFail(int|string $id): Builder|array|EloquentCollection|GiftCard;

    /**
     * Store a new gift card record.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function store(array $data): GiftCard;

    /**
     * Update an existing gift card record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int|string $id, array $data): GiftCard;

    /**
     * Delete one or more gift cards.
     */
    public function destroy(int|array|string $ids): bool;

    /**
     * Get structure filters for gift card listing screens.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStructureFilters(): array;

    /**
     * Get metadata required to build gift card forms.
     *
     * @return array<string, mixed>
     */
    public function getFormMeta(?int $branchId = null): array;

    /**
     * Get the current balance payload for a gift card.
     *
     * @return array<string, mixed>
     */
    public function balance(int|string $id): array;

    /**
     * Resolve the frozen amount of default/base currency represented by one
     * unit of the supplied Gift Card currency.
     */
    public function currencyRateToDefault(string $currency): string;

    /**
     * Convert an order-currency amount into the target gift card currency.
     */
    public function convertOrderAmountToGiftCardAmount(
        GiftCard $giftCard,
        int|float|string $amount,
        string $orderCurrency,
        int|float|string|null $orderCurrencyRate,
        int|float|string|null $giftCardCurrencyRate = null,
    ): Money;

    /**
     * Shared table query used by pagination and export.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>|null  $sorts
     * @param  array<string, mixed>  $context
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
