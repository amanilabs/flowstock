<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderService;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

function assertNoLeakedInternals(TestResponse $response): void
{
    $response->assertJsonMissingPath('exception');
    $response->assertJsonMissingPath('file');
    $response->assertJsonMissingPath('line');
    $response->assertJsonMissingPath('trace');
}

it('returns a clean 401 with no leaked internals when unauthenticated', function () {
    $response = $this->getJson('/api/v1/products')->assertStatus(401);

    $response->assertExactJson(['message' => 'Unauthenticated.']);
    assertNoLeakedInternals($response);
});

it('returns a clean 401, not a 500, for a plain unauthenticated request with no Accept header', function () {
    // Regression test: getJson()/postJson() always set Accept: application/json,
    // which masked a real bug — Laravel's skeleton defaults guest redirects to
    // route('login'), which doesn't exist in this API-only app, and only
    // AuthenticationException's ternary skips that redirect when the request
    // "expects JSON" (i.e. sent an explicit Accept header). A bare client that
    // omits it hit a raw RouteNotFoundException instead of a clean 401.
    $response = $this->get('/api/v1/products')->assertStatus(401);

    $response->assertExactJson(['message' => 'Unauthenticated.']);
    assertNoLeakedInternals($response);
});

it('returns a clean 403 with no leaked internals when unauthorized', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Staff', $tenant);

    $response = $this->postJson('/api/v1/products', [])->assertStatus(403);

    assertNoLeakedInternals($response);
    expect($response->json('message'))->toBeString();
});

it('returns a clean 404 with no model class name or internals leaked', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $response = $this->getJson('/api/v1/products/999999')->assertStatus(404);

    $response->assertExactJson(['message' => 'Not found.']);
    assertNoLeakedInternals($response);
});

it('returns a clean 409 for an invalid order transition, with no leaked internals', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $order = app(OrderService::class)->createOrder($customer, $warehouse, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    $response = $this->postJson("/api/v1/orders/{$order->id}/ship")->assertStatus(409);

    assertNoLeakedInternals($response);
    expect($response->json('message'))->toContain('Cannot transition');
});

it('keeps the standard {message, errors} shape for 422 validation failures', function () {
    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $response = $this->postJson('/api/v1/products', [])->assertStatus(422);

    $response->assertJsonStructure(['message', 'errors']);
    assertNoLeakedInternals($response);
});

it('returns a generic 500 with no leaked internals, while still logging the real exception', function () {
    Route::middleware(['auth:sanctum'])->get('/api/v1/__test-throw', function () {
        throw new RuntimeException('sensitive internal detail: db password xyz123');
    });

    $tenant = Tenant::factory()->create();
    actingAsRole('Admin', $tenant);

    $response = $this->getJson('/api/v1/__test-throw')->assertStatus(500);

    $response->assertExactJson(['message' => 'Server Error']);
    assertNoLeakedInternals($response);
    expect($response->getContent())->not->toContain('sensitive internal detail');

    $logPath = storage_path('logs/laravel.log');
    expect(file_exists($logPath))->toBeTrue();
    expect(file_get_contents($logPath))->toContain('sensitive internal detail');
});
