<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait CachesTenantScopedLists
{
    /**
     * Cache a tenant-scoped index() result, keyed by every query param that
     * affects the result set. 10-minute TTL is a safety net only — the real
     * invalidation mechanism is the tag flush in store/update/destroy.
     */
    private function rememberTenantList(string $resource, Request $request, \Closure $callback): mixed
    {
        $tenantId = $request->user()->tenant_id;
        $key = "tenant:{$tenantId}:{$resource}:".md5($request->getQueryString() ?? '');

        return Cache::tags($this->tenantListTag($resource, $tenantId))
            ->remember($key, now()->addMinutes(10), $callback);
    }

    private function flushTenantList(string $resource, int $tenantId): void
    {
        Cache::tags($this->tenantListTag($resource, $tenantId))->flush();
    }

    private function tenantListTag(string $resource, int $tenantId): string
    {
        return "tenant:{$tenantId}:{$resource}";
    }
}
