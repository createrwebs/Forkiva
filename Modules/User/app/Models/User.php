<?php

namespace Modules\User\Models;

use Arr;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\HasDatabaseNotifications;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Models\Branch;
use Modules\Branch\Traits\HasBranch;
use Modules\Core\Traits\HasTenant;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Hr\Models\Employee;
use Modules\Notification\Enums\NotificationPushTokenClient;
use Modules\Notification\Models\NotificationPushToken;
use Modules\Printer\Models\Printer;
use Modules\Service\Enums\PushNotificationProvider;
use Modules\Support\Traits\HasActiveStatus;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasProfilePhoto;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\User\Database\Factories\UserFactory;
use Modules\User\Enums\CustomerType;
use Modules\User\Enums\DefaultRole;
use Modules\User\Enums\GenderType;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Traits\HasRoles;
use Nwidart\Modules\Facades\Module;
use Yadahan\AuthenticationLog\AuthenticationLogable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string|null $email
 * @property GenderType|null $gender
 * @property string|null $password
 * @property TwoFactorMethod|null $two_factor_method
 * @property string|null $two_factor_secret
 * @property array<int, string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_used_timestep
 * @property string|null $phone_country_iso_code
 * @property string|null $phone
 * @property string|null $birthdate
 * @property string|null $note
 * @property CustomerType|null $customer_type
 * @property string|null $registration_number
 * @property string|null $vat_tin
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property Carbon|null $pdpa_consent_at
 * @property Carbon|null $pdpa_anonymized_at
 * @property array|null $profile_photo_path
 * @property string|null $national_phone
 * @property-read Branch|null $effective_branch
 * @property-read Collection<int, NotificationPushToken> $pushTokens
 * @property-read Collection<int, Passkey> $passkeys
 * @property array|null $category_slugs
 * @property int|null $printer_id
 * @property-read  Printer|null $printer
 * @property-read  Employee|null $employee
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable implements ModelExportDefinitionInterface, PasskeyUser
{
    use AuthenticationLogable,
        HasActiveStatus,
        HasActivityLog,
        HasApiTokens,
        HasBranch,
        HasCreatedBy,
        HasDatabaseNotifications,
        HasFactory,
        HasFilters,
        HasProfilePhoto,
        HasRoles,
        HasSortBy,
        HasTagsCache,
        HasTenant,
        Notifiable,
        PasskeyAuthenticatable,
        SoftDeletes;

    /**
     * Prevent alternate account-edit paths from silently redirecting an enrolled factor.
     */
    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->getOriginal('two_factor_confirmed_at') === null) {
                return;
            }

            $method = $user->getOriginal('two_factor_method');
            if ($method === TwoFactorMethod::Email && $user->isDirty('email')) {
                throw ValidationException::withMessages([
                    'email' => __('user::auth.two_factor_email_change_protected'),
                ]);
            }
            if ($method === TwoFactorMethod::Sms && $user->isDirty(['phone', 'phone_country_iso_code'])) {
                throw ValidationException::withMessages([
                    'phone' => __('user::auth.two_factor_phone_change_protected'),
                ]);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'username',
        'email',
        'password',
        'gender',
        'phone_country_iso_code',
        'phone',
        'birthdate',
        'note',
        'customer_type',
        'registration_number',
        'vat_tin',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'pdpa_consent_at',
        'profile_photo_path',
        'category_slugs',
        'printer_id',
        self::BRANCH_COLUMN_NAME,
        self::ACTIVE_COLUMN_NAME,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['roles'];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'name', 'label' => 'user::users.table.name'],
            ['path' => 'username', 'label' => 'user::users.table.username'],
            ['path' => 'email', 'label' => 'user::users.table.email'],
            ['path' => 'gender', 'label' => 'user::users.table.gender'],
            ['path' => 'role_names', 'label' => 'user::users.table.role_names'],
            ['path' => 'printer.name', 'label' => 'user::users.table.printer', 'with' => ['printer:id,name']],
            ['path' => 'category_slugs', 'label' => 'user::users.table.category_slugs'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a list of all suppliers.
     *
     * @param  DefaultRole|array<DefaultRole>|null  $defaultRole
     * @param  DefaultRole|array<DefaultRole>|null  $withoutDefaultRole
     */
    public static function list(
        ?int $branchId = null,
        DefaultRole|array|null $defaultRole = null,
        DefaultRole|array|null $withoutDefaultRole = null,
        bool $withDetails = false,
    ): Collection {
        $defaultRoles = collect(Arr::wrap($defaultRole))
            ->flatten()
            ->map(fn (DefaultRole $role) => $role->value)
            ->values();

        $withoutDefaultRoles = collect(Arr::wrap($withoutDefaultRole))
            ->flatten()
            ->map(fn (DefaultRole $role) => $role->value)
            ->values();

        return Cache::tags('users')->rememberForever(
            makeCacheKey(
                [
                    'users',
                    $branchId ? "branch-$branchId" : 'all',
                    $defaultRoles->isNotEmpty()
                        ? 'default-role-'.$defaultRoles->implode(',')
                        : 'default-role-all',
                    $withoutDefaultRoles->isNotEmpty()
                        ? 'without-default-role-'.$withoutDefaultRoles->implode(',')
                        : 'without-default-role-all',
                    $withDetails ? 'with-details' : 'basic',
                    'list',
                ],
                false
            ),
            fn () => static::query()
                ->without('roles')
                ->select('id', 'name', 'email', 'phone', 'phone_country_iso_code')
                ->when(
                    $branchId,
                    fn ($query) => $query
                        ->where('branch_id', $branchId)
                        ->orWhereNull('branch_id')
                )
                ->when(
                    $defaultRoles->isNotEmpty(),
                    fn ($query) => $query->whereHas(
                        'roles',
                        fn ($q) => $q->whereIn('name', $defaultRoles)
                    )
                )
                ->when(
                    $withoutDefaultRoles->isNotEmpty(),
                    fn ($query) => $query->whereDoesntHave(
                        'roles',
                        fn ($q) => $q->whereIn('name', $withoutDefaultRoles)
                    )
                )
                ->get()
                ->map(fn (User $user) => array_filter([
                    'id' => $user->id,
                    'name' => $withDetails
                        ? $user->name
                        : $user->name
                        .(is_null($user->national_phone)
                            ? ''
                            : ' ('.str_replace(' ', '', $user->national_phone).')'),
                    'email' => $withDetails ? $user->email : null,
                    'phone' => $withDetails ? $user->phone : null,
                    'national_phone' => $withDetails ? $user->national_phone : null,
                    'phone_country_iso_code' => $withDetails ? $user->phone_country_iso_code : null,
                ], fn ($value) => ! is_null($value)))
        );
    }

    /**
     * Get walk-in Name
     */
    public static function walkInName(): string
    {
        return 'Walk-In Customer';
    }

    /**
     * Create a new instance of the factory for generating AcademicYear models.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Disable direct authentication-log package notifications.
     *
     * @return array<int, string>
     */
    public function notifyAuthenticationLogVia(): array
    {
        return [];
    }

    /**
     * Route FCM notifications to stored device tokens.
     *
     * @return array<int, string>
     */
    public function routeNotificationForFcm(?object $notification = null): array
    {
        $clients = $this->pushClientsFor($notification);

        if ($this->relationLoaded('pushTokens')) {
            return $this->pushTokens
                ->filter(fn (NotificationPushToken $token) => $this->pushTokenMatches($token, $clients))
                ->pluck('token')
                ->values()
                ->all();
        }

        return $this->pushTokens()
            ->where('provider', PushNotificationProvider::Fcm->value)
            ->when(
                $clients,
                fn (Builder $query, array $clients) => $query->whereIn('client', $clients)
            )
            ->pluck('token')
            ->all();
    }

    /**
     * Resolve notification push client scopes when the notification supports it.
     *
     * @return array<int, string>|null
     */
    protected function pushClientsFor(?object $notification): ?array
    {
        if (! $notification) {
            return null;
        }

        if (method_exists($notification, 'pushTokenClients')) {
            $clients = array_values(array_filter(
                array_map('strval', (array) $notification->pushTokenClients()),
                fn (string $client) => ! blank($client)
            ));

            return $clients === [] ? null : $clients;
        }

        return null;
    }

    /**
     * Determine whether a loaded push token matches the notification route.
     *
     * @param  array<int, string>|null  $clients
     */
    protected function pushTokenMatches(NotificationPushToken $token, ?array $clients): bool
    {
        $provider = $token->provider instanceof PushNotificationProvider
            ? $token->provider->value
            : (string) $token->provider;

        if ($provider !== PushNotificationProvider::Fcm->value) {
            return false;
        }

        if ($clients === null) {
            return true;
        }

        $client = $token->client instanceof NotificationPushTokenClient
            ? $token->client->value
            : (string) $token->client;

        return in_array($client, $clients, true);
    }

    /**
     * Get push tokens.
     *
     * @return HasMany<NotificationPushToken, $this>
     */
    public function pushTokens(): HasMany
    {
        return $this->hasMany(NotificationPushToken::class);
    }

    /**
     * Get linked employee profile.
     */
    public function employee(): HasOne
    {
        if (! Module::find('Hr') && ! Module::find('hr')) {
            return $this->hasOne(self::class, 'id', 'id')->whereRaw('1 = 0');
        }

        return $this->hasOne(Employee::class, 'user_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            'role',
            'gender',
            self::ACTIVE_COLUMN_NAME,
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Determine if the main user
     */
    public function isMainUser(): bool
    {
        return $this->id === 1;
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function ($query) use ($value) {
            $query->like('name', $value)
                ->orLike('username', $value)
                ->orLike('phone', $value)
                ->orLike('email', $value);
        });
    }

    /**
     * Get effective branch
     */
    public function effectiveBranch(): Attribute
    {
        return Attribute::get(fn () => $this->assignedToBranch() ? $this->branch : Branch::main()->first());
    }

    /**
     * Determine if user assigned to branch or not
     */
    public function assignedToBranch(): bool
    {
        return array_key_exists('branch_id', $this->getAttributes())
            && ! is_null($this->getAttributes()['branch_id']);
    }

    /**
     * Route SMS notifications to this user's phone number.
     */
    public function routeNotificationForSms(?object $notification = null): ?string
    {
        return filled($this->phone) ? $this->phone : null;
    }

    /**
     * Phone attribute
     */
    public function phone(): Attribute
    {
        return Attribute::make(
            get: fn ($phone) => ! is_null($phone)
                ? phone($phone, $this->phone_country_iso_code)->formatInternational()
                : null,
        );
    }

    /**
     * National mobile number attribute
     */
    public function nationalPhone(): Attribute
    {
        return Attribute::get(
            fn () => ! is_null($this->phone)
                ? phone($this->phone, $this->phone_country_iso_code)->formatNational()
                : null
        );
    }

    /**
     * Get printer
     */
    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    protected function shortName(): Attribute
    {
        return Attribute::get(function (): string {
            $normalized = trim((string) $this->name);

            if ($normalized === '') {
                return '';
            }

            $parts = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (count($parts) <= 1) {
                return $normalized;
            }

            return $parts[0].' '.$parts[count($parts) - 1];
        });
    }

    /**
     * Get user roles as a list of display names.
     *
     * @return Attribute<array<int,string>, never>
     */
    protected function roleNames(): Attribute
    {
        return Attribute::get(fn () => $this->roles->pluck('display_name')->filter()->values()->all());
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
            'email',
            'username',
            'gender',
            'phone',
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_method' => TwoFactorMethod::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_timestep' => 'integer',
            'gender' => GenderType::class,
            'customer_type' => CustomerType::class,
            'is_active' => 'boolean',
            'birthdate' => 'date:Y-m-d',
            'pdpa_consent_at' => 'datetime',
            'pdpa_anonymized_at' => 'datetime',
            'profile_photo_path' => 'array',
            'category_slugs' => 'array',
        ];
    }
}
