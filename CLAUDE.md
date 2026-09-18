# FlowStock

Multi-tenant inventory & order management SaaS. Portfolio project built to
demonstrate production-quality backend practices, not a toy/demo app.

## Stack
- Laravel 12, PHP 8.4-FPM
- PostgreSQL 16, Redis 7
- Docker Compose (custom setup, no Laravel Sail — removed intentionally)
- spatie/laravel-permission (RBAC, with "teams" mapped to our tenant concept)
- Pest, OpenAPI/Swagger, GitHub Actions CI/CD — planned, not yet added

## How to work with the user

The user is new to Claude Code and to this stack. Standing rules, given
explicitly and still in force:
- **Explore → Plan → Code.** For anything non-trivial or touching more than
  a file or two, use plan mode (or at least explain the approach and get
  explicit go-ahead) before writing code. Don't build the whole project, or
  even a whole feature, in one uninterrupted pass.
- **Explain before acting.** Say what a change does and why in plain terms
  before making it — especially for commands and config files, which should
  be explained simply, not assumed as known.
- **Never install or create anything without approval first** — this
  includes Composer packages, Docker services, and new config, not just code.
- **When there are multiple reasonable approaches, briefly explain the
  trade-off and let the user pick** rather than silently choosing one.
- **Stop after each step** and wait, rather than chaining multiple steps
  together unprompted.
- **Always keep explanations short — no exceptions.** Lead with the
  decision/result, skip narrative build-up, expand into detail only if
  asked. Plans, summaries, and status updates should be a few lines/bullets,
  not paragraphs. This applies every time, not just when things feel complex.

## Architecture decisions already made (don't re-litigate without cause)

- **Multi-tenancy**: shared database, `tenant_id` column on every
  tenant-scoped table (not separate databases per tenant).
- **User ↔ tenant**: one tenant per user (`users.tenant_id`, nullable to
  allow a tenant-less platform superadmin). Not a many-to-many pivot.
- **RBAC**: spatie/laravel-permission, with `'teams' => true` and
  `'team_foreign_key' => 'tenant_id'` in `config/permission.php`. This scopes
  *who has which role* per tenant. Role/permission *definitions* themselves
  are currently shared/global (not yet duplicated per tenant) — making those
  independently customizable per tenant is a deliberate future decision, not
  done yet.
- **Tenant isolation for new domain models**: give any new tenant-scoped
  model (Product, Order, etc.) the `App\Models\Concerns\BelongsToTenant`
  trait — it applies `TenantScope` (auto-filters queries by the current
  user's `tenant_id`) and auto-fills `tenant_id` on creation. Don't
  hand-write `where('tenant_id', ...)` in controllers/queries.
- **Permissions middleware**: `SetPermissionsTeamId` runs on every `web`
  request (registered in `bootstrap/app.php`) and must run before any
  `hasRole`/`can` check, or the check will use the wrong tenant context.

## Docker / running the app

Services (`docker-compose.yml`): `app` (PHP-FPM, no exposed port),
`nginx` (**host port 8081** — 8080 was already taken by something else on
this machine), `postgres` (5432), `redis` (6379).

All PHP/Composer/Artisan commands run **inside the container**, never on the
host:
```
docker compose exec app php artisan ...
docker compose exec app composer ...
```

`docker compose exec app ... tinker --execute="..."` breaks on multi-line
scripts piped from PowerShell (BOM/encoding issues) — write a temp `.php`
file into the project instead, run it with `php artisan tinker <file>` or
a bootstrapped script, then delete the temp file afterward.

`php artisan migrate:fresh` wipes all data — fine in this local dev
environment, but call it out before running it, and reseed afterward
(`--seed` or `db:seed`) rather than leaving the DB empty.

**`docker compose exec app <command>` runs as root by default** (no `USER`
directive in the Dockerfile), but actual HTTP requests are handled by
PHP-FPM *worker* processes running as `www-data` (standard php-fpm
architecture: root master process, `www-data` workers). This means any
artisan/composer command run via `exec` can create files (e.g.
`storage/logs/laravel.log`) owned by root, which then blocks `www-data` from
writing to them during real requests — surfaced once as a confusing
"Permission denied" + secondary Monolog crash when an exception tried to log
itself. If this resurfaces: `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`.
