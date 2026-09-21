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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Realistic multi-tenant demo data — run explicitly with
 * `php artisan db:seed --class=DemoDataSeeder` (never wired into the
 * default seeder chain, so it never silently appears on a normal
 * `migrate --seed`). Goes through the real StockService/OrderService
 * rather than raw inserts, so stock levels, reservations, and audit
 * logs all end up in the same consistent shape they would from real
 * usage.
 *
 * Seeds 3 completely separate fictional tenants (own users, categories,
 * products, warehouses, customers, orders) so tenant isolation is easy to
 * demonstrate: an electronics distributor, an office-supplies distributor,
 * and a German retail company — each with its own realistic catalog,
 * geography, and customer roster. Order timestamps are spread across the
 * last two weeks (via Carbon::setTestNow, restored at the end) so the
 * dashboard's 14-day chart and Orders pagination both have something real
 * to show for every tenant.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Orders to seed per tenant, each backdated to `days_ago` and
     * progressed through its lifecycle up to `status` — spread across the
     * last 14 days so the dashboard's "orders placed, last 14 days" chart
     * has a real shape. Shared across tenants: the shape of business
     * activity doesn't need to differ, only the underlying catalog/customers.
     *
     * @var array<int, array{days_ago: int, status: string}>
     */
    private const ORDER_PLAN = [
        ['days_ago' => 13, 'status' => 'delivered'],
        ['days_ago' => 13, 'status' => 'delivered'],
        ['days_ago' => 12, 'status' => 'delivered'],
        ['days_ago' => 12, 'status' => 'refunded'],
        ['days_ago' => 11, 'status' => 'delivered'],
        ['days_ago' => 10, 'status' => 'shipped'],
        ['days_ago' => 10, 'status' => 'delivered'],
        ['days_ago' => 9, 'status' => 'shipped'],
        ['days_ago' => 8, 'status' => 'delivered'],
        ['days_ago' => 8, 'status' => 'cancelled'],
        ['days_ago' => 7, 'status' => 'shipped'],
        ['days_ago' => 6, 'status' => 'processing'],
        ['days_ago' => 6, 'status' => 'shipped'],
        ['days_ago' => 5, 'status' => 'confirmed'],
        ['days_ago' => 5, 'status' => 'processing'],
        ['days_ago' => 4, 'status' => 'confirmed'],
        ['days_ago' => 3, 'status' => 'confirmed'],
        ['days_ago' => 3, 'status' => 'pending'],
        ['days_ago' => 2, 'status' => 'pending'],
        ['days_ago' => 2, 'status' => 'cancelled'],
        ['days_ago' => 1, 'status' => 'pending'],
        ['days_ago' => 1, 'status' => 'confirmed'],
        ['days_ago' => 0, 'status' => 'pending'],
        ['days_ago' => 0, 'status' => 'pending'],
    ];

    public function run(): void
    {
        (new RoleSeeder)->run();

        $blueprints = $this->tenantBlueprints();

        // Idempotent: users.tenant_id is nullOnDelete (not cascade), so the
        // fixed demo emails must be cleared explicitly before the tenant
        // (whose cascade handles everything else) is removed and recreated.
        $allEmails = collect($blueprints)
            ->flatMap(fn (array $b) => ["admin@{$b['email_domain']}", "manager@{$b['email_domain']}", "staff@{$b['email_domain']}"]);
        User::whereIn('email', $allEmails)->delete();
        Tenant::whereIn('name', collect($blueprints)->pluck('name'))->delete();

        $accounts = collect($blueprints)->mapWithKeys(fn (array $blueprint) => [
            $blueprint['name'] => $this->seedTenant($blueprint),
        ]);

        $this->command?->info('Demo tenants seeded: '.$accounts->keys()->join(', '));
        foreach ($accounts as $tenantName => $rows) {
            $this->command?->info($tenantName);
            $this->command?->table(['Role', 'Email', 'Password'], $rows);
        }
    }

    /** @return array<int, array<int, string>> the [role, email, password] rows for this tenant */
    private function seedTenant(array $blueprint): array
    {
        $tenant = Tenant::create([
            'name' => $blueprint['name'],
            'slug' => Str::slug($blueprint['name']),
            'status' => 'active',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $domain = $blueprint['email_domain'];
        $admin = $this->createUser($tenant, 'Ada Admin', "admin@{$domain}", 'Admin');
        $this->createUser($tenant, 'Max Manager', "manager@{$domain}", 'Manager');
        $this->createUser($tenant, 'Sam Staff', "staff@{$domain}", 'Staff');

        // Attribute the seeded activity log/order events to a real user rather
        // than leaving every entry's causer null.
        Auth::login($admin);

        // Captured once, before any Carbon::setTestNow() call below — every
        // backdated timestamp is computed from this real moment, never from
        // the (by then frozen) now() helper.
        $realNow = Carbon::now();

        try {
            $leafCategories = $this->createCategories($tenant, $blueprint['category_tree']);
            $products = $this->createProducts($tenant, $leafCategories, $blueprint['products']);
            $warehouses = $this->createWarehouses($tenant, $blueprint['warehouses']);
            $this->stockWarehouses($realNow, $warehouses, $products, $admin, $blueprint['products'], $blueprint['low_stock_overrides']);
            $customers = $this->createCustomers($tenant, $blueprint['customers'], $blueprint['customer_country']);
            $this->createOrders($realNow, $customers, $warehouses, $products, $admin);
        } finally {
            Auth::logout();
            Carbon::setTestNow();
        }

        return [
            ['Admin', "admin@{$domain}", 'password'],
            ['Manager', "manager@{$domain}", 'password'],
            ['Staff', "staff@{$domain}", 'password'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function tenantBlueprints(): array
    {
        return [
            $this->acmeSupplyBlueprint(),
            $this->northstarOfficeBlueprint(),
            $this->urbanRetailBlueprint(),
        ];
    }

    private function acmeSupplyBlueprint(): array
    {
        return [
            'name' => 'Acme Supply Co.',
            'email_domain' => 'acme.test',
            'customer_country' => 'United States',
            'category_tree' => [
                'Computers' => ['Laptops', 'Desktops'],
                'Mobile' => ['Smartphones'],
                'Displays' => ['Monitors'],
                'Accessories' => null,
                'Audio' => null,
            ],
            'products' => [
                // Laptops
                ['category' => 'Laptops', 'name' => 'MacBook Pro 14" M4', 'sku' => 'APL-MBP14-M4', 'description' => 'Apple M4 chip, 16GB unified memory, 512GB SSD.', 'cost' => 1599.00, 'price' => 1999.00, 'reorder_point' => 6],
                ['category' => 'Laptops', 'name' => 'MacBook Air 13" M3', 'sku' => 'APL-MBA13-M3', 'description' => 'Apple M3 chip, 8GB unified memory, 256GB SSD.', 'cost' => 899.00, 'price' => 1099.00, 'reorder_point' => 8],
                ['category' => 'Laptops', 'name' => 'Dell XPS 13', 'sku' => 'DEL-XPS13', 'description' => '13.4" FHD+, Intel Core Ultra 7, 16GB RAM, 512GB SSD.', 'cost' => 999.00, 'price' => 1299.00, 'reorder_point' => 8],
                ['category' => 'Laptops', 'name' => 'Dell XPS 15', 'sku' => 'DEL-XPS15', 'description' => '15.6" 3.5K OLED, Intel Core Ultra 9, 32GB RAM, 1TB SSD.', 'cost' => 1399.00, 'price' => 1799.00, 'reorder_point' => 5],
                ['category' => 'Laptops', 'name' => 'Lenovo ThinkPad X1 Carbon', 'sku' => 'LEN-X1CARBON', 'description' => '14" WUXGA, Intel Core Ultra 7, 16GB RAM, 1TB SSD.', 'cost' => 1349.00, 'price' => 1699.00, 'reorder_point' => 8],
                ['category' => 'Laptops', 'name' => 'HP Spectre x360 14', 'sku' => 'HP-SPECTREX360', 'description' => '13.5" 3K2K OLED convertible, Intel Core Ultra 7, 16GB RAM.', 'cost' => 1099.00, 'price' => 1449.00, 'reorder_point' => 6],

                // Desktops
                ['category' => 'Desktops', 'name' => 'Mac Mini M4', 'sku' => 'APL-MACMINI-M4', 'description' => 'Apple M4 chip, 16GB unified memory, 256GB SSD.', 'cost' => 479.00, 'price' => 599.00, 'reorder_point' => 6],
                ['category' => 'Desktops', 'name' => 'HP EliteDesk 800 G9', 'sku' => 'HP-ELITEDESK800', 'description' => 'Compact business desktop, Intel Core i7, 16GB RAM, 512GB SSD.', 'cost' => 649.00, 'price' => 849.00, 'reorder_point' => 5],

                // Smartphones
                ['category' => 'Smartphones', 'name' => 'iPhone 16 Pro', 'sku' => 'APL-IPHONE16PRO', 'description' => '6.3" Super Retina XDR, A18 Pro chip, 256GB.', 'cost' => 799.00, 'price' => 999.00, 'reorder_point' => 12],
                ['category' => 'Smartphones', 'name' => 'iPhone 16', 'sku' => 'APL-IPHONE16', 'description' => '6.1" Super Retina XDR, A18 chip, 128GB.', 'cost' => 599.00, 'price' => 799.00, 'reorder_point' => 12],
                ['category' => 'Smartphones', 'name' => 'Samsung Galaxy S25', 'sku' => 'SAM-GALAXYS25', 'description' => '6.2" Dynamic AMOLED 2X, Snapdragon 8 Elite, 128GB.', 'cost' => 639.00, 'price' => 799.00, 'reorder_point' => 12],
                ['category' => 'Smartphones', 'name' => 'Samsung Galaxy S25 Ultra', 'sku' => 'SAM-GALAXYS25U', 'description' => '6.9" Dynamic AMOLED 2X, Snapdragon 8 Elite, 256GB, S Pen.', 'cost' => 999.00, 'price' => 1299.00, 'reorder_point' => 8],
                ['category' => 'Smartphones', 'name' => 'Google Pixel 9 Pro', 'sku' => 'GOOG-PIXEL9PRO', 'description' => '6.3" Super Actua display, Tensor G4, 128GB.', 'cost' => 699.00, 'price' => 899.00, 'reorder_point' => 10],

                // Monitors
                ['category' => 'Monitors', 'name' => 'Dell UltraSharp 27" Monitor', 'sku' => 'DEL-U2724D', 'description' => '27" QHD IPS Black, USB-C hub, height adjustable.', 'cost' => 379.00, 'price' => 479.00, 'reorder_point' => 10],
                ['category' => 'Monitors', 'name' => 'LG UltraFine 27" 4K Monitor', 'sku' => 'LG-27UQ850', 'description' => '27" 4K UHD IPS, USB-C 96W power delivery.', 'cost' => 499.00, 'price' => 649.00, 'reorder_point' => 8],
                ['category' => 'Monitors', 'name' => 'Samsung Odyssey G7 32"', 'sku' => 'SAM-ODYSSEYG7', 'description' => '32" QHD curved, 240Hz, 1ms response time.', 'cost' => 549.00, 'price' => 699.00, 'reorder_point' => 6],
                ['category' => 'Monitors', 'name' => 'ASUS ProArt PA278CV', 'sku' => 'ASUS-PA278CV', 'description' => '27" QHD IPS, 100% sRGB, color-accurate for design work.', 'cost' => 259.00, 'price' => 329.00, 'reorder_point' => 10],

                // Accessories
                ['category' => 'Accessories', 'name' => 'Logitech MX Master 3S', 'sku' => 'LOG-MXMASTER3S', 'description' => 'Wireless ergonomic mouse, 8K DPI, quiet clicks.', 'cost' => 64.00, 'price' => 99.99, 'reorder_point' => 30],
                ['category' => 'Accessories', 'name' => 'Logitech MX Keys S', 'sku' => 'LOG-MXKEYSS', 'description' => 'Wireless illuminated keyboard with smart backlighting.', 'cost' => 69.00, 'price' => 109.99, 'reorder_point' => 30],
                ['category' => 'Accessories', 'name' => 'Anker 737 Power Bank', 'sku' => 'ANK-737PWRBANK', 'description' => '24,000mAh, 140W, laptop-capable portable charger.', 'cost' => 89.00, 'price' => 149.99, 'reorder_point' => 25],
                ['category' => 'Accessories', 'name' => 'Anker 720W GaN Charger', 'sku' => 'ANK-720WGAN', 'description' => '4-port GaN desktop charger for laptops and phones.', 'cost' => 44.00, 'price' => 69.99, 'reorder_point' => 25],
                ['category' => 'Accessories', 'name' => 'Apple Magic Keyboard', 'sku' => 'APL-MAGICKB', 'description' => 'Wireless keyboard with Touch ID and numeric keypad.', 'cost' => 89.00, 'price' => 129.00, 'reorder_point' => 25],
                ['category' => 'Accessories', 'name' => 'Belkin USB-C Hub', 'sku' => 'BELK-USBCHUB', 'description' => '7-in-1 USB-C hub — HDMI, USB-A, SD, and power delivery.', 'cost' => 24.00, 'price' => 49.99, 'reorder_point' => 20, 'active' => false],

                // Audio
                ['category' => 'Audio', 'name' => 'Sony WH-1000XM6', 'sku' => 'SONY-WH1000XM6', 'description' => 'Wireless noise-cancelling over-ear headphones.', 'cost' => 259.00, 'price' => 399.99, 'reorder_point' => 15],
                ['category' => 'Audio', 'name' => 'Apple AirPods Pro 3', 'sku' => 'APL-AIRPODSPRO3', 'description' => 'Active noise cancellation, adaptive audio, USB-C case.', 'cost' => 169.00, 'price' => 249.00, 'reorder_point' => 20],
                ['category' => 'Audio', 'name' => 'Bose QuietComfort Ultra', 'sku' => 'BOSE-QCULTRA', 'description' => 'Wireless headphones with immersive spatial audio.', 'cost' => 279.00, 'price' => 429.00, 'reorder_point' => 15],
                ['category' => 'Audio', 'name' => 'JBL Flip 6', 'sku' => 'JBL-FLIP6', 'description' => 'Portable waterproof Bluetooth speaker.', 'cost' => 69.00, 'price' => 129.99, 'reorder_point' => 20],
            ],
            'warehouses' => [
                [
                    'name' => 'Austin Distribution Center', 'code' => 'WH-ATX',
                    'address_line1' => '4820 Rutherford Commerce Dr', 'city' => 'Austin', 'state' => 'TX',
                    'postal_code' => '78744', 'country' => 'United States',
                    'contact_name' => 'Diane Ortiz', 'contact_phone' => '512-555-0148', 'contact_email' => 'diane.ortiz@acmesupply.test',
                ],
                [
                    'name' => 'Reno Fulfillment Hub', 'code' => 'WH-RNO',
                    'address_line1' => '1150 Northgate Logistics Pkwy', 'city' => 'Reno', 'state' => 'NV',
                    'postal_code' => '89506', 'country' => 'United States',
                    'contact_name' => 'Marcus Webb', 'contact_phone' => '775-555-0193', 'contact_email' => 'marcus.webb@acmesupply.test',
                ],
            ],
            'customers' => [
                ['name' => 'Melissa Turner', 'company_name' => 'Northbridge Legal Group', 'email' => 'procurement@northbridgelegal.test', 'phone' => '512-555-0110', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78701', 'notes' => 'Net-30 invoicing, billing contact is separate from IT contact.'],
                ['name' => 'James Whitfield', 'company_name' => 'Riverside Community College', 'email' => 'j.whitfield@riversidecc.test', 'phone' => '915-555-0134', 'city' => 'El Paso', 'state' => 'TX', 'postal_code' => '79901'],
                ['name' => 'Elena Cruz', 'company_name' => 'Harborview Architecture Studio', 'email' => 'elena.cruz@harborviewarch.test', 'phone' => '206-555-0177', 'city' => 'Seattle', 'state' => 'WA', 'postal_code' => '98101', 'notes' => 'Prefers deliveries scheduled for Tuesday/Thursday mornings.'],
                ['name' => 'Marcus Webb', 'company_name' => 'BrightPath Consulting', 'email' => 'marcus@brightpathco.test', 'phone' => '303-555-0142', 'city' => 'Denver', 'state' => 'CO', 'postal_code' => '80202'],
                ['name' => 'Priya Nandi', 'company_name' => 'Summit Ridge Health Partners', 'email' => 'p.nandi@summitridgehealth.test', 'phone' => '702-555-0165', 'city' => 'Las Vegas', 'state' => 'NV', 'postal_code' => '89101', 'notes' => 'Requires PO number on every invoice.'],
                ['name' => 'Jordan Lee', 'company_name' => 'TechNest Coworking', 'email' => 'jordan@technestco.test', 'phone' => '512-555-0189', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78702'],
            ],
            'low_stock_overrides' => [
                'LEN-X1CARBON' => 'WH-RNO',
                'ANK-737PWRBANK' => 'WH-ATX',
            ],
        ];
    }

    private function northstarOfficeBlueprint(): array
    {
        return [
            'name' => 'Northstar Office Supplies',
            'email_domain' => 'northstar.test',
            'customer_country' => 'United States',
            'category_tree' => [
                'Paper & Stationery' => ['Notebooks', 'Printer Paper'],
                'Office Furniture' => ['Desks', 'Chairs'],
                'Writing Instruments' => null,
                'Filing & Storage' => null,
                'Printers & Ink' => null,
            ],
            'products' => [
                // Notebooks
                ['category' => 'Notebooks', 'name' => 'Moleskine Classic Notebook', 'sku' => 'MOL-CLASSIC-NB', 'description' => 'Hardcover, ruled, 240 pages.', 'cost' => 12.00, 'price' => 19.95, 'reorder_point' => 40],
                ['category' => 'Notebooks', 'name' => 'Rhodia Dot Pad', 'sku' => 'RHODIA-DOTPAD', 'description' => 'A5 dot-grid pad, 80 sheets.', 'cost' => 8.00, 'price' => 14.95, 'reorder_point' => 40],
                ['category' => 'Notebooks', 'name' => 'Five Star Spiral Notebook', 'sku' => 'FIVESTAR-SPIRAL', 'description' => '1-subject, college ruled, 100 sheets.', 'cost' => 3.00, 'price' => 6.49, 'reorder_point' => 60],

                // Printer Paper
                ['category' => 'Printer Paper', 'name' => 'HP Printer Paper 20lb (Case)', 'sku' => 'HP-PAPER-20LB', 'description' => '10 reams, 92 bright, letter size.', 'cost' => 28.00, 'price' => 42.99, 'reorder_point' => 25],
                ['category' => 'Printer Paper', 'name' => 'Hammermill Copy Plus Paper (Case)', 'sku' => 'HAMMERMILL-COPY', 'description' => '10 reams, 92 bright, letter size.', 'cost' => 26.00, 'price' => 39.99, 'reorder_point' => 25],
                ['category' => 'Printer Paper', 'name' => 'Boise X-9 Multipurpose Paper (Case)', 'sku' => 'BOISE-X9-PAPER', 'description' => '10 reams, 92 bright, letter size.', 'cost' => 24.00, 'price' => 36.99, 'reorder_point' => 25],

                // Writing Instruments
                ['category' => 'Writing Instruments', 'name' => 'Pilot G2 Gel Pens (Box of 12)', 'sku' => 'PILOT-G2-12PK', 'description' => 'Retractable gel pens, fine point, black.', 'cost' => 9.00, 'price' => 15.99, 'reorder_point' => 50],
                ['category' => 'Writing Instruments', 'name' => 'BIC Cristal Ballpoint Pens (Box of 24)', 'sku' => 'BIC-CRISTAL-24PK', 'description' => 'Medium point, black ink.', 'cost' => 5.00, 'price' => 9.99, 'reorder_point' => 60],
                ['category' => 'Writing Instruments', 'name' => 'Sharpie Permanent Markers (Box of 12)', 'sku' => 'SHARPIE-PERM-12PK', 'description' => 'Fine point, assorted colors.', 'cost' => 8.00, 'price' => 13.99, 'reorder_point' => 40],
                ['category' => 'Writing Instruments', 'name' => 'Staedtler Highlighters (Box of 10)', 'sku' => 'STAEDTLER-HL-10PK', 'description' => 'Chisel tip, assorted colors.', 'cost' => 6.00, 'price' => 10.99, 'reorder_point' => 40],

                // Desks
                ['category' => 'Desks', 'name' => 'Fully Jarvis Standing Desk', 'sku' => 'FULLY-JARVIS-DESK', 'description' => 'Electric height-adjustable, 60x30" top.', 'cost' => 420.00, 'price' => 599.00, 'reorder_point' => 4],
                ['category' => 'Desks', 'name' => 'IKEA Bekant Desk', 'sku' => 'IKEA-BEKANT-DESK', 'description' => 'Sit desk, 63x31", white.', 'cost' => 180.00, 'price' => 249.00, 'reorder_point' => 6],
                ['category' => 'Desks', 'name' => 'Steelcase Series 1 Desk', 'sku' => 'STEELCASE-S1-DESK', 'description' => 'Height-adjustable bench desk.', 'cost' => 350.00, 'price' => 499.00, 'reorder_point' => 4],

                // Chairs
                ['category' => 'Chairs', 'name' => 'Herman Miller Aeron Chair', 'sku' => 'HERMANMILLER-AERON', 'description' => 'Size B, fully adjustable, PostureFit SL.', 'cost' => 950.00, 'price' => 1395.00, 'reorder_point' => 3],
                ['category' => 'Chairs', 'name' => 'Steelcase Leap Chair', 'sku' => 'STEELCASE-LEAP', 'description' => 'Ergonomic task chair, adjustable lumbar.', 'cost' => 700.00, 'price' => 999.00, 'reorder_point' => 4],
                ['category' => 'Chairs', 'name' => 'HON Ignition 2.0 Chair', 'sku' => 'HON-IGNITION2', 'description' => 'Mesh back task chair, adjustable arms.', 'cost' => 220.00, 'price' => 329.00, 'reorder_point' => 6],

                // Filing & Storage
                ['category' => 'Filing & Storage', 'name' => 'Bankers Box Storage Boxes (12-pack)', 'sku' => 'BANKERSBOX-12PK', 'description' => 'Letter/legal, quick-set-up lids.', 'cost' => 22.00, 'price' => 34.99, 'reorder_point' => 30],
                ['category' => 'Filing & Storage', 'name' => 'Smead File Folders (Box of 100)', 'sku' => 'SMEAD-FOLDERS-100', 'description' => 'Letter size, 1/3-cut tabs, manila.', 'cost' => 18.00, 'price' => 27.99, 'reorder_point' => 30],
                ['category' => 'Filing & Storage', 'name' => 'Fellowes Filing Cabinet', 'sku' => 'FELLOWES-FILECAB', 'description' => '2-drawer, letter/legal, lockable.', 'cost' => 140.00, 'price' => 199.99, 'reorder_point' => 8],

                // Printers & Ink
                ['category' => 'Printers & Ink', 'name' => 'HP LaserJet Pro M404dn', 'sku' => 'HP-LASERJET-M404DN', 'description' => 'Monochrome laser printer, duplex, networked.', 'cost' => 260.00, 'price' => 349.00, 'reorder_point' => 6],
                ['category' => 'Printers & Ink', 'name' => 'Brother HL-L2350DW', 'sku' => 'BROTHER-HLL2350DW', 'description' => 'Compact monochrome laser printer, wireless.', 'cost' => 110.00, 'price' => 159.99, 'reorder_point' => 8],
                ['category' => 'Printers & Ink', 'name' => 'HP 63 Ink Cartridge Combo', 'sku' => 'HP-63-INKCOMBO', 'description' => 'Black + tri-color, standard yield.', 'cost' => 32.00, 'price' => 49.99, 'reorder_point' => 20],
                ['category' => 'Printers & Ink', 'name' => 'Brother TN660 Toner Cartridge', 'sku' => 'BROTHER-TN660', 'description' => 'High-yield black toner.', 'cost' => 45.00, 'price' => 69.99, 'reorder_point' => 20],
            ],
            'warehouses' => [
                [
                    'name' => 'Columbus Distribution Center', 'code' => 'WH-CMH',
                    'address_line1' => '775 Innovation Way', 'city' => 'Columbus', 'state' => 'OH',
                    'postal_code' => '43215', 'country' => 'United States',
                    'contact_name' => 'Rachel Kim', 'contact_phone' => '614-555-0122', 'contact_email' => 'rachel.kim@northstarsupplies.test',
                ],
                [
                    'name' => 'Charlotte Fulfillment Hub', 'code' => 'WH-CLT',
                    'address_line1' => '2200 Freight Yard Rd', 'city' => 'Charlotte', 'state' => 'NC',
                    'postal_code' => '28208', 'country' => 'United States',
                    'contact_name' => 'Tom Delgado', 'contact_phone' => '704-555-0176', 'contact_email' => 'tom.delgado@northstarsupplies.test',
                ],
            ],
            'customers' => [
                ['name' => 'Karen Whitfield', 'company_name' => 'Meridian Insurance Group', 'email' => 'karen.whitfield@meridianinsurance.test', 'phone' => '614-555-0141', 'city' => 'Columbus', 'state' => 'OH', 'postal_code' => '43201', 'notes' => 'Reorders paper and toner monthly on a standing PO.'],
                ['name' => 'David Okafor', 'company_name' => 'Lakeside Elementary School', 'email' => 'd.okafor@lakesideschool.test', 'phone' => '614-555-0163', 'city' => 'Columbus', 'state' => 'OH', 'postal_code' => '43212'],
                ['name' => 'Angela Ferris', 'company_name' => 'Ferris & Cole CPAs', 'email' => 'angela@ferriscolecpa.test', 'phone' => '704-555-0128', 'city' => 'Charlotte', 'state' => 'NC', 'postal_code' => '28202', 'notes' => 'Net-30 invoicing.'],
                ['name' => 'Brian Sato', 'company_name' => 'Union Street Dental Practice', 'email' => 'brian.sato@unionstreetdental.test', 'phone' => '704-555-0154', 'city' => 'Charlotte', 'state' => 'NC', 'postal_code' => '28204'],
                ['name' => 'Nicole Bennett', 'company_name' => 'Greenfield Nonprofit Alliance', 'email' => 'nicole@greenfieldalliance.test', 'phone' => '614-555-0187', 'city' => 'Columbus', 'state' => 'OH', 'postal_code' => '43206', 'notes' => 'Tax-exempt — PO must reference exemption certificate.'],
                ['name' => 'Tyler Brooks', 'company_name' => 'Bright Horizons Tutoring Center', 'email' => 'tyler@brighthorizonstutoring.test', 'phone' => '704-555-0119', 'city' => 'Charlotte', 'state' => 'NC', 'postal_code' => '28210'],
            ],
            'low_stock_overrides' => [
                'HERMANMILLER-AERON' => 'WH-CLT',
                'HP-LASERJET-M404DN' => 'WH-CMH',
            ],
        ];
    }

    private function urbanRetailBlueprint(): array
    {
        return [
            'name' => 'Urban Retail GmbH',
            'email_domain' => 'urbanretail.test',
            'customer_country' => 'Germany',
            'category_tree' => [
                'Apparel' => ["Men's", "Women's"],
                'Home & Living' => ['Kitchen', 'Decor'],
                'Footwear' => null,
                'Accessories' => null,
            ],
            'products' => [
                // Men's
                ['category' => "Men's", 'name' => "Levi's 501 Original Jeans", 'sku' => 'LEVIS-501-JEANS', 'description' => 'Straight fit, button fly, classic denim.', 'cost' => 35.00, 'price' => 69.90, 'reorder_point' => 25],
                ['category' => "Men's", 'name' => 'Nike Sportswear Club Hoodie', 'sku' => 'NIKE-CLUBHOODIE', 'description' => 'Fleece pullover hoodie, unisex fit.', 'cost' => 28.00, 'price' => 54.90, 'reorder_point' => 25],
                ['category' => "Men's", 'name' => "Uniqlo Men's Oxford Shirt", 'sku' => 'UNIQLO-OXFORD-M', 'description' => 'Long sleeve, cotton, regular fit.', 'cost' => 18.00, 'price' => 34.90, 'reorder_point' => 30],

                // Women's
                ['category' => "Women's", 'name' => 'Zara Wrap Midi Dress', 'sku' => 'ZARA-WRAPMIDI', 'description' => 'V-neck, tie waist, midi length.', 'cost' => 22.00, 'price' => 45.90, 'reorder_point' => 20],
                ['category' => "Women's", 'name' => 'H&M Ribbed Knit Sweater', 'sku' => 'HM-RIBKNIT-SWTR', 'description' => 'Crew neck, fine rib knit.', 'cost' => 15.00, 'price' => 29.90, 'reorder_point' => 30],
                ['category' => "Women's", 'name' => "Levi's Ribcage Straight Jeans", 'sku' => 'LEVIS-RIBCAGE', 'description' => 'High rise, straight leg, rigid denim.', 'cost' => 36.00, 'price' => 74.90, 'reorder_point' => 20],

                // Footwear
                ['category' => 'Footwear', 'name' => 'Adidas Samba OG', 'sku' => 'ADIDAS-SAMBAOG', 'description' => 'Classic leather trainer, gum sole.', 'cost' => 55.00, 'price' => 99.90, 'reorder_point' => 20],
                ['category' => 'Footwear', 'name' => 'Nike Air Force 1', 'sku' => 'NIKE-AIRFORCE1', 'description' => "'07 low-top, white leather.", 'cost' => 58.00, 'price' => 109.90, 'reorder_point' => 20],
                ['category' => 'Footwear', 'name' => 'Dr. Martens 1460 Boots', 'sku' => 'DRMARTENS-1460', 'description' => '8-eye leather boot, black.', 'cost' => 90.00, 'price' => 169.90, 'reorder_point' => 12],
                ['category' => 'Footwear', 'name' => 'Birkenstock Arizona Sandals', 'sku' => 'BIRKENSTOCK-ARIZONA', 'description' => 'Two-strap sandal, suede footbed.', 'cost' => 35.00, 'price' => 64.90, 'reorder_point' => 20],

                // Kitchen
                ['category' => 'Kitchen', 'name' => 'Le Creuset Dutch Oven', 'sku' => 'LECREUSET-DUTCHOVEN', 'description' => '24cm round, enameled cast iron.', 'cost' => 180.00, 'price' => 289.00, 'reorder_point' => 6],
                ['category' => 'Kitchen', 'name' => 'WMF Cutlery Set', 'sku' => 'WMF-CUTLERYSET', 'description' => '30-piece stainless steel set, service for 6.', 'cost' => 65.00, 'price' => 109.00, 'reorder_point' => 10],
                ['category' => 'Kitchen', 'name' => 'Fissler Frying Pan', 'sku' => 'FISSLER-FRYPAN', 'description' => '28cm stainless steel, induction-ready.', 'cost' => 45.00, 'price' => 79.00, 'reorder_point' => 12],

                // Decor
                ['category' => 'Decor', 'name' => 'IKEA Fado Table Lamp', 'sku' => 'IKEA-FADOLAMP', 'description' => 'Frosted glass, dimmable, white.', 'cost' => 12.00, 'price' => 24.99, 'reorder_point' => 25],
                ['category' => 'Decor', 'name' => 'Zara Home Linen Cushion Cover', 'sku' => 'ZARAHOME-CUSHION', 'description' => '45x45cm, washed linen.', 'cost' => 8.00, 'price' => 17.90, 'reorder_point' => 30],
                ['category' => 'Decor', 'name' => 'Hay Ceramic Vase', 'sku' => 'HAY-CERAMICVASE', 'description' => 'Matte glaze, medium, handmade.', 'cost' => 20.00, 'price' => 39.00, 'reorder_point' => 15],

                // Accessories
                ['category' => 'Accessories', 'name' => 'Ray-Ban Wayfarer Sunglasses', 'sku' => 'RAYBAN-WAYFARER', 'description' => 'Classic acetate frame, UV400 lenses.', 'cost' => 60.00, 'price' => 129.00, 'reorder_point' => 15],
                ['category' => 'Accessories', 'name' => 'Fossil Leather Belt', 'sku' => 'FOSSIL-BELT', 'description' => 'Full-grain leather, brushed buckle.', 'cost' => 22.00, 'price' => 44.90, 'reorder_point' => 20],
                ['category' => 'Accessories', 'name' => 'Herschel Novel Duffel Bag', 'sku' => 'HERSCHEL-DUFFEL', 'description' => '42.5L, shoe compartment, cotton webbing strap.', 'cost' => 38.00, 'price' => 74.90, 'reorder_point' => 15, 'active' => false],
            ],
            'warehouses' => [
                [
                    'name' => 'Berlin Logistikzentrum', 'code' => 'WH-BER',
                    'address_line1' => 'Frachtstraße 12', 'city' => 'Berlin', 'state' => 'Berlin',
                    'postal_code' => '10115', 'country' => 'Germany',
                    'contact_name' => 'Lukas Hoffmann', 'contact_phone' => '+49 30 5550 1123', 'contact_email' => 'lukas.hoffmann@urbanretail.test',
                ],
                [
                    'name' => 'München Vertriebslager', 'code' => 'WH-MUC',
                    'address_line1' => 'Industriering 8', 'city' => 'München', 'state' => 'Bayern',
                    'postal_code' => '80939', 'country' => 'Germany',
                    'contact_name' => 'Sophie Bauer', 'contact_phone' => '+49 89 5550 4471', 'contact_email' => 'sophie.bauer@urbanretail.test',
                ],
            ],
            'customers' => [
                ['name' => 'Julia Meyer', 'company_name' => 'Boutique Kleinod', 'email' => 'julia.meyer@boutique-kleinod.test', 'phone' => '+49 30 5550 8812', 'city' => 'Berlin', 'state' => 'Berlin', 'postal_code' => '10119', 'notes' => 'Small boutique — orders in limited quantities per style.'],
                ['name' => 'Felix Wagner', 'company_name' => 'Café Lumen', 'email' => 'felix@cafe-lumen.test', 'phone' => '+49 30 5550 7734', 'city' => 'Berlin', 'state' => 'Berlin', 'postal_code' => '10245'],
                ['name' => 'Anna Schreiber', 'company_name' => 'Alpen Sports Club', 'email' => 'anna.schreiber@alpensportsclub.test', 'phone' => '+49 89 5550 2261', 'city' => 'München', 'state' => 'Bayern', 'postal_code' => '80331'],
                ['name' => 'Maximilian Voss', 'company_name' => 'Stadtmitte Coworking', 'email' => 'max.voss@stadtmitte-coworking.test', 'phone' => '+49 89 5550 9987', 'city' => 'München', 'state' => 'Bayern', 'postal_code' => '80333'],
                ['name' => 'Laura Fischer', 'company_name' => 'Fashion House München', 'email' => 'laura.fischer@fashionhouse-muc.test', 'phone' => '+49 89 5550 3345', 'city' => 'München', 'state' => 'Bayern', 'postal_code' => '80469', 'notes' => 'Requires delivery notes in German.'],
                ['name' => 'Jonas Richter', 'company_name' => 'Berlin Startup Hub', 'email' => 'jonas@berlinstartuphub.test', 'phone' => '+49 30 5550 6623', 'city' => 'Berlin', 'state' => 'Berlin', 'postal_code' => '10963'],
            ],
            'low_stock_overrides' => [
                'LECREUSET-DUTCHOVEN' => 'WH-MUC',
                'DRMARTENS-1460' => 'WH-BER',
            ],
        ];
    }

    /**
     * @param  array<string, array<int, string>|null>  $categoryTree  Parent category => child category names, or null for a leaf category.
     * @return array<string, ProductCategory> leaf category name => model
     */
    private function createCategories(Tenant $tenant, array $categoryTree): array
    {
        $leaves = [];

        foreach ($categoryTree as $parentName => $children) {
            $parent = ProductCategory::create([
                'tenant_id' => $tenant->id,
                'name' => $parentName,
                'slug' => Str::slug($parentName),
            ]);

            if ($children === null) {
                $leaves[$parentName] = $parent;

                continue;
            }

            foreach ($children as $childName) {
                $leaves[$childName] = ProductCategory::create([
                    'tenant_id' => $tenant->id,
                    'name' => $childName,
                    'slug' => Str::slug($childName),
                    'parent_id' => $parent->id,
                ]);
            }
        }

        return $leaves;
    }

    /**
     * @param  array<string, ProductCategory>  $leafCategories
     * @param  array<int, array<string, mixed>>  $productsSpec
     * @return Collection<int, Product>
     */
    private function createProducts(Tenant $tenant, array $leafCategories, array $productsSpec): Collection
    {
        return collect($productsSpec)->map(fn (array $spec) => Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $leafCategories[$spec['category']]->id,
            'sku' => $spec['sku'],
            'name' => $spec['name'],
            'description' => $spec['description'],
            'barcode' => fake()->unique()->ean13(),
            'unit_of_measure' => 'pcs',
            'cost_price' => $spec['cost'],
            'selling_price' => $spec['price'],
            'is_active' => $spec['active'] ?? true,
        ]));
    }

    /**
     * @param  array<int, array<string, string>>  $warehousesSpec
     * @return Collection<int, Warehouse>
     */
    private function createWarehouses(Tenant $tenant, array $warehousesSpec): Collection
    {
        return collect($warehousesSpec)->map(fn (array $spec) => Warehouse::create([
            'tenant_id' => $tenant->id,
            'name' => $spec['name'],
            'code' => $spec['code'],
            'address_line1' => $spec['address_line1'],
            'city' => $spec['city'],
            'state' => $spec['state'],
            'postal_code' => $spec['postal_code'],
            'country' => $spec['country'],
            'contact_name' => $spec['contact_name'],
            'contact_phone' => $spec['contact_phone'],
            'contact_email' => $spec['contact_email'],
        ]));
    }

    /**
     * @param  Collection<int, Warehouse>  $warehouses
     * @param  Collection<int, Product>  $products
     * @param  array<int, array<string, mixed>>  $productsSpec
     * @param  array<string, string>  $lowStockOverrides  product sku => warehouse code
     */
    private function stockWarehouses(Carbon $realNow, Collection $warehouses, Collection $products, User $admin, array $productsSpec, array $lowStockOverrides): void
    {
        // Initial stock predates every order below, so movement history reads
        // "received, then sold" in the right order.
        Carbon::setTestNow($realNow->copy()->subDays(21)->setTime(8, 0));

        $stockService = app(StockService::class);
        $reorderPointsBySku = collect($productsSpec)->pluck('reorder_point', 'sku');

        foreach ($warehouses as $warehouse) {
            foreach ($products as $product) {
                $reorderPoint = $reorderPointsBySku[$product->sku];
                $override = $lowStockOverrides[$product->sku] ?? null;
                $quantity = $override === $warehouse->code
                    ? max(1, (int) round($reorderPoint * fake()->randomFloat(2, 0.3, 0.6)))
                    : (int) round($reorderPoint * fake()->randomFloat(2, 3, 6));

                $stockService->recordMovement(
                    product: $product,
                    warehouse: $warehouse,
                    quantityChange: $quantity,
                    type: StockMovementType::Received,
                    note: 'Initial stock receipt',
                    userId: $admin->id,
                );
                $stockService->setReorderPoint($product, $warehouse, $reorderPoint);
            }
        }
    }

    /**
     * @param  array<int, array<string, string>>  $customersSpec
     * @return Collection<int, Customer>
     */
    private function createCustomers(Tenant $tenant, array $customersSpec, string $country): Collection
    {
        return collect($customersSpec)->map(fn (array $spec) => Customer::create([
            'tenant_id' => $tenant->id,
            'name' => $spec['name'],
            'company_name' => $spec['company_name'],
            'email' => $spec['email'],
            'phone' => $spec['phone'],
            'billing_address_line1' => fake()->buildingNumber().' '.fake()->streetName(),
            'billing_city' => $spec['city'],
            'billing_state' => $spec['state'],
            'billing_postal_code' => $spec['postal_code'],
            'billing_country' => $country,
            'shipping_address_line1' => fake()->buildingNumber().' '.fake()->streetName(),
            'shipping_city' => $spec['city'],
            'shipping_state' => $spec['state'],
            'shipping_postal_code' => $spec['postal_code'],
            'shipping_country' => $country,
            'notes' => $spec['notes'] ?? null,
        ]));
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Warehouse>  $warehouses
     * @param  Collection<int, Product>  $products
     */
    private function createOrders(Carbon $realNow, Collection $customers, Collection $warehouses, Collection $products, User $admin): void
    {
        $orderService = app(OrderService::class);
        $primaryWarehouse = $warehouses->first();
        $secondaryWarehouse = $warehouses->last();

        foreach (self::ORDER_PLAN as $plan) {
            $warehouse = fake()->boolean(70) ? $primaryWarehouse : $secondaryWarehouse;
            $customer = $customers->random();
            $items = $products->random(fake()->numberBetween(1, 3))
                ->map(fn (Product $product) => ['product_id' => $product->id, 'quantity' => fake()->numberBetween(1, 3)])
                ->values()->all();

            $createdAt = $realNow->copy()->subDays($plan['days_ago'])->setTime(fake()->numberBetween(9, 17), fake()->numberBetween(0, 59));
            Carbon::setTestNow($createdAt);
            $order = $orderService->createOrder($customer, $warehouse, $items);

            $cursor = $createdAt;

            if ($plan['status'] === 'cancelled') {
                Carbon::setTestNow($cursor->copy()->addHours(fake()->numberBetween(2, 20)));
                $orderService->cancelOrder($order, 'Customer changed their mind');

                continue;
            }

            if ($plan['status'] === 'pending') {
                continue;
            }

            $cursor = $cursor->copy()->addHours(fake()->numberBetween(2, 6));
            Carbon::setTestNow($cursor);
            $order = $orderService->confirmOrder($order);

            if ($plan['status'] === 'confirmed') {
                continue;
            }

            $cursor = $cursor->copy()->addHours(fake()->numberBetween(4, 20));
            Carbon::setTestNow($cursor);
            $order = $orderService->markProcessing($order);

            if ($plan['status'] === 'processing') {
                continue;
            }

            $cursor = $cursor->copy()->addDays(1)->addHours(fake()->numberBetween(0, 10));
            Carbon::setTestNow($cursor);
            $order = $orderService->shipOrder($order, $admin->id);

            if ($plan['status'] === 'shipped') {
                continue;
            }

            $cursor = $cursor->copy()->addDays(fake()->numberBetween(2, 4));
            Carbon::setTestNow($cursor);
            $order = $orderService->markDelivered($order);

            if ($plan['status'] === 'refunded') {
                $cursor = $cursor->copy()->addDays(fake()->numberBetween(2, 5));
                Carbon::setTestNow($cursor);
                $orderService->refundOrder($order, 'Item arrived damaged');
            }
        }
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
