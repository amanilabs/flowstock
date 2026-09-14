<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Realistic demo data for frontend development — run explicitly with
 * `php artisan db:seed --class=DemoDataSeeder` (never wired into the
 * default seeder chain, so it never silently appears on a normal
 * `migrate --seed`). Goes through the real StockService/OrderService
 * rather than raw inserts, so stock levels, reservations, and audit
 * logs all end up in the same consistent shape they would from real
 * usage.
 */
class DemoDataSeeder extends Seeder
{
    private const TENANT_NAME = 'Acme Supply Co.';

    private const DEMO_EMAILS = ['admin@acme.test', 'manager@acme.test', 'staff@acme.test'];

    public function run(): void
    {
        // Idempotent: users.tenant_id is nullOnDelete (not cascade), so the
        // fixed demo emails must be cleared explicitly before the tenant
        // (whose cascade handles everything else) is removed and recreated.
        User::whereIn('email', self::DEMO_EMAILS)->delete();
        Tenant::where('name', self::TENANT_NAME)->delete();

        $tenant = Tenant::create([
            'name' => self::TENANT_NAME,
            'slug' => Str::slug(self::TENANT_NAME),
            'status' => 'active',
        ]);

        (new RoleSeeder)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $admin = $this->createUser($tenant, 'Ada Admin', 'admin@acme.test', 'Admin');
        $this->createUser($tenant, 'Max Manager', 'manager@acme.test', 'Manager');
        $this->createUser($tenant, 'Sam Staff', 'staff@acme.test', 'Staff');

        // Attribute the seeded activity log/order events to a real user rather
        // than leaving every entry's causer null.
        Auth::login($admin);

        $categories = collect(['Electronics', 'Office Supplies', 'Furniture'])
            ->map(fn (string $name) => ProductCategory::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'slug' => Str::slug($name),
            ]));

        $products = $categories->flatMap(fn (ProductCategory $category) => Product::factory()
            ->count(5)
            ->create(['tenant_id' => $tenant->id, 'category_id' => $category->id])
        );

        $warehouses = Warehouse::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $stockService = app(StockService::class);
        foreach ($warehouses as $warehouse) {
            foreach ($products as $product) {
                $stockService->recordMovement(
                    product: $product,
                    warehouse: $warehouse,
                    quantityChange: fake()->numberBetween(50, 200),
                    type: StockMovementType::Received,
                    note: 'Initial demo stock',
                    userId: $admin->id,
                );
            }
        }

        $customers = Customer::factory()->count(6)->create(['tenant_id' => $tenant->id]);
        $primaryWarehouse = $warehouses->first();
        $orderService = app(OrderService::class);

        $orderFor = fn () => $orderService->createOrder(
            $customers->random(),
            $primaryWarehouse,
            $products->random(fake()->numberBetween(1, 3))
                ->map(fn (Product $product) => ['product_id' => $product->id, 'quantity' => fake()->numberBetween(1, 4)])
                ->values()->all(),
        );

        // Pending: just created, no further action.
        $orderFor();
        $orderFor();

        // Confirmed.
        $orderService->confirmOrder($orderFor());
        $orderService->confirmOrder($orderFor());

        // Processing.
        $orderService->markProcessing($orderService->confirmOrder($orderFor()));

        // Shipped.
        $orderService->shipOrder($orderService->confirmOrder($orderFor()), $admin->id);
        $orderService->shipOrder($orderService->markProcessing($orderService->confirmOrder($orderFor())), $admin->id);

        // Delivered.
        $orderService->markDelivered($orderService->shipOrder($orderService->confirmOrder($orderFor()), $admin->id));
        $orderService->markDelivered($orderService->shipOrder($orderService->confirmOrder($orderFor()), $admin->id));

        // Cancelled (from pending, before any stock reservation).
        $orderService->cancelOrder($orderFor(), 'Customer changed their mind');

        // Refunded.
        $refunded = $orderService->markDelivered($orderService->shipOrder($orderService->confirmOrder($orderFor()), $admin->id));
        $orderService->refundOrder($refunded, 'Item arrived damaged');

        Auth::logout();

        $this->command?->info('Demo tenant seeded: '.self::TENANT_NAME);
        $this->command?->table(['Role', 'Email', 'Password'], [
            ['Admin', 'admin@acme.test', 'password'],
            ['Manager', 'manager@acme.test', 'password'],
            ['Staff', 'staff@acme.test', 'password'],
        ]);
    }

    private function createUser(Tenant $tenant, string $name, string $email, string $role): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
