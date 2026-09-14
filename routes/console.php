<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --force bypasses the command's production confirmation prompt, which a
// non-interactive scheduled run can never answer.
Schedule::command('activitylog:clean --force')->daily();
