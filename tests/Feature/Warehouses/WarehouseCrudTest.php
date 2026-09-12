<?php

use App\Models\Tenant;
use App\Models\Warehouse;

it('supports full CRUD as Admin, but only Admin', function () {
    $tenant = Tenant::factory()->create();

    foreach (['Manager', 'Staff'] as $role) {
        actingAsRole($role, $tenant);
        $this->postJson('/api/v1/warehouses', [
            'name' => 'WH', 'code' => "WH-$role", 'address_line1' => '1 St',
            'city' => 'City', 'postal_code' => '000', 'country' => 'US',
        ])->assertForbidden();
    }

    actingAsRole('Admin', $tenant);
    $create = $this->postJson('/api/v1/warehouses', [
        'name' => 'Main', 'code' => 'WH-01', 'address_line1' => '1 St',
        'city' => 'City', 'postal_code' => '000', 'country' => 'US',
    ])->assertCreated();

    $id = $create->json('data.id');

    $this->putJson("/api/v1/warehouses/{$id}", ['name' => 'Main Updated'])
        ->assertOk()->assertJsonPath('data.name', 'Main Updated');

    $this->deleteJson("/api/v1/warehouses/{$id}")->assertNoContent();
});

it('enforces warehouse code uniqueness per tenant, not globally', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Warehouse::factory()->create(['tenant_id' => $tenantA->id, 'code' => 'SHARED']);

    actingAsRole('Admin', $tenantB);
    $this->postJson('/api/v1/warehouses', [
        'name' => 'WH', 'code' => 'SHARED', 'address_line1' => '1 St',
        'city' => 'City', 'postal_code' => '000', 'country' => 'US',
    ])->assertCreated();

    actingAsRole('Admin', $tenantA);
    $this->postJson('/api/v1/warehouses', [
        'name' => 'WH', 'code' => 'SHARED', 'address_line1' => '1 St',
        'city' => 'City', 'postal_code' => '000', 'country' => 'US',
    ])->assertUnprocessable();
});
