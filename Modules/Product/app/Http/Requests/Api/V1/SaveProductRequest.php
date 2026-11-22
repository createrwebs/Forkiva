<?php

namespace Modules\Product\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Menu\Models\Menu;
use Modules\Option\Enums\OptionType;
use Modules\Product\Enums\IngredientOperation;
use Modules\Product\Enums\ProductInventoryConsumptionMode;
use Modules\Support\Enums\PriceType;

/**
 * @property-read int $menu_id
 */
class SaveProductRequest extends Request
{
    /**
     * Menu
     */
    private ?Menu $menu = null;

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $this->menu = Menu::query()
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->where('id', $this->menu_id)
            ->first();

        return [
            ...$this->getProductRules(),
            ...$this->getProductOptionsRules(),
            ...$this->getProductIngredientsRules(),
        ];
    }

    /**
     * Get product rules
     */
    private function getProductRules(): array
    {
        return [
            ...$this->getTranslationRules([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]),
            ...$this->getMenuRule(),
            'sku' => [
                'bail',
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'sku')
                    ->where('menu_id', $this->menu_id)
                    ->ignore($this->route('id')),
            ],
            'taxes' => 'nullable|array',
            'taxes.*' => 'bail|required|exists:taxes,id,deleted_at,NULL,is_global,0',
            'price' => 'required|numeric|min:0|max:99999999999999',
            'prep_time_minutes' => 'sometimes|required|integer|min:0|max:1440',
            'special_price' => 'nullable|numeric|min:0|max:99999999999999|lt:price',
            'special_price_type' => ['required_with:special_price', Rule::enum(PriceType::class)],
            'special_price_start' => 'nullable|date',
            'special_price_end' => 'nullable|date|after:special_price_start',
            'categories' => 'nullable|array',
            'categories.*' => "bail|required|exists:categories,id,deleted_at,NULL,is_active,1,menu_id,$this->menu_id",
            'new_from' => 'nullable|date',
            'new_to' => 'nullable|date|after:new_from',
            'is_active' => 'required|boolean',
            'inventory_consumption_mode' => ['required', Rule::enum(ProductInventoryConsumptionMode::class)],
            'produced_ingredient_id' => [
                'nullable',
                Rule::requiredIf($this->input('inventory_consumption_mode') === ProductInventoryConsumptionMode::ProductionOutput->value),
                Rule::prohibitedIf($this->input('inventory_consumption_mode') !== ProductInventoryConsumptionMode::ProductionOutput->value),
                Rule::exists('ingredients', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('branch_id', $this->menu?->branch_id)),
            ],
            'produced_ingredient_quantity' => [
                'nullable',
                Rule::requiredIf($this->input('inventory_consumption_mode') === ProductInventoryConsumptionMode::ProductionOutput->value),
                Rule::prohibitedIf($this->input('inventory_consumption_mode') !== ProductInventoryConsumptionMode::ProductionOutput->value),
                'numeric',
                'gt:0',
                'max:99999.999999',
            ],
        ];
    }

    /**
     * Get product options rules
     */
    public function getProductOptionsRules(): array
    {
        return [
            'ingredients' => [
                'nullable',
                'array',
                Rule::prohibitedIf($this->input('inventory_consumption_mode') === ProductInventoryConsumptionMode::ProductionOutput->value),
            ],
            'ingredients.*.id' => 'nullable|numeric',
            'ingredients.*.ingredient_id' => "bail|nullable|exists:ingredients,id,deleted_at,NULL,branch_id,{$this->menu?->branch_id}",
            'ingredients.*.quantity' => 'required_with:ingredients.*.ingredient_id|nullable|numeric|min:0.01|max:99999.9999',
            'ingredients.*.loss_pct' => 'nullable|numeric|min:0|max:100',
            'ingredients.*.note' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get product ingredients rules
     */
    public function getProductIngredientsRules(): array
    {
        return [
            'options' => 'nullable|array',
            ...$this->getTranslationRules([
                'options.*.name' => 'required_with:options.*.type',
                'options.*.values.*.label' => 'required_if:options.*.type,select,checkbox,radio,multiple_select',
            ]),
            'options.*.id' => 'nullable|numeric',
            'options.*.type' => ['nullable', 'required_with:options.*.name', Rule::enum(OptionType::class)],
            'options.*.is_required' => 'required_with:options.*.name|boolean',
            'options.*.values.*.id' => 'nullable|numeric',
            'options.*.values.*.price' => 'nullable|numeric|min:0|max:99999999999999',
            'options.*.values.*.price_type' => ['required', Rule::enum(PriceType::class)],

            'options.*.values.*.ingredients' => [
                'nullable',
                'array',
                Rule::prohibitedIf($this->input('inventory_consumption_mode') === ProductInventoryConsumptionMode::ProductionOutput->value),
            ],
            'options.*.values.*.ingredients.*.id' => 'nullable|numeric',
            'options.*.values.*.ingredients.*.ingredient_id' => "bail|nullable|exists:ingredients,id,deleted_at,NULL,branch_id,{$this->menu?->branch_id}",
            'options.*.values.*.ingredients.*.quantity' => 'required_with:options.*.values.*.ingredients.*.ingredient_id|nullable|numeric|min:0.01|max:99999.9999',
            'options.*.values.*.ingredients.*.operation' => ['required_with:options.*.values.*.ingredients.*.ingredient_id', 'nullable', Rule::enum(IngredientOperation::class)],
            'options.*.values.*.ingredients.*.loss_pct' => 'nullable|numeric|min:0|max:100',
            'options.*.values.*.ingredients.*.note' => 'nullable|string|max:255',
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'product::attributes.products';
    }
}
