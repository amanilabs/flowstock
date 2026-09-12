<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmedNotification extends Notification
{
    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order {$this->order->order_number} confirmed")
            ->greeting("Hi {$this->order->customer->name},")
            ->line("Your order {$this->order->order_number} has been confirmed.")
            ->line("Total: {$this->order->total_amount}");
    }
}
