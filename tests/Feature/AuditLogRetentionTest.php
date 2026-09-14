<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

function insertActivityAt(string $description, Carbon $createdAt): void
{
    DB::table('activity_log')->insert([
        'log_name' => 'default',
        'description' => $description,
        'properties' => json_encode([]),
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

it('prunes activity log rows older than the configured retention window', function () {
    config(['activitylog.clean_after_days' => 365]);

    insertActivityAt('old entry', now()->subDays(400));
    insertActivityAt('recent entry', now()->subDays(10));

    Artisan::call('activitylog:clean', ['--force' => true]);

    expect(Activity::where('description', 'old entry')->exists())->toBeFalse();
    expect(Activity::where('description', 'recent entry')->exists())->toBeTrue();
});

it('respects a custom retention window from config', function () {
    config(['activitylog.clean_after_days' => 30]);

    insertActivityAt('outside 30 days', now()->subDays(45));
    insertActivityAt('inside 30 days', now()->subDays(5));

    Artisan::call('activitylog:clean', ['--force' => true]);

    expect(Activity::where('description', 'outside 30 days')->exists())->toBeFalse();
    expect(Activity::where('description', 'inside 30 days')->exists())->toBeTrue();
});
