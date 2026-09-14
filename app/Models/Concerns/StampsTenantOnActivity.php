<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Activity;

/**
 * Stamps every activity log entry for this model with the record's own
 * tenant_id. Spatie's activity_log table has no tenant concept by default;
 * this is the hook point (called automatically if it exists — see
 * LogActivityAction::beforeActivityLogged in the vendor package) that makes
 * every logged entry tenant-scoped, ready for a future audit-log endpoint
 * to filter on the same way every other list endpoint does.
 */
trait StampsTenantOnActivity
{
    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
    }
}
