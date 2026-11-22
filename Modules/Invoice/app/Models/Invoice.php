<?php

namespace Modules\Invoice\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Invoice\Enums\InvoiceKind;
use Modules\Invoice\Enums\InvoicePartyType;
use Modules\Invoice\Enums\InvoicePurpose;
use Modules\Invoice\Enums\InvoiceStatus;
use Modules\Invoice\Enums\InvoiceType;
use Modules\Order\Models\Order;
use Modules\Payment\Models\PaymentAllocation;
use Modules\SeatingPlan\Models\TableMerge;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\User\Models\User;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * @property int $id
 * @property int|null $order_id
 * @property int|null $table_merge_id
 * @property int $seller_party_id
 * @property int|null $buyer_party_id
 * @property string $invoice_number
 * @property string $uuid
 * @property InvoiceType $type
 * @property InvoicePurpose $purpose
 * @property InvoiceKind $invoice_kind
 * @property int|null $reference_invoice_id
 * @property string $currency
 * @property float $currency_rate
 * @property Money $subtotal
 * @property Money $tax_total
 * @property Money $discount_total
 * @property Money $total
 * @property Money $rounding_adjustment
 * @property Money $paid_amount
 * @property Money $refunded_amount
 * @property Money $net_paid
 * @property InvoiceStatus $status
 * @property array|null $file_info
 * @property Carbon $issued_at
 * @property int|null $invoice_counter
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Order|null $order
 * @property-read TableMerge|null $tableMerge
 * @property-read InvoiceParty $seller
 * @property-read InvoiceParty|null $buyer
 * @property-read Invoice|null $referenceInvoice
 * @property-read Collection|Invoice[] $relatedInvoices
 * @property-read Collection|PaymentAllocation[] $allocations
 * @property-read Collection|InvoiceTax[] $taxes
 * @property-read Collection|InvoiceTax[] $allTaxes
 * @property-read Collection|InvoiceDiscount[] $discounts
 * @property-read Collection|InvoiceLine[] $lines
 */
class Invoice extends Model implements ModelExportDefinitionInterface
{
    use HasBranch, HasFilters, HasSortBy, SoftDeletes;

