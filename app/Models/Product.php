<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\StampsTenantOnActivity;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes, StampsTenantOnActivity;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'sku',
        'name',
        'description',
        'barcode',
        'unit_of_measure',
        'cost_price',
        'selling_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function stock(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['sku', 'name', 'description', 'barcode', 'unit_of_measure', 'cost_price', 'selling_price', 'is_active', 'category_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function margin(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->selling_price - $this->cost_price,
        );
    }

    protected function marginPercentage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cost_price > 0
                ? round((($this->selling_price - $this->cost_price) / $this->cost_price) * 100, 2)
                : null,
        );
    }
}
