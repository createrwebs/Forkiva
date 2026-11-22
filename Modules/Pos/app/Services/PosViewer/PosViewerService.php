<?php

namespace Modules\Pos\Services\PosViewer;

use App\Forkiva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Modules\Branch\Models\Branch;
use Modules\Category\Models\Category;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Discount\Models\Discount;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\OrderType;
use Modules\Order\Facades\Pricing;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Enums\PosSoundAction;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Transformers\Api\V1\Pos\PosCategoryResource;
use Modules\Pos\Transformers\Api\V1\Pos\PosProductResource;
use Modules\Printer\Models\PrintAgent;
use Modules\Product\Models\Product;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class PosViewerService implements PosViewerServiceInterface
{
    /** {@inheritDoc}
     * @throws InvalidConditionException
     */
    public function getConfiguration(?int $branchId = null): array
    {
        Pricing::clear();
        $user = auth()->user();
        $branch = null;
        $data = [
            'branches' => [],
            'menus' => [],
            'registers' => [],
            'order_types' => [],
            ...app(DeliveryFeatureInterface::class)->posMeta(),
            'discounts' => [],
            'branch_id' => $branchId,
            'menu_id' => null,
            'register_id' => null,
            'session_id' => null,
            'currency' => setting('default_currency'),
            'directions' => [],
            'reasons' => [],
            'sounds' => $this->soundSettings(),
        ];

        if (is_null($data['branch_id'])) {
            if ($user->assignedToBranch()) {
                $data['branches'][] = [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'currency' => $user->branch->currency,
                ];
                $branch = $user->branch;
                $data['branch_id'] = $user->branch->id;
            } else {
                $data['branches'] = Branch::list();
            }
            if ($user->can('admin.pos_cash_movements.create')) {
                $directions = PosCashDirection::toArrayTrans([PosCashDirection::Adjust->value]);
                $reasons = [];

                foreach ($directions as $direction) {
                    $reasons[$direction['id']] = array_map(
                        fn (PosCashReason $reason) => $reason->toTrans(),
                        PosCashReason::getForManageCashMovement(PosCashDirection::from($direction['id']))
                    );
                }
                $data['directions'] = $directions;
                $data['reasons'] = $reasons;
            }
        }

        if (count($data['branches']) > 0 || ! is_null($data['branch_id'])) {
            if (is_null($data['branch_id'])) {
                $data['branch_id'] = $data['branches'][0]['id'];
            }

            $branch = $branch ?? Branch::select(
                'id',
                'name',
                'order_types',
                'currency')
                ->findOrFail($data['branch_id']);

            $data['currency'] = $branch->currency;
            Pricing::addBranch($branch);

            $orderTypes = $branch->order_types ?: [];
            $isWaiter = $user->hasRole(DefaultRole::Waiter->value);
            if ($isWaiter && in_array(OrderType::DineIn->value, $orderTypes)) {
                $orderTypes = [OrderType::DineIn->value];
            } else {
                $orderTypes = app(DeliveryFeatureInterface::class)->filterSupportedOrderTypes($branch, $orderTypes);
            }

            if (! $isWaiter && count($orderTypes) > 0) {
                Pricing::addOrderType(OrderType::from($orderTypes[0]));

                if ($orderTypes[0] === OrderType::Delivery->value) {
                    app(DeliveryFeatureInterface::class)->syncPricingCharges();
                }
            }

            if (! $isWaiter) {
                $data['waiters'] = User::list($data['branch_id'], DefaultRole::Waiter);
            }

            $data['discounts'] = Discount::list($data['branch_id']);

            $data['order_types'] = array_values(array_filter(
                OrderType::toArrayTrans(),
                fn ($orderType) => in_array($orderType['id'], $orderTypes)
            ));
            $data['menus'] = Menu::list($data['branch_id'], true);
            $data['menu_id'] = $data['menus'][0]['id'] ?? null;

            $data['registers'] = PosRegister::list($data['branch_id'], true);
            $data['register_id'] = $data['registers'][0]['id'] ?? null;
            $data['session_id'] = $data['registers'][0]['session']['id'] ?? null;

            /** @var PrintAgent $agent */
            $agent = PrintAgent::whereBranch($data['branch_id'])
                ->withOutGlobalBranchPermission()
                ->whereNotNull('api_key')
                ->whereNotNull('host')
                ->whereNotNull('port')
                ->first();

            $data['agent'] = $agent ? [
                'id' => $agent->id,
                'name' => $agent->name,
                'api_key' => $agent->api_key,
                'base_url' => $agent->getBaseUrl(),
            ] : null;
        }

        if ($branch) {
            $data = [...$data, ...\Modules\Order\Support\PricingCatalog::branch($branch->id, $branch->currency)];
        }
        $data['cart'] = Pricing::instance();

        return $data;
    }

    /**
     * Return the POS sound profile without exposing unrelated application settings.
     *
     * @return array{enabled: bool, volume: int, actions: array<string, array{enabled: bool, sound: string}>}
     */
    private function soundSettings(): array
    {
        return [
            'enabled' => filter_var(setting('pos_sound_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'volume' => min(100, max(0, (int) setting('pos_sound_volume', 65))),
            'actions' => collect(PosSoundAction::cases())->mapWithKeys(fn (PosSoundAction $action) => [
                $action->value => [
                    'enabled' => filter_var(
                        setting("pos_sound_{$action->value}_enabled", true),
                        FILTER_VALIDATE_BOOLEAN
                    ),
                    'sound' => (string) setting(
                        "pos_sound_{$action->value}_sound",
                        $action->defaultSound()->value
                    ),
                ],
            ])->all(),
        ];
    }

    /** {@inheritDoc} */
    public function getMenuItems(int $menuId): array
    {
        return [
            'categories' => $this->getCategories($menuId),
            'products' => [],
        ];
    }

    /** {@inheritDoc} */
    public function getCategories(int $menuId): AnonymousResourceCollection
    {
        return Cache::tags('categories')
            ->rememberForever(
                makeCacheKey([
                    'categories',
                    "menu-$menuId",
                    'pos_tree',
                ]),
                fn () => PosCategoryResource::collection(
                    Category::query()
                        ->select(['id', 'name', 'parent_id', 'order', 'menu_id'])
                        ->with(['childrenRecursive', 'files'])
                        ->whereNull('parent_id')
                        ->whereMenu($menuId)
                        ->withOutGlobalBranchPermission()
                        ->orderBy('order')
                        ->get()
                ));
    }

    /** {@inheritDoc} */
    public function getProducts(int $menuId, array $filters = []): LengthAwarePaginator
    {
        return $this->productsQuery($menuId, $filters)
            ->paginate(Forkiva::paginate());
    }

    /** {@inheritDoc} */
    public function getAllProducts(int $menuId): AnonymousResourceCollection
    {
        return PosProductResource::collection(
            $this->productsQuery($menuId)->get()
        );
    }

    /**
     * Build the active products query shared by paginated and complete menu responses.
     *
     * @param  array<string, mixed>  $filters
     */
    private function productsQuery(int $menuId, array $filters = []): Builder
    {
        $activeCategoryIds = Category::getFullyActiveCategoryIds($menuId);
        $categoryIds = collect(Arr::wrap($filters['category_ids'] ?? []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->intersect($activeCategoryIds)
            ->values();

        return Product::query()
            ->with([
                'taxes',
                'files',
                'categories:id,menu_id',
                'options' => fn ($query) => $query->with('values'),
            ])
            ->when(
                $categoryIds->isNotEmpty(),
                fn (Builder $query) => $query->whereHas(
                    'categories',
                    fn (Builder $categoryQuery) => $categoryQuery->whereIn('id', $categoryIds)
                ),
                fn (Builder $query) => $query->where(function (Builder $visibilityQuery) use ($activeCategoryIds) {
                    $visibilityQuery
                        ->whereDoesntHave(
                            'categories',
                            fn (Builder $categoryQuery) => $categoryQuery->withoutGlobalActive()
                        )
                        ->orWhereHas(
                            'categories',
                            fn (Builder $categoryQuery) => $categoryQuery->whereIn('id', $activeCategoryIds)
                        );
                })
            )
            ->whereMenu($menuId)
            ->when(
                filled($filters['search'] ?? null),
                fn (Builder $query) => $query->search((string) $filters['search'])
            )
            ->latest();
    }

    /** {@inheritDoc} */
    public function getCustomers(array $filters = []): LengthAwarePaginator
    {
        return User::query()
            ->without('roles')
            ->select('id', 'name', 'phone', 'phone_country_iso_code')
            ->role(DefaultRole::Customer)
            ->when(
                filled($filters['branch_id'] ?? null),
                fn (Builder $query) => $query->where(
                    fn (Builder $query) => $query
                        ->whereBranch((int) $filters['branch_id'])
                        ->orWhereNull('branch_id')
                )
            )
            ->when(
                filled($filters['search'] ?? null),
                fn (Builder $query) => $query->search((string) $filters['search'])
            )
            ->orderBy('name')
            ->paginate(Forkiva::paginate())
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name
                    .(is_null($user->national_phone)
                        ? ''
                        : ' ('.str_replace(' ', '', $user->national_phone).')'),
            ]);
    }
}
