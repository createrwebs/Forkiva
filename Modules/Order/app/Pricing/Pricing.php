<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonException;
use JsonSerializable;
use Modules\Branch\Models\Branch;
use Modules\Discount\Models\Discount;
use Modules\Loyalty\Models\LoyaltyGift;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Order\Pricing\Traits\PricingInitOrder;
use Modules\Pos\Transformers\Api\V1\Pos\PosProductResource;
use Modules\Product\Models\Product;
use Modules\Product\Services\ChosenProductOptions;
use Modules\Support\Money;
use Modules\Tax\Enums\TaxType;
use Modules\Tax\Models\Tax;
use Modules\User\Models\User;
use Modules\Voucher\Models\Voucher;

class Pricing extends PricingState implements Arrayable, JsonSerializable
{
    use PricingInitOrder;

    /**
     * Pricing Branch
     */
    protected ?PricingBranch $branch = null;

    protected array $computedCache = [];

    /**
     * Get the calculation instance for the current request.
     */
    public function instance(): static
    {
        return $this;
    }

    /**
     * Discard derived values after the calculation inputs change.
     */
    public function refresh(): static
    {
        $this->resetComputedState();

        return $this;
    }

    protected function resetComputedState(): void
    {
        $this->computedCache = [];
        $this->branch = null;
    }

    /**
     * Clear the calculation and all attached conditions (e.g., taxes, discounts).
     */
    public function clear(): void
    {
        $this->mutate(function () {
            parent::clear();
            $this->clearConditions();
            $this->resetComputedState();
        });
    }

    /**
     * Change request-local inputs and invalidate derived values; persisted orders are locked by checkout.
     */
    public function mutate(callable $callback): mixed
    {
        $this->resetComputedState();
        try {
            return $callback($this);
        } finally {
            $this->resetComputedState();
        }
    }

    /**
     * Add or store a product in the calculation.
     */
    public function store(
        int $productId,
        int $qty,
        array $options = [],
        ?OrderProduct $orderProduct = null,
        ?LoyaltyGift $gift = null,
        ?string $lineKey = null,
        ?Product $resolvedProduct = null
    ): ?PricingItem {
        return $this->mutate(function () use ($productId, $qty, $options, $orderProduct, $gift, $lineKey, $resolvedProduct) {
            $this->resetComputedState();
            $options = array_filter($options, fn ($value) => $value !== null && $value !== '' && $value !== []);

            /** @var Product $product */
            // Reuse only the matching product resolved within the current request.
            $product = $resolvedProduct && (int) $resolvedProduct->id === $productId
                ? $resolvedProduct
                : Product::findOrFail($productId);
            $product->loadMissing(['files', 'categories', 'taxes', 'menu', 'options.values']);
            $productSnapshot = PricingProductSnapshot::fromProduct($product, $this->branch()?->currency());

            $existingItems = $this->getContent();

            if ($existingItems->isNotEmpty()) {
                $existingBranchIds = $existingItems
                    ->pluck('attributes.branch_id')
                    ->unique()
                    ->filter();

                abort_if(
                    $existingBranchIds->count() > 1 || ((int) $existingBranchIds->first() !== (int) $product->menu->branch_id),
                    400,
                    __('cart::messages.mixed_branch_not_allowed')
                );

                if (! is_null($gift)) {
                    $giftAlreadyAdded = $existingItems
                        ->pluck('attributes.loyalty_gift.id')
                        ->unique()
                        ->filter();

                    abort_if(
                        $giftAlreadyAdded->count(),
                        400,
                        __('cart::messages.gift_already_added')
                    );
                }
            }

            $attributes = [
                'line_id' => $lineKey,
                'option_input' => $options,
                'catalog_product' => (new PosProductResource($product))->resolve(),
                'product' => $productSnapshot,
                'branch_id' => $productSnapshot->branch_id,
                'item' => $productSnapshot,
                'options' => (new ChosenProductOptions($product, $options))->getEntities(),
            ];

            $sellingPrice = $product->selling_price->amount();
            $uniqueId = "product_id.$productId";

            if (! is_null($orderProduct)) {
                if ($orderProduct->status !== OrderProductStatus::Pending) {
                    $uniqueId .= "status:{$orderProduct->status->value}:order_product_id:$orderProduct->id";
                }
            }

            if (! is_null($gift)) {
                $uniqueId .= "loyalty_gift_id:$gift->id";
                $attributes['loyalty_gift'] = [
                    'id' => $gift->id,
                ];
                $sellingPrice = 0;
            }

            if (! is_null($lineKey)) {
                $uniqueId .= ":line_key:$lineKey";
                $attributes['source_line_key'] = $lineKey;
            }

            $uniqueId = md5("$uniqueId:options.".serialize($options));

            if (! is_null($orderProduct)) {
                $attributes['order_product'] = [
                    'id' => $orderProduct->id,
                    'status' => $orderProduct->status,
                ];
            }

            $this->add([
                'id' => $uniqueId,
                'name' => $product->name,
                'price' => $sellingPrice,
                'quantity' => $qty,
                'attributes' => $attributes,
            ]);
            $this->resetComputedState();

            return $this->item($uniqueId);
        });
    }

