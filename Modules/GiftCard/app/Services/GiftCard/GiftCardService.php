<?php

namespace Modules\GiftCard\Services\GiftCard;

use App\Forkiva;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Currency\Currency;
use Modules\Currency\Models\CurrencyRate;
use Modules\GiftCard\Enums\GiftCardFundingType;
use Modules\GiftCard\Enums\GiftCardScope;
use Modules\GiftCard\Enums\GiftCardStatus;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Events\GiftCardIssued;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Services\GiftCardCode\GiftCardCodeServiceInterface;
use Modules\GiftCard\Services\GiftCardTransaction\GiftCardTransactionServiceInterface;
use Modules\GiftCard\Support\GiftCardCurrencyRate;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Support\GlobalStructureFilters;
use Modules\Support\Money;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class GiftCardService implements GiftCardServiceInterface
{
    public function __construct(
        protected GiftCardCodeServiceInterface $codeService,
        protected GiftCardTransactionServiceInterface $transactions,
        protected FinancialAccountLookupInterface $financialAccounts,
        protected BranchAccessServiceInterface $branchAccess,
    ) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('giftcard::gift_cards.gift_card');
    }

    /** {@inheritDoc} */
    public function show(int|string $id): GiftCard
    {
        return $this->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function findOrFail(int|string $id): Builder|array|EloquentCollection|GiftCard
    {
        $numericId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $normalizedCode = $numericId === false ? $this->codeService->normalize((string) $id) : null;

        return $this->baseQuery()
            ->where(function (Builder $query) use ($id, $numericId, $normalizedCode) {
                if ($numericId !== false) {
                    $query->whereKey($numericId);

                    return;
                }

                $query->whereRaw('1 = 0');
                if (Str::isUuid((string) $id)) {
                    $query->orWhere('uuid', $id);
                }
                if (! blank($normalizedCode)) {
                    $code = $query->getQuery()->getGrammar()->wrap('code');
                    $query->orWhereRaw(
                        "REPLACE(REPLACE(UPPER($code), '-', ''), ' ', '') = ?",
                        [$normalizedCode]
                    );
                }
            })
            ->firstOrFail();
    }

    /**
     * Build the base gift card query with standard relations and visibility rules.
     */
    protected function baseQuery(): Builder
    {
        return $this->getModel()
            ->query()
            ->with([
                'branch:id,name',
                'liabilityBranch:id,name,currency',
                'customer:id,name',
                'batch:id,name',
            ]);
    }

    /** {@inheritDoc} */
    public function getModel(): GiftCard
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return GiftCard::class;
    }

    /** {@inheritDoc} */
    public function store(array $data): GiftCard
    {
        $data = $this->resolveDerivedAttributes($data);
        $data['funding_type'] ??= GiftCardFundingType::Promotional->value;

        $giftCard = DB::transaction(function () use ($data): GiftCard {
            $financialAccount = $this->resolveFinancialAccount($data);
            $giftCardAttributes = $data;
            unset(
                $giftCardAttributes['funding_type'],
                $giftCardAttributes['tender_method'],
                $giftCardAttributes['financial_account_key'],
                $giftCardAttributes['idempotency_key'],
                $giftCardAttributes['economic_date'],
            );
            $giftCard = $this->getModel()->query()->create([
                ...$giftCardAttributes,
                'liability_branch_id' => $giftCardAttributes['branch_id'] ?? null,
                'current_balance' => 0,
                'status' => GiftCardStatus::Inactive,
            ]);

            $this->transactions->record($giftCard, [
                'type' => GiftCardTransactionType::Purchase,
                'amount' => $data['initial_balance'],
                'branch_id' => $giftCard->branch_id,
                'funding_type' => $data['funding_type'],
                'tender_method' => $data['tender_method'] ?? null,
                'financial_account_key' => $data['financial_account_key'] ?? null,
                'financial_account_snapshot' => $financialAccount,
                'currency_rate' => $this->currencyRateToDefault($giftCard->currency),
                'idempotency_key' => $data['idempotency_key'] ?? "gift-card:{$giftCard->uuid}:initial",
                'economic_date' => $data['economic_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            return $giftCard->fresh();
        });

        $giftCard = $giftCard->loadMissing(['branch:id,name', 'liabilityBranch:id,name,currency', 'customer:id,name', 'batch:id,name']);

        event(new GiftCardIssued($giftCard));

        return $giftCard;
    }

    /**
     * Resolve scope and currency from the selected branch or the default currency.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveDerivedAttributes(array $data): array
    {
        $branchId = blank($data['branch_id'] ?? null) ? null : (int) $data['branch_id'];
        $branch = $branchId ? Branch::query()->find($branchId) : null;

        unset($data['gift_card_batch_id']);

        return [
            ...$data,
            'branch_id' => $branch?->id,
            'scope' => $branch ? GiftCardScope::Branch : GiftCardScope::Global,
            'currency' => $branch?->currency ?? setting('default_currency'),
        ];
    }

    /** @param array<string,mixed> $data */
    protected function resolveFinancialAccount(array $data): ?array
    {
        if (! $this->financialAccounts->available() || ($data['funding_type'] ?? null) !== GiftCardFundingType::Paid->value) {
            return null;
        }
        if (! $data['branch_id']) {
            throw ValidationException::withMessages(['branch_id' => [__('giftcard::messages.accounting_branch_required')]]);
        }
        $account = $this->financialAccounts->resolve(
            (string) ($data['financial_account_key'] ?? ''),
            (int) $data['branch_id'],
            (string) $data['currency'],
        );
        if (! $account) {
            throw ValidationException::withMessages(['financial_account_key' => [__('giftcard::messages.financial_account_invalid')]]);
        }

        return $account;
    }

    /** {@inheritDoc} */
    public function currencyRateToDefault(string $currency): string
    {
        return GiftCardCurrencyRate::toDefault($currency);
    }

    /** {@inheritDoc} */
    public function update(int|string $id, array $data): GiftCard
    {
        $giftCard = $this->findOrFail($id);
        unset($data['initial_balance'], $data['funding_type'], $data['tender_method'], $data['financial_account_key'], $data['idempotency_key'], $data['economic_date']);
        $resolved = $this->resolveDerivedAttributes($data);
        if ($giftCard->transactions()->exists()) {
            $immutableChanged = (int) ($resolved['branch_id'] ?? 0) !== (int) ($giftCard->branch_id ?? 0)
                || (string) $resolved['scope']->value !== (string) $giftCard->scope->value
                || (string) $resolved['currency'] !== (string) $giftCard->currency;
            abort_if($immutableChanged, 422, __('giftcard::messages.financial_identity_immutable'));
            unset($resolved['branch_id'], $resolved['scope'], $resolved['currency'], $resolved['liability_branch_id']);
        }
        $giftCard->update($resolved);

        return $giftCard->refresh()->loadMissing(['branch:id,name', 'liabilityBranch:id,name,currency', 'customer:id,name', 'batch:id,name']);
    }

    /** {@inheritDoc} */
    public function destroy(int|array|string $ids): bool
    {
        $cards = $this->getModel()->query()->whereIn('id', parseIds($ids))->withCount('transactions')->get();
        abort_if(
            $cards->contains(fn (GiftCard $card): bool => $card->transactions_count > 0
                || ! BigDecimal::of((string) $card->getRawOriginal('current_balance'))->isZero()
                || $card->status !== GiftCardStatus::Inactive),
            422,
            __('giftcard::messages.card_with_financial_activity_not_deletable'),
        );

        return $this->getModel()->query()->whereIn('id', $cards->pluck('id'))->delete() ?: false;
    }

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->filters($filters)
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return $this->baseQuery()
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'currency',
                'label' => __('giftcard::gift_cards.filters.currency'),
                'type' => 'select',
                'options' => Currency::supportedList(),
            ],
            [
                'key' => 'scope',
                'label' => __('giftcard::gift_cards.filters.scope'),
                'type' => 'select',
                'options' => GiftCardScope::toArrayTrans(),
            ],
            [
                'key' => 'status',
                'label' => __('giftcard::gift_cards.filters.status'),
                'type' => 'select',
                'options' => GiftCardStatus::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(?int $branchId = null): array
    {
        $branchId = $this->branchAccess->resolve($branchId);
        $branches = Branch::list(true);
        $branchCurrency = $branchId
            ? Branch::query()->whereKey($branchId)->value('currency')
            : null;

        return [
            'branches' => $branches,
            'default_currency' => setting('default_currency'),
            'statuses' => GiftCardStatus::toArrayTrans(),
            'customers' => User::list(defaultRole: DefaultRole::Customer),
            'funding_types' => GiftCardFundingType::toArrayTrans(),
            'tender_methods' => PaymentMethod::toArrayTrans(),
            'accounting_available' => $this->financialAccounts->available(),
            'financial_accounts' => $branchId
                ? $this->financialAccounts->list((int) $branchId, $branchCurrency)
                : [],
        ];
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'gift_cards';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.gift_cards.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return GiftCard::class;
    }

    /** {@inheritDoc} */
    public function balance(int|string $id): array
    {
        $giftCard = $this->findOrFail($id);

        return [
            'id' => $giftCard->id,
            'uuid' => $giftCard->uuid,
            'code' => $giftCard->code,
            'initial_balance' => $giftCard->initial_balance,
            'current_balance' => $giftCard->current_balance,
            'currency' => $giftCard->currency,
            'status' => $giftCard->status->toTrans(),
            'expiry_date' => $giftCard->expiry_date?->toDateString(),
        ];
    }

    /** {@inheritDoc} */
    public function convertOrderAmountToGiftCardAmount(
        GiftCard $giftCard,
        int|float|string $amount,
        string $orderCurrency,
        int|float|string|null $orderCurrencyRate,
        int|float|string|null $giftCardCurrencyRate = null,
    ): Money {
        $orderAmount = BigDecimal::of((string) $amount);
        if ($orderAmount->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new InvalidArgumentException(__('giftcard::messages.amount_must_be_greater_than_zero'));
        }

        if ($orderCurrency === $giftCard->currency) {
            return new Money((float) (string) $orderAmount->toScale(Currency::subunit($giftCard->currency), RoundingMode::HalfUp), $giftCard->currency);
        }

        $orderConfiguredRate = BigDecimal::of((string) ($orderCurrencyRate ?: CurrencyRate::for($orderCurrency)));
        $cardRateToDefault = BigDecimal::of((string) ($giftCardCurrencyRate ?: $this->currencyRateToDefault($giftCard->currency)));
        if ($orderConfiguredRate->isLessThanOrEqualTo(BigDecimal::zero()) || $cardRateToDefault->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new InvalidArgumentException(__('giftcard::messages.currency_rate_must_be_greater_than_zero'));
        }

        $converted = $orderAmount
            ->dividedBy($orderConfiguredRate, 12, RoundingMode::HalfUp)
            ->dividedBy($cardRateToDefault, 12, RoundingMode::HalfUp)
            ->toScale(Currency::subunit($giftCard->currency), RoundingMode::HalfUp);

        return new Money((float) (string) $converted, $giftCard->currency);
    }
}
