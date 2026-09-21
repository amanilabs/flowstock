<?php

use App\Models\Tenant;
use App\Models\User;

it('returns the real authenticated tenant name in the login response', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme Electronics']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    expect($response->json('user.tenant_id'))->toBe($tenant->id);
    expect($response->json('user.tenant_name'))->toBe('Acme Electronics');
});
