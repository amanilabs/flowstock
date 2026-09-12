<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Scopes\TenantScope;
use App\Models\StockReservation;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    private const array TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'shipped', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function __construct(private readonly StockService $stockService) {}

    /**
     * Create an order with its line items, snapshotting each item's unit
     * price and computing the total once (never recalculated afterward).
     *
     * @param  array<int, array{product_id: int, quantity: int, unit_price?: float|string}>  $items
     */
    public function createOrder(Customer $customer, Warehouse $warehouse, array $items, ?string $notes = null): Order
    {
        return DB::transaction(function () use ($customer, $warehouse, $items, $notes) {
            $order = Order::create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'order_number' => $this->nextOrderNumber($customer->tenant_id),
                'status' => OrderStatus::Pending,
                'total_amount' => 0,
                'notes' => $notes,
            ]);

            $total = '0';
            foreach ($items as $line) {
                $product = Product::findOrFail($line['product_id']);
                $unitPrice = (string) ($line['unit_price'] ?? $product->selling_price);
                $subtotal = bcmul($unitPrice, (string) $line['quantity'], 4);

                $order->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $total = bcadd($total, $subtotal, 4);
            }

            $order->update(['total_amount' => $total]);

            return $order->fresh('items');
        });
    }

    /**
     * @throws InvalidOrderTransitionException
     * @throws InsufficientStockException
     */
    public function confirmOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);
            $this->assertTransition($order, OrderStatus::Confirmed);

            $items = $order->items()->with('product')->get()->sortBy('product_id');

            foreach ($items as $item) {
                $stock = $this->stockService->lockStockRow($item->product, $order->warehouse);

                $activeReserved = StockReservation::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $order->tenant_id)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->where('status', ReservationStatus::Active)
                    ->sum('quantity');

                $available = $stock->quantity - $activeReserved;

                if ($available < $item->quantity) {
                    throw new InsufficientStockException(
                        "Insufficient available stock for product #{$item->product_id} at warehouse #{$order->warehouse_id}: ".
                        "requested {$item->quantity}, available {$available}."
                    );
                }

                StockReservation::create([
                    'tenant_id' => $order->tenant_id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $order->warehouse_id,
                    'quantity' => $item->quantity,
                    'status' => ReservationStatus::Active,
                ]);
            }

            $order->update(['status' => OrderStatus::Confirmed, 'confirmed_at' => now()]);

            return $order->fresh('items.reservation');
        });
    }

    /**
     * @throws InvalidOrderTransitionException
     */
    public function markProcessing(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);
            $this->assertTransition($order, OrderStatus::Processing);

            $order->update(['status' => OrderStatus::Processing]);

            return $order;
        });
    }

    /**
     * @throws InvalidOrderTransitionException
     */
    public function shipOrder(Order $order, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $userId) {
            $order = $this->lockOrder($order);
            $this->assertTransition($order, OrderStatus::Shipped);

            foreach ($order->items as $item) {
                $reservation = StockReservation::withoutGlobalScope(TenantScope::class)
                    ->where('order_item_id', $item->id)
                    ->where('status', ReservationStatus::Active)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->stockService->recordMovement(
                    product: $item->product,
                    warehouse: $order->warehouse,
                    quantityChange: -$item->quantity,
                    type: StockMovementType::Sold,
                    note: "Shipped on order {$order->order_number}",
                    reference: $item,
                    userId: $userId,
                );

                $reservation->update(['status' => ReservationStatus::Fulfilled]);
            }

            $order->update(['status' => OrderStatus::Shipped, 'shipped_at' => now()]);

            return $order->fresh('items.reservation');
        });
    }

    /**
     * @throws InvalidOrderTransitionException
     */
    public function markDelivered(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lockOrder($order);
            $this->assertTransition($order, OrderStatus::Delivered);

            $order->update(['status' => OrderStatus::Delivered, 'delivered_at' => now()]);

            return $order;
        });
    }

    /**
     * @throws InvalidOrderTransitionException
     */
    public function cancelOrder(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = $this->lockOrder($order);
            $this->assertTransition($order, OrderStatus::Cancelled);

            foreach ($order->items as $item) {
                StockReservation::withoutGlobalScope(TenantScope::class)
                    ->where('order_item_id', $item->id)
                    ->where('status', ReservationStatus::Active)
                    ->lockForUpdate()
                    ->get()
                    ->each(fn (StockReservation $r) => $r->update(['status' => ReservationStatus::Released]));
            }

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'notes' => $reason ? trim(($order->notes ?? '')."\nCancelled: {$reason}") : $order->notes,
            ]);

            return $order->fresh('items.reservation');
        });
    }

    /**
     * @throws InvalidOrderTransitionException
     */
    public function refundOrder(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = $this->lockOrder($order);
            $this->assertTransition($order, OrderStatus::Refunded);

            $order->update([
                'status' => OrderStatus::Refunded,
                'refunded_at' => now(),
                'notes' => $reason ? trim(($order->notes ?? '')."\nRefunded: {$reason}") : $order->notes,
            ]);

            return $order;
        });
    }

    private function assertTransition(Order $order, OrderStatus $to): void
    {
        $allowed = self::TRANSITIONS[$order->status->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw new InvalidOrderTransitionException(
                "Cannot transition order #{$order->id} from {$order->status->value} to {$to->value}."
            );
        }
    }

    private function lockOrder(Order $order): Order
    {
        return Order::withoutGlobalScope(TenantScope::class)
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function nextOrderNumber(int $tenantId): string
    {
        $sequence = DB::table('order_number_sequences')->where('tenant_id', $tenantId)->lockForUpdate()->first();

        if (! $sequence) {
            try {
                DB::table('order_number_sequences')->insert([
                    'tenant_id' => $tenantId,
                    'next_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException) {
                // Lost the race to a concurrent order creation for the same
                // tenant — fall through and lock the row it inserted.
            }

            $sequence = DB::table('order_number_sequences')->where('tenant_id', $tenantId)->lockForUpdate()->first();
        }

        DB::table('order_number_sequences')
            ->where('tenant_id', $tenantId)
            ->update(['next_number' => $sequence->next_number + 1, 'updated_at' => now()]);

        return sprintf('ORD-%06d', $sequence->next_number);
    }
}