    /**
     * Default date column
     */
    public static string $defaultDateColumn = 'issued_at';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'table_merge_id',
        'seller_party_id',
        'buyer_party_id',
        'invoice_number',
        'uuid',
        'type',
        'purpose',
        'invoice_kind',
        'reference_invoice_id',
        'currency',
        'currency_rate',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'rounding_adjustment',
        'status',
        'issued_at',
        'invoice_counter',
        'paid_amount',
        'refunded_amount',
        'net_paid',
        'file_info',
        self::BRANCH_COLUMN_NAME,
    ];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'invoice_number', 'label' => 'invoice::invoices.table.invoice_number'],
            ['path' => 'uuid', 'label' => 'invoice::invoices.uuid'],
            ['path' => 'referenceInvoice.invoice_number', 'label' => 'invoice::invoices.show.reference_invoice', 'with' => ['referenceInvoice:id,invoice_number']],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'seller.legal_name', 'label' => 'invoice::invoices.table.seller', 'with' => ['seller:id,legal_name']],
            ['path' => 'buyer.legal_name', 'label' => 'invoice::invoices.table.buyer', 'with' => ['buyer:id,legal_name']],
            ['path' => 'type', 'label' => 'invoice::invoices.table.type'],
            ['path' => 'purpose', 'label' => 'invoice::invoices.table.purpose'],
            ['path' => 'invoice_kind', 'label' => 'invoice::invoices.table.invoice_kind'],
            ['path' => 'currency', 'label' => 'invoice::invoices.show.currency'],
            ['path' => 'currency_rate', 'label' => 'invoice::invoices.show.currency_rate'],
            ['path' => 'subtotal', 'label' => 'invoice::invoices.show.sub_total', 'type' => 'money'],
            ['path' => 'tax_total', 'label' => 'invoice::invoices.show.total_tax', 'type' => 'money'],
            ['path' => 'discount_total', 'label' => 'invoice::invoices.show.discount', 'type' => 'money'],
            ['path' => 'total', 'label' => 'invoice::invoices.table.total', 'type' => 'money'],
            ['path' => 'rounding_adjustment', 'label' => 'invoice::invoices.show.rounding_adjustment', 'type' => 'money'],
            ['path' => 'paid_amount', 'label' => 'invoice::invoices.show.paid_amount', 'type' => 'money'],
            ['path' => 'net_paid', 'label' => 'invoice::invoices.show.net_paid', 'type' => 'money'],
            ['path' => 'status', 'label' => 'invoice::invoices.table.status'],
            ['path' => 'issued_at', 'label' => 'invoice::invoices.table.issued_at', 'type' => 'datetime'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Boot the model and handle stock adjustments on create, update, and delete.
     */
    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (! $invoice->uuid) {
                $invoice->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get seller
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(InvoiceParty::class, 'seller_party_id');
    }

    /**
     * Get related invoices
     */
    public function relatedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'reference_invoice_id');
    }

    public function scopeIssued($query)
    {
        return $query->where('status', InvoiceStatus::Issued);
    }

    /**
     * Get reference invoice
     */
    public function referenceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'reference_invoice_id');
    }

    /**
     * Get buyer
     */
    public function buyer(): BelongsTo
    {
        return $this
            ->belongsTo(InvoiceParty::class, 'buyer_party_id')
            ->withDefault([
                'id' => null,
                'legal_name' => User::walkInName(),
                'type' => InvoicePartyType::Buyer,
            ]);
    }

    /**
     * Get subtotal
     *
     * @return Attribute<Money,static>
     */
    public function subtotal(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get tax total
     *
     * @return Attribute<Money,static>
     */
    public function taxTotal(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get discount total
     *
     * @return Attribute<Money,static>
     */
    public function discountTotal(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get total
     *
     * @return Attribute<Money,static>
     */
    public function total(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get rounding adjustment
     *
     * @return Attribute<Money,static>
     */
    public function roundingAdjustment(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get paid amount
     *
     * @return Attribute<Money,static>
     */
    public function paidAmount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get refunded amount
     *
     * @return Attribute<Money,static>
     */
    public function refundedAmount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get net paid
     *
     * @return Attribute<Money,static>
     */
    public function netPaid(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Get discounts
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(InvoiceDiscount::class);
    }

    /**
     * Get invoice taxes
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceTax::class)
            ->whereNull('invoice_line_id');
    }

    /**
     * Get every persisted tax for this invoice, including line taxes.
     *
     * @return HasMany<InvoiceTax, static>
     */
    public function allTaxes(): HasMany
    {
        return $this->hasMany(InvoiceTax::class);
    }

    /**
     * Get table merge
     */
    public function tableMerge(): BelongsTo
    {
        return $this->belongsTo(TableMerge::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get payment allocations
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class)
            ->with('payment:id,method,type,transaction_id');
    }

    /**
     * Get invoice lines
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(fn (Builder $query) => $query
            ->like('invoice_number', $value)
            ->orLike('uuid', $value)
            ->orWhereHas('buyer', fn (Builder $query) => $query
                ->like('legal_name', $value)
                ->orLike('vat_tin', $value)
                ->orLike('cr_number', $value))
            ->orWhereHas('seller', fn (Builder $query) => $query
                ->like('legal_name', $value)
                ->orLike('vat_tin', $value)
                ->orLike('cr_number', $value))
        );
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'type',
            'purpose',
            'invoice_kind',
            'status',
            'from',
            'to',
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Get qrcode base64
     */
    public function getQrcodeBase64(int $size = 120): string
    {
        if ($this->seller->country_code === 'SA') {
            return zatca()
                ->sellerName($this->seller->legal_name)
                ->vatRegistrationNumber($this->seller->vat_tin)
                ->timestamp($this->issued_at)
                ->totalWithVat($this->total->amount())
                ->vatTotal($this->tax_total->amount())
                ->toQrCode(
                    qrCodeOptions()
                        ->format('png')
                        ->size($size)
                );
        } else {
            return base64_encode(
                QrCode::format('png')
                    ->size($size)
                    ->generate($this->getPDFUrl())
            );
        }
    }

    /**
     * Get an SVG QR code suitable for browser-independent PDF rendering.
     */
    public function getQrCodeSvg(int $size = 120): string
    {
        if ($this->seller->country_code === 'SA') {
            return (string) zatca()
                ->sellerName($this->seller->legal_name)
                ->vatRegistrationNumber($this->seller->vat_tin)
                ->timestamp($this->issued_at)
                ->totalWithVat($this->total->amount())
                ->vatTotal($this->tax_total->amount())
                ->toQrCode(
                    qrCodeOptions()
                        ->format('svg')
                        ->size($size)
                );
        }

        return (string) QrCode::format('svg')
            ->size($size)
            ->generate($this->getPDFUrl());
    }

    /**
     * Get pdf url
     */
    public function getPDFUrl(): string
    {
        return route('invoices.pdf.index', $this->uuid);
    }

    /**
     * Get download url
     */
    public function getDownloadUrl(): string
    {
        return route('invoices.pdf.download', $this->uuid);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'type' => InvoiceType::class,
            'purpose' => InvoicePurpose::class,
            'invoice_kind' => InvoiceKind::class,
            'status' => InvoiceStatus::class,
            'file_info' => 'array',
        ];
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'invoice_number',
            self::BRANCH_COLUMN_NAME,
            'type',
            'status',
            'purpose',
            'invoice_kind',
            'issued_at',
            'total',
            'net_paid',
        ];
    }
}
