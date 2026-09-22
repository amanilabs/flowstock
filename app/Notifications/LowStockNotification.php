<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Scopes\TenantScope;
use App\Models\Warehouse;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    public function __construct(
        private readonly Product $product,
        private readonly Warehouse $warehouse,
        private readonly int $newQuantity,
        private readonly int $reorderPoint,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Low stock: {$this->product->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->product->name} (SKU {$this->product->sku}) at {$this->warehouse->name} has dropped to {$this->newQuantity} units.")
            ->line("Reorder point: {$this->reorderPoint}.");
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ],
            'warehouse' => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ],
            'available_quantity' => $this->availableQuantity(),
            'reorder_point' => $this->reorderPoint,
        ]);
    }

    /**
     * The type discriminator Echo's Notification.notification() payload
     * carries — lets the frontend recognize this specific alert among any
     * other notification types broadcast on the same private user channel.
     */
    public function broadcastType(): string
    {
        return 'LowStockAlert';
    }

    /**
     * available_quantity means quantity minus active reservations
     * everywhere else in this app (see ProductStock::availableToSell /
     * InventoryResource) — newQuantity is the raw post-movement quantity,
     * so it's not the same number and this recomputes the real one rather
     * than mislabeling it.
     */
    private function availableQuantity(): int
    {
        $stock = ProductStock::withoutGlobalScope(TenantScope::class)
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        return $stock ? $stock->quantity - $stock->activeReservedQuantity() : $this->newQuantity;
    }
}
