<?php

namespace Modules\Product\Services\ProductOption;

use Arr;
use Illuminate\Support\Collection;
use Modules\Option\Models\Option;
use Modules\Product\Models\Product;

class ProductOptionService implements ProductOptionServiceInterface
{
    /** {@inheritDoc} */
    public function syncOptions(Product $product, array $options): void
    {
        $ids = $this->getDeleteCandidates($product, $options);

        if ($ids->isNotEmpty()) {
            $product->options()->detach($ids);
        }

        $counter = 0;

        foreach (array_reset_index($this->getOptions($options)) as $attributes) {
            $attributes['is_global'] = false;
            $attributes['order'] = ++$counter;

            $values = Arr::except($attributes, ['values', 'id']);
            $values['branch_id'] = $product->branch->id;
            /** @var Option $option */
            $option = isset($attributes['id'])
                ? $product->options()->updateOrCreate(
                    [
                        'id' => $attributes['id'],
                        'branch_id' => $values['branch_id'],
                    ],
                    $values
                )
                : $product->options()->create(Arr::except($values, ['id']));

            $option->saveValues($attributes['values'] ?? []);
        }
    }

    /**
     * Get delete candidates
     */
    protected function getDeleteCandidates(Product $product, array $options): Collection
    {
        return $product->options()
            ->pluck('id')
            ->diff(Arr::pluck($this->getOptions($options), 'id'));
    }

    /**
     * Get product options
     */
    protected function getOptions(array $options): array
    {
        return array_filter($options, fn ($option) => isset($option['name']));
    }
}