    /**
     * Get branch for this calculation
     */
    public function branch(): ?PricingBranch
    {
        return $this->rememberComputed('branch', function () {
            if (isset($this->branch)) {
                return $this->branch;
            }

            return $this->branch = new PricingBranch($this->getConditionsByType('branch')->first());
        });
    }

    protected function rememberComputed(string $key, callable $resolver): mixed
    {
        if (! array_key_exists($key, $this->computedCache)) {
            $this->computedCache[$key] = $resolver();
        }

        return $this->computedCache[$key];
    }

    /**
     * Get total count of items (unique calculation lines, not quantity sum).
     */
    public function count(): int
    {
        return $this->items()->count();
    }

    /**
     * Retrieve calculation items in the same order supplied by the client.
     *
     * @return Collection<PricingItem>
     */
    public function items(): Collection
    {
        return $this->rememberComputed('items', fn () => $this->getContent()
            ->map(fn ($item) => new PricingItem($this, $item))
            ->values());
    }

    /**
     * Retrieve one calculation item by its storage id.
     */
    public function item(?string $id): ?PricingItem
    {
        if (is_null($id)) {
            return null;
        }

        $item = $this->get($id);

        return is_null($item) ? null : new PricingItem($this, $item);
    }

    /**
     * Get the configured inclusive global taxes embedded in catalog prices.
     *
     * @return Collection<Tax>
     */
    public function globalInclusiveTaxes(): Collection
    {
        return $this->rememberComputed(
            'global_inclusive_taxes',
            fn () => $this->configuredGlobalTaxes()
                ->filter(fn (Tax $tax) => $tax->type === TaxType::Inclusive)
                ->values()
        );
    }

    /**
     * Resolve active global taxes represented by the calculation's tax conditions.
     *
     * @return Collection<Tax>
     */
    private function configuredGlobalTaxes(): Collection
    {
        return $this->rememberComputed('configured_global_taxes', function () {
            $taxConditions = $this->getConditionsByType('tax');

            if ($taxConditions->isEmpty() || ! $this->hasBranch()) {
                return collect();
            }

            $branch = $this->branch();

            return Tax::query()
                ->whereIn('id', $this->getTaxIds($taxConditions))
                ->withOutGlobalBranchPermission()
                ->where(function ($query) use ($branch) {
                    $query->where('branch_id', $branch->id())
                        ->orWhereNull('branch_id');
                })
                ->orderBy('compound')
                ->orderBy('id')
                ->global()
                ->get();
        });
    }

    /**
     * Determine if the calculation is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items()->isEmpty();
    }

    /**
     * Determine if calculation has branch or not
     */
    public function hasBranch(): bool
    {
        return $this->getConditionsByType('branch')->isNotEmpty();
    }

    /**
     * Extract tax  IDs from the given conditions.
     *
     * @param  Collection<PricingCondition>  $taxConditions
     * @return Collection<int>
     */
    private function getTaxIds(Collection $taxConditions): Collection
    {
        return $taxConditions->map(fn (PricingCondition $c) => $c->getAttribute('tax_id'));
    }

