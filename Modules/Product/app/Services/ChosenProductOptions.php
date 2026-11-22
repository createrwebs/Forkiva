<?php

namespace Modules\Product\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Modules\Option\Models\Option;
use Modules\Product\Models\Product;

/**
 * Class ChosenProductOptions
 *
 * Resolves and normalizes the selected options for a given product,
 * including both selectable options (dropdown, checkbox, etc.)
 * and text-based options (custom text input or freeform).
 *
 * This service ensures that all chosen options can be represented
 * as complete Option entities with their related OptionValue models,
 * ready to be used for pricing, cart building, and printing.
 *
 * Example use:
 *   $chosen = new ChosenProductOptions($product, [
 *       10 => [22, 23],        // Multiple-choice option IDs
 *       11 => 'No onions',     // Text-type option
 *   ]);
 *
 *   $entities = $chosen->getEntities(); // Returns a collection of Option models
 */
class ChosenProductOptions
{
    /**
     * Create a new ChosenProductOptions instance.
     *
     * @param  array<int|string, mixed>  $chosenOptions
     *                                                   An associative array of option_id => selected_value(s).
     *                                                   Example:
     *                                                   [
     *                                                   1 => [5, 6],          // Multi-select option values
     *                                                   2 => 'Custom text',   // Text input option
     *                                                   ]
     */
    public function __construct(
        protected Product $product,
        protected array $chosenOptions = []
    ) {
        // Remove empty/null options to avoid unnecessary DB queries
        $this->chosenOptions = array_filter($chosenOptions, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Retrieve all option entities (including text-based) chosen for this product.
     *
     * @return Collection<Option>
     */
    public function getEntities(): Collection
    {
        if ($this->chosenOptions === []) {
            return collect();
        }

        $this->product->loadMissing('options.values');

        return $this->product->options->filter(fn ($option) => array_key_exists($option->id, $this->chosenOptions))
            ->map(function (Option $option) {
                $selected = clone $option;
                $input = $this->chosenOptions[$option->id];
                if ($option->type->isFieldType()) {
                    $values = $option->values->take(1)->map(function ($value) use ($input) {
                        $copy = clone $value;
                        $copy->fill(['label' => $input]);

                        return $copy;
                    });
                } else {
                    $ids = array_map('strval', Arr::wrap($input));
                    $values = $option->values->filter(fn ($value) => in_array((string) $value->id, $ids, true));
                }
                $selected->setRelation('values', $values->values());

                return $selected;
            })->filter(fn ($option) => $option->values->isNotEmpty())->values();
    }
}
