<?php

use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns a clean 429 once a tenant exceeds its per-minute limit', function () {
    config(['rate_limiting.per_tenant_per_minute' => 2]);

    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $this->getJson('/api/v1/products')->assertOk();
    $this->getJson('/api/v1/products')->assertOk();

    $response = $this->getJson('/api/v1/products')->assertStatus(429);

    $response->assertJson(['message' => 'Too many requests. Please slow down and try again shortly.']);
    expect($response->json())->toHaveCount(1); // no trace/exception details leaked
    expect($response->headers->has('Retry-After'))->toBeTrue();
});

it('never lets one tenant exceeding its limit affect another tenant', function () {
    config(['rate_limiting.per_tenant_per_minute' => 2]);

    $tenantA = Tenant::factory()->create();
    actingAsRole('Admin', $tenantA);
    $this->getJson('/api/v1/products')->assertOk();
    $this->getJson('/api/v1/products')->assertOk();
    $this->getJson('/api/v1/products')->assertStatus(429);

    $tenantB = Tenant::factory()->create();
    actingAsRole('Admin', $tenantB);
    $this->getJson('/api/v1/products')->assertOk();
});

it('shares one rate-limit bucket across all users of the same tenant', function () {
    config(['rate_limiting.per_tenant_per_minute' => 2]);

    $tenant = Tenant::factory()->create();
    $userA = actingAsRole('Admin', $tenant);
    $userB = User::factory()->create(['tenant_id' => $tenant->id]);
    $userB->assignRole('Admin');

    $this->getJson('/api/v1/products')->assertOk();

    Sanctum::actingAs($userB, ['*']);
    $this->getJson('/api/v1/products')->assertOk();

    $response = $this->getJson('/api/v1/products')->assertStatus(429);
    expect($response->json('message'))->toBe('Too many requests. Please slow down and try again shortly.');
});

it('returns the same clean 429 shape for the login throttle', function () {
    Tenant::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/login', ['email' => 'nobody@test.com', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    $response = $this->postJson('/api/v1/login', ['email' => 'nobody@test.com', 'password' => 'wrong'])
        ->assertStatus(429);

    $response->assertJson(['message' => 'Too many requests. Please slow down and try again shortly.']);
});