    /**
     * Update the quantity of a specific calculation item.
     */
    public function updateQuantity(string $id, int $qty): ?PricingItem
    {
        return $this->mutate(function () use ($id, $qty) {
            $this->resetComputedState();
            $this->update($id, [
                'quantity' => [
                    'relative' => false,
                    'value' => $qty,
                ],
            ]);

            return $this->item($id);
        });
    }

    public function update($id, $data): bool
    {
        return $this->mutate(function () use ($id, $data) {
            $this->resetComputedState();

            return parent::update($id, $data);
        });
    }

    public function remove($id): bool
    {
        return $this->mutate(function () use ($id) {
            $this->resetComputedState();

            return parent::remove($id);
        });
    }

    /**
     * Convert the calculation to JSON string.
     *
     * @throws JsonException
     */
    public function __toString(): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items(),
            'quantity' => $this->getTotalQuantity(),
            'subTotal' => $this->subTotal(),
            'orderType' => $this->orderType(),
            'taxes' => $this->taxes(),
            'charges' => $this->charges(),
            'discount' => $this->discount(),
            'customer' => $this->customer(),
            'deliveryContext' => $this->deliveryContext(),
            'order' => $this->order(),
            'total' => $this->total(),
        ];
    }

    /**
     * Get the subtotal including option prices.
     */
    public function subTotal(): Money
    {
        return $this->rememberComputed('sub_total', fn () => new Money(
            $this->items()->sum(fn (PricingItem $item) => $item->subtotal()->amount()),
            $this->branch()->currency()
        ));
    }

    public function orderType(): PricingOrderType
    {
        return $this->rememberComputed('order_type', fn () => new PricingOrderType($this->getConditionsByType('order_type')->first()));
    }

    /**
     * Retrieve all applied tax details as PricingTax objects.
     *
     * @return Collection<PricingTax>
     */
    public function taxes(): Collection
    {
        return $this->rememberComputed('taxes', function () {
            if (! $this->hasTax()) {
                return collect();
            }

            $taxes = $this->configuredGlobalTaxes();

            $base = $this->subTotal()->amount();
            $result = collect();

            foreach ($taxes as $tax) {
                if ($tax->type === TaxType::Inclusive) {
                    $amount = $base * (((float) ($tax->rate ?? 0)) / 100);

                    $result->push(
                        new PricingTax(
                            cart: $this,
                            tax: $tax,
                            currency: $this->branch()->currency(),
                            preCalculatedAmount: $amount
                        )
                    );

                    continue;
                }

                $rate = (float) ($tax->rate ?? 0);
                $taxableBase = $tax->compound
                    ? $base + $result->sum(fn (PricingTax $cartTax) => $cartTax->amount()->amount())
                    : $base;
                $amount = $taxableBase * ($rate / 100);

                $result->push(
                    new PricingTax(
                        cart: $this,
                        tax: $tax,
                        currency: $this->branch()->currency(),
                        preCalculatedAmount: $amount
                    )
                );
            }

            return $result->values();
        });
    }

    /**
     * Check if any tax condition exists in the calculation.
     */
    public function hasTax(): bool
    {
        return $this->getConditionsByType('tax')->isNotEmpty();
    }

    /**
     * @return Collection<PricingCharge>
     */
    public function charges(): Collection
    {
        return $this->rememberComputed('charges', fn () => $this->getConditionsByType('charge')
            ->map(fn (PricingCondition $condition) => new PricingCharge($condition))
            ->values());
    }

    /**
     * Get discount
     */
    public function discount(): PricingDiscount
    {
        return $this->rememberComputed('discount', function () {
            if (! $this->hasDiscount()) {
                return new PricingDiscount;
            }

            /** @var PricingCondition $discountCondition */
            $discountCondition = $this->getConditionsByType('discount')->first();

            $cartDiscount = new PricingDiscount(
                $this,
                resolve($discountCondition->getAttribute('model'))::query()
                    ->find($discountCondition->getAttribute('discount_id')),
                $discountCondition
            );

            if (! $cartDiscount->isAvailable()) {
                $this->removeDiscount();

                return new PricingDiscount;
            }

            return $cartDiscount;
        });
    }

    /**
     * Determine if calculation has discount or not
     */
    public function hasDiscount(): bool
    {
        if ($this->getConditionsByType('discount')->isEmpty()) {
            return false;
        }

        /** @var PricingCondition $discount */
        $discount = $this->getConditionsByType('discount')
            ->first();

        return resolve($discount->getAttribute('model'))::query()
            ->where('id', $discount->getAttribute('discount_id'))
            ->exists();
    }

    /**
     * Remove Discount from calculation
     */
    public function removeDiscount(): void
    {
        $this->mutate(function () {
            $this->removeConditionsByType('discount');
            $this->resetComputedState();
        });
    }

    public function removeConditionsByType($type): ?Pricing
    {
        return $this->mutate(function () use ($type) {
            $this->resetComputedState();

            return parent::removeConditionsByType($type);
        });
    }

    public function customer(): ?PricingCustomer
    {
        return $this->rememberComputed('customer', fn () => $this->hasCustomer()
            ? new PricingCustomer($this->getConditionsByType('customer')->first())
            : null);
    }

    public function hasCustomer(): bool
    {
        return $this->getConditionsByType('customer')->isNotEmpty();
    }

    public function deliveryContext(): ?PricingDeliveryContext
    {
        return $this->rememberComputed('delivery_context', fn () => $this->hasDeliveryContext()
            ? new PricingDeliveryContext($this->getConditionsByType('delivery_context')->first())
            : null);
    }

    public function hasDeliveryContext(): bool
    {
        return $this->getConditionsByType('delivery_context')->isNotEmpty();
    }

    /**
     * Get the total including taxes.
     */
    public function total(): Money
    {
        return $this->rememberComputed('total', fn () => $this->subTotal()
            ->subtract(subtrahend: $this->discount()->value())
            ->add($this->productsTaxesPrice())
            ->add($this->tax())
            ->add($this->chargesTotal()));
    }

    /**
     * Calculate the total price of all product's taxes price.
     */
    private function productsTaxesPrice(): Money
    {
        return $this->rememberComputed('products_taxes_price', fn () => new Money($this->calculateProductsTaxesPrice(), $this->branch()->currency()));
    }

    /**
     * Compute the numeric total of all products taxes.
     */
    private function calculateProductsTaxesPrice(): float
    {
        return $this->items()->sum(fn (PricingItem $item) => $item->taxTotal()->amount());
    }

    /**
     * Calculate total tax value for the calculation.
     */
    public function tax(): Money
    {
        return $this->rememberComputed('tax', fn () => new Money($this->calculateTax(), $this->branch()->currency()));
    }

    /**
     * Compute numeric tax amount.
     */
    private function calculateTax(): float
    {
        return $this->taxes()->sum(fn (PricingTax $cartTax) => $cartTax->amount()->amount());
    }

    public function chargesTotal(): Money
    {
        return $this->rememberComputed('charges_total', fn () => new Money(
            $this->charges()->sum(fn (PricingCharge $charge) => $charge->amount()->amount()),
            $this->branch()->currency()
        ));
    }

    /**
     * Determine if calculation has order type or not
     */
    public function hasOrderType(): bool
    {
        return $this->getConditionsByType('order_type')->isNotEmpty();
    }

    public function addBranch(Branch $branch): PricingBranch
    {
        return $this->mutate(function () use ($branch) {
            $this->removeBranch();
            $this->removeDeliveryContext();
            $this->removeCharges();

            $this->condition(
                new PricingCondition([
                    'name' => $branch->name,
                    'type' => 'branch',
                    'value' => $branch->id,
                    'attributes' => [
                        'currency' => $branch->currency,
                    ],
                ]),
            );

            $this->resetComputedState();

            return $this->branch();
        });
    }

    public function removeBranch(): void
    {
        $this->mutate(function () {
            $this->removeConditionsByType('branch');
            $this->resetComputedState();
        });
    }

    public function removeDeliveryContext(): void
    {
        $this->mutate(function () {
            $this->removeConditionsByType('delivery_context');
            $this->resetComputedState();
        });
    }

    public function removeCharges(): void
    {
        $this->mutate(function () {
            $this->removeConditionsByType('charge');
            $this->resetComputedState();
        });
    }

    public function condition($condition): Pricing
    {
        return $this->mutate(function () use ($condition) {
            $this->resetComputedState();

            return parent::condition($condition);
        });
    }

    public function addOrderType(OrderType $type): PricingOrderType
    {
        return $this->mutate(function () use ($type) {
            $this->removeOrderType();

            if ($type !== OrderType::Delivery) {
                $this->removeDeliveryContext();
                $this->removeCharges();
            }

            $this->condition(
                new PricingCondition([
                    'name' => $type->trans(),
                    'type' => 'order_type',
                    'value' => $type->value,
                ]),
            );

            $this->addTaxes();
            $this->resetComputedState();

            return $this->orderType();
        });
    }

    public function removeOrderType(): void
    {
        $this->mutate(function () {
            $this->removeConditionsByType('order_type');
            $this->resetComputedState();
        });
    }

    /**
     * Add tax conditions to the calculation.
     */
    public function addTaxes(): void
    {
        $this->mutate(function () {
            $this->removeTaxes();

            $this->findTaxes()
                ->each(function (Tax $tax) {
                    $this->condition(
                        new PricingCondition([
                            'name' => $tax->name,
                            'type' => 'tax',
                            'target' => 'total',
                            'value' => ($tax->type === TaxType::Inclusive ? '-' : '+')."$tax->rate%",
                            'order' => $tax->compound ? 5 : 3,
                            'attributes' => [
                                'tax_id' => $tax->id,
                                'type' => $tax->type,
                                'compound' => $tax->compound,
                                'is_global' => $tax->is_global,
                            ],
                        ])
                    );
                });
            $this->resetComputedState();
        });
    }

    /**
     * Remove all tax conditions from the calculation.
     */
    public function removeTaxes(): void
    {
        $this->mutate(function () {
            $this->removeConditionsByType('tax');
            $this->resetComputedState();
        });
    }

    /**
     * Find applicable taxes for all calculation items.
     *
     * @return Collection<Tax>
     */
    private function findTaxes(): Collection
    {
        $branch = $this->branch();

        return Tax::query()
            ->withOutGlobalBranchPermission()
            ->where(function ($q) use ($branch) {
                $q->where('branch_id', $branch?->id())
                    ->orWhereNull('branch_id');
            })
            ->global()
            ->get()
            ->filter(fn (Tax $tax) => $this->isTaxApplicable($tax))
            ->values();
    }

    /**
     * Check if a given tax should apply based on type/order type/status.
     */
    public function isTaxApplicable(Tax $tax): bool
    {
        $orderType = $this->orderType();

        if (empty($tax->order_types)) {
            return true;
        }

        if (is_null($orderType->value())) {
            return false;
        }

        return in_array($orderType->value(), $tax->order_types);
    }

    /**
     * Determine if discount already applied
     */
    public function discountAlreadyApplied(Discount|Voucher $discount): bool
    {
        $cartDiscount = $this->discount();

        return $cartDiscount->id() === $discount->id && $cartDiscount->model()->getMorphClass() === $discount->getMorphClass();
    }

    public function applyDiscount(Discount|Voucher $discount, ?LoyaltyGift $gift = null): void
    {
        $this->mutate(function () use ($discount, $gift) {
            $this->removeDiscount();

            $morphClass = $discount->getMorphClass();
            $attributes = [
                'discount_id' => $discount->id,
                'model' => $morphClass,
            ];

            if (! is_null($gift)) {
                $attributes['loyalty_gift'] = [
                    'id' => $gift->id,
                ];
            }

            $this->condition(
                new PricingCondition([
                    'name' => $morphClass === Voucher::class ? $discount->code : $discount->name,
                    'type' => 'discount',
                    'target' => 'total',
                    'value' => $this->getDiscountValue($discount),
                    'order' => 2,
                    'attributes' => $attributes,
                ]),
            );
            $this->resetComputedState();
        });
    }

    /**
     * Get discount
     */
    private function getDiscountValue(Discount|Voucher $discount): string
    {
        if ($discount->type->isPercent()) {
            return "-$discount->value%";
        }

        return "-{$discount->value->convert($this->branch()->currency())->amount()}";
    }

    public function addCustomer(User $customer): PricingCustomer
    {
        return $this->mutate(function () use ($customer) {
            $this->removeCustomer();
            $this->removeDeliveryContext();
            $this->removeCharges();

            $this->condition(
                new PricingCondition([
                    'name' => "$customer->name ($customer->phone)",
                    'type' => 'customer',
                    'target' => 'total',
                    'value' => 0,
                    'attributes' => [
                        'customer' => $customer,
                    ],
                ]),
            );

            $this->resetComputedState();

            return $this->customer();
        });
    }

    public function removeCustomer(): void
    {
        $this->mutate(function () {
            $this->removeConditionsByType('customer');
            $this->removeDeliveryContext();
            $this->removeCharges();
            $this->resetComputedState();
        });
    }

    public function addCharge(string $code, string|array $name, float $amount, array $meta = []): PricingCharge
    {
        return $this->mutate(function () use ($code, $name, $amount, $meta) {
            $conditionName = "charge:$code";
            $this->removeCondition($conditionName);

            $translations = [];

            if (is_array($name)) {
                $translations = $name;
            } else {
                foreach (supportedLocaleKeys() as $locale) {
                    $translations[$locale] = $name;
                }
            }

            $label = $translations[locale()] ?? $translations[setting('default_locale', 'en')] ?? reset($translations);

            $this->condition(
                new PricingCondition([
                    'name' => $conditionName,
                    'type' => 'charge',
                    'target' => 'total',
                    'value' => $amount,
                    'order' => 9,
                    'attributes' => [
                        'code' => $code,
                        'label' => $label,
                        'translations' => $translations,
                        'amount' => $amount,
                        'currency' => $this->branch()->currency(),
                        'meta' => $meta,
                    ],
                ]),
            );

            $this->resetComputedState();

            /** @var PricingCondition $condition */
            $condition = $this->getCondition($conditionName);

            return new PricingCharge($condition);
        });
    }

    /**
     * Remove calculation condition
     */
    public function removeCondition($conditionName): void
    {
        $this->mutate(function () use ($conditionName) {
            if (! $this->getConditions()->has($conditionName)) {
                return;
            }

            parent::removeCondition($conditionName);
            $this->resetComputedState();
        });
    }

    public function removeCharge(string $code): void
    {
        $this->mutate(function () use ($code) {
            $this->removeCondition("charge:$code");
            $this->resetComputedState();
        });
    }

    public function putDeliveryContext(array $attributes): PricingDeliveryContext
    {
        return $this->mutate(function () use ($attributes) {
            $this->removeDeliveryContext();

            $this->condition(
                new PricingCondition([
                    'name' => 'delivery_context',
                    'type' => 'delivery_context',
                    'value' => 0,
                    'order' => 11,
                    'attributes' => $attributes,
                ]),
            );

            $this->resetComputedState();

            /** @var PricingCondition $condition */
            $condition = $this->getCondition('delivery_context');

            return new PricingDeliveryContext($condition);
        });
    }

    public function addCurrentOrder(Order $order): PricingCurrentOrder
    {
        return $this->mutate(function () use ($order) {
            $this->removeCurrentOrder();

            $this->condition(
                new PricingCondition([
                    'name' => $order->reference_no,
                    'type' => 'current_order',
                    'value' => $order->id,
                ]),
            );

            $this->resetComputedState();

            return $this->currentOrder();
        });
    }

    public function removeCurrentOrder(): void
    {
        $this->mutate(function () {
            $this->removeConditionsByType('current_order');
            $this->resetComputedState();
        });
    }

    public function currentOrder(): ?PricingCurrentOrder
    {
        return $this->rememberComputed('current_order', fn () => $this->hasCurrentOrder()
            ? new PricingCurrentOrder($this->getConditionsByType('current_order')->first())
            : null);
    }

    public function hasCurrentOrder(): bool
    {
        return $this->getConditionsByType('current_order')->isNotEmpty();
    }
}
