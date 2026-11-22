<?php

namespace Modules\Order\Pricing;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonException;
use JsonSerializable;
use Modules\Branch\Models\Branch;
use Modules\Category\Models\Category;
use Modules\Product\Models\Product;
use Modules\Support\Money;
use Modules\Tax\Models\Tax;
use TypeError;

class PricingProductSnapshot implements Arrayable, JsonSerializable
{
    /**
     * Product identifier.
     */
    public int $id;

    /**
     * Product display name.
     */
    public string $name;

    /**
     * Product SKU.
     */
    public ?string $sku;

    /**
     * Branch identifier resolved from the product menu.
     */
    public ?int $branch_id;

    /**
     * Expected kitchen preparation time in minutes.
     */
    public int $prep_time_minutes;

    /**
     * Currency used for product and option calculations.
     */
    public string $currency;

    /**
     * Base product price.
     */
    public Money $price;

    /**
     * Product selling price.
     */
    public Money $selling_price;

    /**
     * Minimal thumbnail object compatible with the existing calculation response.
     */
    public ?object $thumbnail;

    /**
     * Minimal category snapshots.
     *
     * @var Collection<int, object>
     */
    public Collection $categories;

    /**
     * Hydrated tax models needed by calculation tax calculation.
     *
     * @var Collection<int, Tax>
     */
    public Collection $taxes;

    /**
     * Create a product snapshot from compact storage data.
     *
     * @param  array<string, mixed>  $data
     * @return void
     *
     * @throws TypeError
     */
    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->sku = $data['sku'] ?? null;
        $this->branch_id = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        $this->prep_time_minutes = max(0, (int) ($data['prep_time_minutes'] ?? 0));
        $this->currency = $data['currency'] ?? setting('default_currency', 'JOD');
        $this->price = new Money((float) ($data['price'] ?? 0), $this->currency);
        $this->selling_price = new Money((float) ($data['selling_price'] ?? $data['price'] ?? 0), $this->currency);
        $this->thumbnail = ! empty($data['thumbnail'])
            ? (object) ['preview_image_url' => $data['thumbnail']]
            : null;
        $this->categories = collect($data['categories'] ?? [])
            ->map(fn (array|object $category) => (object) $category)
            ->values();
        $this->taxes = collect($data['taxes'] ?? [])
            ->map(fn (array|Tax $tax) => $tax instanceof Tax ? $tax : self::hydrateTax($tax))
            ->values();
    }

    /**
     * Hydrate a Tax model from compact calculation storage data.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    public static function hydrateTax(array $data): Tax
    {
        $name = $data['name'] ?? [];
        if (! is_array($name)) {
            $name = [setting('default_locale', 'en') => (string) $name];
        }

        $tax = new Tax;
        $tax->setRawAttributes([
            'id' => $data['id'] ?? null,
            'name' => json_encode($name, JSON_THROW_ON_ERROR),
            'code' => $data['code'] ?? null,
            'rate' => $data['rate'] ?? 0,
            'type' => $data['type'] ?? null,
            'compound' => $data['compound'] ?? false,
            'is_global' => $data['is_global'] ?? false,
            'order_types' => json_encode($data['order_types'] ?? [], JSON_THROW_ON_ERROR),
        ], true);

        if (array_key_exists('branch_id', $data)) {
            $branch = new Branch;
            $branch->setRawAttributes([
                'id' => $data['branch_id'],
                'currency' => $data['currency'] ?? setting('default_currency', 'JOD'),
            ], true);
            $tax->setRelation('branch', $branch);
        }

        return $tax;
    }

    /**
     * Resolve stored calculation product data into a snapshot instance.
     *
     * @param  array<string, mixed>|self|Product  $product
     *
     * @throws TypeError
     */
    public static function fromStored(array|self|Product $product, ?string $currency = null): self
    {
        if ($product instanceof self) {
            return $product;
        }

        if ($product instanceof Product) {
            return self::fromProduct($product, $currency);
        }

        return new self($product);
    }

    /**
     * Create a compact calculation product snapshot from a Product model.
     */
    public static function fromProduct(Product $product, ?string $currency = null): self
    {
        $currency ??= $product->relationLoaded('branch')
            ? ($product->branch?->currency ?: setting('default_currency', 'JOD'))
            : setting('default_currency', 'JOD');

        return new self([
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'branch_id' => $product->relationLoaded('menu') ? $product->menu?->branch_id : null,
            'prep_time_minutes' => $product->prep_time_minutes,
            'currency' => $currency,
            'price' => $product->price->amount(),
            'selling_price' => $product->selling_price->amount(),
            'thumbnail' => $product->thumbnail?->preview_image_url,
            'categories' => $product->relationLoaded('categories')
                ? $product->categories->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                ])->values()->all()
                : [],
            'taxes' => $product->relationLoaded('taxes')
                ? $product->taxes->map(fn (Tax $tax) => self::taxToArray($tax))->values()->all()
                : [],
        ]);
    }

    /**
     * Convert a Tax model into compact calculation storage data.
     *
     * @return array<string, mixed>
     */
    public static function taxToArray(Tax $tax): array
    {
        return [
            'id' => $tax->id,
            'name' => $tax->getTranslations('name'),
            'code' => $tax->code,
            'rate' => $tax->rate,
            'type' => $tax->type?->value ?? $tax->type,
            'compound' => (bool) $tax->compound,
            'is_global' => (bool) $tax->is_global,
            'branch_id' => $tax->branch_id,
            'order_types' => self::taxOrderTypes($tax),
        ];
    }

    /**
     * Read order types safely from both fresh and database-hydrated tax models.
     *
     * @return array<int, string>
     *
     * @throws JsonException
     */
    private static function taxOrderTypes(Tax $tax): array
    {
        $orderTypes = $tax->getRawOriginal('order_types');

        if (is_string($orderTypes)) {
            $orderTypes = json_decode($orderTypes, true, flags: JSON_THROW_ON_ERROR);
        }

        return is_array($orderTypes) ? $orderTypes : [];
    }

    /**
     * Convert the snapshot to JSON-serializable data.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the snapshot to compact calculation storage data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'branch_id' => $this->branch_id,
            'prep_time_minutes' => $this->prep_time_minutes,
            'currency' => $this->currency,
            'price' => $this->price->amount(),
            'selling_price' => $this->selling_price->amount(),
            'thumbnail' => $this->thumbnail?->preview_image_url,
            'categories' => $this->categories
                ->map(fn (array|object $category) => [
                    'id' => data_get($category, 'id'),
                    'slug' => data_get($category, 'slug'),
                ])
                ->values()
                ->all(),
            'taxes' => $this->taxes
                ->map(fn (Tax $tax) => self::taxToArray($tax))
                ->values()
                ->all(),
        ];
    }
}
