<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

it('rejects a token older than the configured expiration', function () {
    config(['sanctum.expiration' => 43200]); // 30 days, matches production default

    $tenant = Tenant::factory()->create();
    (new RoleSeeder)->run();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $user->assignRole('Admin');

    $token = $user->createToken('test');
    $token->accessToken->forceFill(['created_at' => now()->subDays(31)])->save();

    $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/v1/products');

    $response->assertStatus(401);
});

it('accepts a fresh token within the expiration window', function () {
    config(['sanctum.expiration' => 43200]);

    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $this->getJson('/api/v1/products')->assertOk();
});

it('never expires tokens when expiration is disabled', function () {
    config(['sanctum.expiration' => null]);

    $tenant = Tenant::factory()->create();
    (new RoleSeeder)->run();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $user->assignRole('Admin');

    $token = $user->createToken('test');
    $token->accessToken->forceFill(['created_at' => now()->subYears(5)])->save();

    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/v1/products')
        ->assertOk();
});
