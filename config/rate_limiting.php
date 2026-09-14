<?php

return [
    /*
     * Generous per-tenant limit for normal business API traffic. Every user
     * belonging to the same tenant shares this one bucket, so one tenant
     * maxing it out never affects another tenant's requests.
     */
    'per_tenant_per_minute' => env('API_RATE_LIMIT_PER_TENANT', 300),
];
