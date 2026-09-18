<?php

use App\Models\Product;
use App\Models\Tenant;

it('lets an admin list audit logs scoped to their own tenant', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Name']);
    $product->update(['name' => 'New Name']);

    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'Other Old']);
    $otherProduct->update(['name' => 'Other New']);

    $response = $this->getJson('/api/v1/audit-logs')->assertOk();

    $subjectIds = collect($response->json('data'))->pluck('subject_id');

    expect($subjectIds)->toContain($product->id);
    expect($subjectIds)->not->toContain($otherProduct->id);
});

it('denies Manager and Staff access to audit logs', function () {
    $tenant = Tenant::factory()->create();

    actingAsRole('Manager', $tenant);
    $this->getJson('/api/v1/audit-logs')->assertForbidden();

    actingAsRole('Staff', $tenant);
    $this->getJson('/api/v1/audit-logs')->assertForbidden();
});

it('filters audit logs by subject_type', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Name']);
    $product->update(['name' => 'New Name']);

    $response = $this->getJson('/api/v1/audit-logs?subject_type='.urlencode(Product::class))->assertOk();

    expect(collect($response->json('data'))->pluck('subject_type')->unique()->all())->toBe(['Product']);
});

it('filters audit logs by log_name', function () {
    $tenant = Tenant::factory()->create();
    $user = actingAsRole('Admin', $tenant);

    $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password']);

    $response = $this->getJson('/api/v1/audit-logs?log_name=auth')->assertOk();

    expect(collect($response->json('data'))->pluck('log_name')->unique()->all())->toBe(['auth']);
});

it('filters audit logs by date range', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Name']);
    $product->update(['name' => 'New Name']);

    $response = $this->getJson('/api/v1/audit-logs?from='.now()->addDay()->toDateString())->assertOk();

    expect($response->json('data'))->toBeEmpty();
});

it('filters audit logs by event, and reports old/new attribute_changes for an update', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Name']);
    $product->update(['name' => 'New Name']);

    $created = $this->getJson('/api/v1/audit-logs?event=created')->assertOk();
    expect(collect($created->json('data'))->pluck('event')->unique()->all())->toBe(['created']);

    $updated = $this->getJson('/api/v1/audit-logs?event=updated')->assertOk();
    expect(collect($updated->json('data'))->pluck('event')->unique()->all())->toBe(['updated']);

    $entry = collect($updated->json('data'))->firstWhere('subject_id', $product->id);
    expect($entry['attribute_changes']['old']['name'])->toBe('Old Name');
    expect($entry['attribute_changes']['attributes']['name'])->toBe('New Name');
});

it('filters audit logs by causer_search matching the causer name or email', function () {
    $tenant = Tenant::factory()->create();
    $admin = actingAsRole('Admin', $tenant);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $product->update(['name' => 'Renamed']);

    $byName = $this->getJson('/api/v1/audit-logs?causer_search='.urlencode($admin->name))->assertOk();
    expect($byName->json('data'))->not->toBeEmpty();
    expect(collect($byName->json('data'))->pluck('causer.id')->unique()->all())->toBe([$admin->id]);

    $byNoMatch = $this->getJson('/api/v1/audit-logs?causer_search=nobody-matches-this')->assertOk();
    expect($byNoMatch->json('data'))->toBeEmpty();
});
