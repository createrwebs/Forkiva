<?php

namespace Modules\Branch\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Database\Factories\BranchFactory;
use Modules\Core\Traits\HasTenant;
use Modules\Currency\Currency;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Order\Enums\OrderType;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Support\Country;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasActiveStatus;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $registration_number
 * @property string|null $vat_tin
 * @property string|null $tax_branch_code
 * @property string|null $promptpay_id
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $phone
 * @property string|null $email
 * @property string $country_code
 * @property string $timezone
 * @property string $currency
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Money $cash_difference_threshold
 * @property bool $is_main
 * @property OrderType[] $order_types
 * @property PaymentMethod[] $payment_methods
 * @property array<int, numeric>|null $quick_pay_amounts
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $country_name
 *
 * @method static Builder|self main(bool $operator = true)
 * @method static Builder|self notMain()
 *
 * @mixin Builder
 */
class Branch extends Model implements ModelExportDefinitionInterface
{
    use HasActiveStatus,
        HasActivityLog,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        HasTagsCache,
        HasTenant,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['name'];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'legal_name',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'phone',
        'email',
        'country_code',
        'cash_difference_threshold',
        'timezone',
        'currency',
        'latitude',
        'longitude',
        'is_main',
        'registration_number',
        'vat_tin',
        'tax_branch_code',
        'promptpay_id',
        'postal_code',
        'order_types',
        'payment_methods',
        'quick_pay_amounts',
        self::ACTIVE_COLUMN_NAME,
    ];

    /**
     * Ordered export column definitions.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'name', 'label' => 'branch::branches.table.name'],
            ['path' => 'legal_name', 'label' => 'branch::branches.table.legal_name'],
            ['path' => 'registration_number', 'label' => 'branch::branches.table.registration_number'],
            ['path' => 'vat_tin', 'label' => 'branch::branches.table.vat_tin'],
            ['path' => 'phone', 'label' => 'branch::branches.table.phone'],
            ['path' => 'email', 'label' => 'branch::branches.table.email'],
            ['path' => 'country_name', 'label' => 'branch::branches.table.country'],
            ['path' => 'timezone', 'label' => 'branch::branches.table.timezone'],
            ['path' => 'currency', 'label' => 'branch::branches.table.currency'],
            ['path' => 'cash_difference_threshold', 'label' => 'branch::branches.table.cash_difference_threshold', 'type' => 'money'],
            ['path' => 'address_line1', 'label' => 'branch::branches.table.address_line1'],
            ['path' => 'address_line2', 'label' => 'branch::branches.table.address_line2'],
            ['path' => 'city', 'label' => 'branch::branches.table.city'],
            ['path' => 'state', 'label' => 'branch::branches.table.state'],
            ['path' => 'postal_code', 'label' => 'branch::branches.table.postal_code'],
            ['path' => 'latitude', 'label' => 'branch::branches.table.latitude'],
            ['path' => 'longitude', 'label' => 'branch::branches.table.longitude'],
            ['path' => 'order_types', 'label' => 'branch::branches.table.order_types'],
            ['path' => 'payment_methods', 'label' => 'branch::branches.table.payment_methods'],
            ['path' => 'quick_pay_amounts', 'label' => 'branch::branches.table.quick_pay_amounts'],
            ['path' => 'is_main', 'label' => 'branch::branches.table.is_main', 'type' => 'boolean'],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get the branch list, optionally restricted to the authenticated user's assignment.
     */
    public static function list(bool $onlyAssigned = false): Collection
    {
        $branchId = $onlyAssigned && auth()->user()?->assignedToBranch()
            ? (int) auth()->user()->branch_id
            : null;

        /** @var Collection<int, array<string, mixed>> $branches */
        $branches = Cache::tags('branches')
            ->rememberForever(makeCacheKey([
                'branches',
                $branchId ? "branch-$branchId" : 'all',
                'list',
            ]),
                fn () => static::select('id', 'name', 'currency')
                    ->orderBy('is_main', 'desc')
                    ->when($branchId, fn ($query) => $query->where('id', $branchId))
                    ->latest()
                    ->get()
                    ->map(fn (Branch $branch) => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'currency' => $branch->currency,
                        'currency_precision' => Currency::subunit($branch->currency),
                    ])
            );

        return $branches;
    }

    /**
     * Get the branch list with locations, optionally restricted to the authenticated user's assignment.
     */
    public static function listWithLocation(bool $onlyAssigned = false): Collection
    {
        $branchId = $onlyAssigned && auth()->user()?->assignedToBranch()
            ? (int) auth()->user()->branch_id
            : null;

        /** @var Collection<int, array<string, mixed>> $branches */
        $branches = Cache::tags('branches')
            ->rememberForever(makeCacheKey([
                'branches',
                $branchId ? "branch-$branchId" : 'all',
                'location',
                'list',
            ]),
                fn () => static::select('id', 'name', 'latitude', 'longitude')
                    ->orderBy('is_main', 'desc')
                    ->when($branchId, fn ($query) => $query->where('id', $branchId))
                    ->latest()
                    ->get()
                    ->map(fn (Branch $branch) => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'latitude' => $branch->latitude,
                        'longitude' => $branch->longitude,
                    ])
            );

        return $branches;
    }

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }

    /**
     * Scope a query to where main.
     *
     * @noinspection PhpUnused
     */
    public function scopeMain(Builder $query, bool $operator = true): void
    {
        $query->where('is_main', $operator);
    }

    /**
     * Scope a query to where not Main.
     *
     * @noinspection PhpUnused
     */
    public function scopeNotMain(Builder $query): void
    {
        /** @var self $query */
        $query->main(false);
    }

    /**
     * Determine if a role is a main
     */
    public function isMain(): bool
    {
        return (bool) $this->is_main;
    }

    /**
     * Get country name.
     */
    public function countryName(): Attribute
    {
        return Attribute::get(fn () => $this->getCountryName());
    }

    /**
     * Get country name
     */
    public function getCountryName(): string
    {
        return Country::name($this->country_code);
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'country_code',
            'currency',
            'from',
            'to',
            'is_active',
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $query) use ($value) {
            $query->whereLikeTranslation('name', $value)
                ->orLike('phone', $value)
                ->orLike('email', $value);
        });
    }

    /**
     * Get cash difference threshold
     */
    public function cashDifferenceThreshold(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order_types' => 'array',
            'payment_methods' => 'array',
            'quick_pay_amounts' => 'array',
        ];
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return ['name'];
    }
}
