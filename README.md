# FlowStock

A multi-tenant inventory & order management API — a portfolio project
built to demonstrate production-quality backend engineering, not a toy
demo.

## Highlights

- **Real concurrency safety, proven not assumed** — stock movements use
  PostgreSQL row locking; a dedicated two-connection test proves a second
  transaction actually blocks, rather than trusting application-level logic alone.
- **Multi-tenant by construction** — shared database, automatic tenant
  scoping on every query via a global Eloquent scope, RBAC (Admin/Manager/Staff)
  scoped per tenant with `spatie/laravel-permission`.
- **86 tests, zero SQLite** — the full suite runs against a real
  PostgreSQL database, locally and in GitHub Actions CI, because SQLite
  can't validate the `CHECK` constraints and locking this app relies on.
- **Event-driven order lifecycle** — state-machine-driven order
  transitions, queued notifications over Redis, and audit logging with
  attribute-level diffs on every write.
- **Safe by default** — API errors never leak a stack trace or file path,
  regardless of debug mode; tenant/permission checks fail closed.
- **Deploy-ready** — a separate hardened Docker image (multi-stage build,
  no dev dependencies, no bind mount) alongside the dev setup.

## Tech stack

Laravel 12 · PHP 8.4-FPM · PostgreSQL 16 · Redis 7 · Docker Compose ·
Sanctum · spatie/laravel-permission · spatie/laravel-activitylog ·
dedoc/scramble (OpenAPI) · Pest · GitHub Actions

## API

Interactive docs, generated from the code: **`/docs/api`** (raw spec at
`/docs/api.json`). All routes are under `/api/v1`, Sanctum-authenticated,
permission-gated per route.

| Group | Endpoints |
|---|---|
| Auth | login, logout |
| Products / Categories / Warehouses | full CRUD |
| Stock | view + adjust, per product/warehouse |
| Orders | create, view, confirm → process → ship → deliver, cancel, refund |
| Audit logs | read-only, Admin only |

## Getting started

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

App: `http://localhost:8081` · Docs: `http://localhost:8081/docs/api`

No self-service registration. Fastest way to get real, varied data —
products, stock, orders in every lifecycle state, audit logs — is the
demo seeder (safe to rerun, it resets its own tenant each time):

```bash
docker compose exec app php artisan db:seed --class=DemoDataSeeder
```

Prints login credentials when it finishes (`admin@acme.test` /
`manager@acme.test` / `staff@acme.test`, password `password`).

To create a tenant/admin manually instead, use Tinker
(`docker compose exec app php artisan tinker`):

```php
$tenant = App\Models\Tenant::create(['name' => 'Acme Inc', 'slug' => 'acme-inc', 'status' => 'active']);
$user = App\Models\User::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'email' => 'admin@acme.test', 'password' => Hash::make('password')]);
app(Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
$user->assignRole('Admin');
```

## Tests & production

```bash
docker compose exec app php artisan test          # real Postgres, not SQLite
docker compose -f docker-compose.prod.yml up -d --build   # hardened prod stack
```

## License

[MIT](https://opensource.org/licenses/MIT)
