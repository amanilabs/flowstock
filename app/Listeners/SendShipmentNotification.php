<?php

namespace App\Listeners;

use App\Events\OrderShipped;
use App\Notifications\OrderShippedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendShipmentNotification implements ShouldQueue
{
    public function handle(OrderShipped $event): void
    {
        $customer = $event->order->customer;

        if (! $customer?->email) {
            return;
        }

        Notification::route('mail', $customer->email)
            ->notify(new OrderShippedNotification($event->order));
    }
}
