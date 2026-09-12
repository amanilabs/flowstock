<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Notifications\OrderConfirmedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendOrderConfirmationEmail implements ShouldQueue
{
    public function handle(OrderConfirmed $event): void
    {
        $customer = $event->order->customer;

        if (! $customer?->email) {
            return;
        }

        Notification::route('mail', $customer->email)
            ->notify(new OrderConfirmedNotification($event->order));
    }
}
