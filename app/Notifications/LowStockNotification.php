<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Warehouse;
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Low stock: {$this->product->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->product->name} (SKU {$this->product->sku}) at {$this->warehouse->name} has dropped to {$this->newQuantity} units.")
            ->line("Reorder point: {$this->reorderPoint}.");
    }
}
