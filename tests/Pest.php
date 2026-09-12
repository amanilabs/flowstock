<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every Feature and Unit test runs each test inside a DB transaction that's
| rolled back afterward (RefreshDatabase), against the real Postgres
| flowstock_test database configured in phpunit.xml. The concurrency test
| suite (tests/Feature/Concurrency) deliberately does NOT use this binding —
| see that directory for why.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

// Deliberately NOT bound with RefreshDatabase: this suite proves a real
// PostgreSQL row lock blocks a second, independent connection — it needs
// genuine, independently-committable transactions, which a rolled-back
// wrapping transaction would defeat. See tests/Concurrency/ for cleanup.
pest()->extend(TestCase::class)
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a tenant + a user assigned the given role within that tenant, and
 * authenticate as that user via Sanctum for the rest of the test — this
 * still exercises the real auth:sanctum + EnsureUserHasTenant +
 * SetPermissionsTeamId middleware chain, it's not a shortcut that skips it.
 */
function actingAsRole(string $role, ?Tenant $tenant = null): User
{
    // RefreshDatabase rolls back after every test, so roles/permissions
    // never persist between tests — reseed each time. RoleSeeder is
    // idempotent (firstOrCreate/syncPermissions), so this is cheap.
    (new RoleSeeder)->run();

    $tenant ??= Tenant::factory()->create();

    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    // Outside an HTTP request there's no SetPermissionsTeamId middleware to
    // do this automatically — it must happen before assignRole() since
    // Spatie's teams feature scopes the role assignment itself by team id.
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    Sanctum::actingAs($user, ['*']);

    return $user;
}
