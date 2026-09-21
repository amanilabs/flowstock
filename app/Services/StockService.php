<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Events\LowStockDetected;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Scopes\TenantScope;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Atomically record a stock movement and keep product_stock.quantity in sync.
     *
     * @throws InsufficientStockException
     */
    public function recordMovement(
        Product $product,
        Warehouse $warehouse,
        int $quantityChange,
        StockMovementType $type,
        ?string $note = null,
        ?Model $reference = null,
        ?int $userId = null,
    ): StockMovement {
        if ($quantityChange === 0) {
            throw new \InvalidArgumentException('quantityChange cannot be zero.');
        }

        return DB::transaction(function () use ($product, $warehouse, $quantityChange, $type, $note, $reference, $userId) {
            $stock = $this->lockStockRow($product, $warehouse);

            $oldQuantity = $stock->quantity;
            $newQuantity = $oldQuantity + $quantityChange;

            if ($newQuantity < 0) {
                throw new InsufficientStockException(
                    "Insufficient stock for product #{$product->id} at warehouse #{$warehouse->id}: ".
                    "requested change {$quantityChange}, available {$stock->quantity}."
                );
            }

            $stock->update(['quantity' => $newQuantity]);

            $movement = StockMovement::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => $type,
                'quantity_change' => $quantityChange,
                'note' => $note,
                'user_id' => $userId ?? auth()->id(),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);

            // Only fire on the actual crossing — not on every movement while
            // already below the threshold, and never on a restock.
            if ($oldQuantity > $stock->reorder_point && $newQuantity <= $stock->reorder_point) {
                event(new LowStockDetected($product, $warehouse, $product->tenant_id, $oldQuantity, $newQuantity, $stock->reorder_point));
            }

            return $movement;
        });
    }

    /**
     * Fetch-and-lock the product_stock row for this product/warehouse pair,
     * creating it (quantity 0) on first use. Bypasses TenantScope deliberately
     * so this service stays correct outside an authenticated HTTP request
     * (queue workers, artisan commands). Public so OrderService can reuse the
     * same lock when validating/reserving stock at order-confirm time.
     */
    public function lockStockRow(Product $product, Warehouse $warehouse): ProductStock
    {
        $query = fn () => ProductStock::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $product->tenant_id)
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id);

        $stock = $query()->lockForUpdate()->first();

        if ($stock) {
            return $stock;
        }

        try {
            ProductStock::withoutGlobalScope(TenantScope::class)->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => 0,
            ]);
        } catch (QueryException) {
            // Lost the race to a concurrent request creating the same row —
            // fall through and lock the row it inserted.
        }

        return $query()->lockForUpdate()->firstOrFail();
    }

    /**
     * Set the reorder point for a single product/warehouse pair, creating
     * the product_stock row (quantity 0) if nothing has ever been stocked
     * there yet. This is a settings change, not a movement — no
     * StockMovement row, no LowStockDetected event.
     */
    public function setReorderPoint(Product $product, Warehouse $warehouse, int $reorderPoint): ProductStock
    {
        return DB::transaction(function () use ($product, $warehouse, $reorderPoint) {
            $stock = $this->lockStockRow($product, $warehouse);
            $stock->update(['reorder_point' => $reorderPoint]);

            return $stock;
        });
    }
}
