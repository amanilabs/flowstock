<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStock extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'product_stock';

    protected $fillable = ['tenant_id', 'product_id', 'warehouse_id', 'quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function activeReservedQuantity(): int
    {
        return StockReservation::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->tenant_id)
            ->where('product_id', $this->product_id)
            ->where('warehouse_id', $this->warehouse_id)
            ->where('status', ReservationStatus::Active)
            ->sum('quantity');
    }

    protected function availableToSell(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->quantity - $this->activeReservedQuantity(),
        );
    }
}
