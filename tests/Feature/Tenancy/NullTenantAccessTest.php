<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects a tenant-less user with 403 instead of silently showing everything', function () {
    // Regression test: TenantScope only filters `if (auth()->check() &&
    // auth()->user()->tenant_id)` — a null tenant_id previously made that
    // condition false, so the scope applied NO filter at all, showing every
    // tenant's data. EnsureUserHasTenant closes this by rejecting the
    // request before it ever reaches a query.
    $user = User::factory()->create(['tenant_id' => null]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/products')->assertForbidden();
    $this->getJson('/api/v1/orders')->assertForbidden();
});

it('rejects a tenant-less account at login before ever issuing a token', function () {
    $user = User::factory()->create([
        'tenant_id' => null,
        'password' => bcrypt('password'),
    ]);

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertUnprocessable();
});
