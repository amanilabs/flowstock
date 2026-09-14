<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $tenantId = $request->user()?->tenant_id;

            return Limit::perMinute(config('rate_limiting.per_tenant_per_minute'))
                ->by($tenantId ? "tenant:{$tenantId}" : 'ip:'.$request->ip());
        });
    }
}
