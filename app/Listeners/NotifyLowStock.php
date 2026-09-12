<?php

namespace App\Listeners;

use App\Events\LowStockDetected;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

class NotifyLowStock implements ShouldQueue
{
    public function handle(LowStockDetected $event): void
    {
        // No HTTP request ran for this job, so SetPermissionsTeamId middleware
        // never set the team context — and a long-running worker reuses this
        // process across jobs from different tenants, so this must be set
        // per-job (from the event's own tenantId), not once at boot.
        app(PermissionRegistrar::class)->setPermissionsTeamId($event->tenantId);

        // User has no BelongsToTenant/TenantScope, so filtering by the plain
        // tenant_id column is correct and sufficient here — no need for
        // withoutGlobalScope. Any FUTURE listener querying a tenant-scoped
        // model (Product, Order, etc.) by anything other than the exact
        // instance/ID already on the event must filter tenant_id explicitly,
        // since TenantScope fails open (no filter) with no authenticated user.
        $recipients = User::where('tenant_id', $event->tenantId)
            ->get()
            ->filter(fn (User $user) => $user->hasPermissionTo('view-stock'));

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new LowStockNotification($event->product, $event->warehouse, $event->newQuantity)
        );
    }
}
