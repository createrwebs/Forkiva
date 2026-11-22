<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'monthly_price',
        'yearly_price',
        'currency',
        'limits',
        'features',
        'is_active',
    ];

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function limitFor(string $resource): ?int
    {
        $limit = ($this->limits ?? [])[$resource] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
