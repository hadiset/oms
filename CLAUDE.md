# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel 13 app built on the official Livewire starter kit (Livewire 4 + Flux UI + Fortify auth with 2FA/passkeys), running in Podman containers. This project adds role/permission management on top of it using `spatie/laravel-permission`.

## Environment & commands

The dev stack runs under `podman-compose` (`docker-compose.yml`):
- `laradocker` — PHP 8.5-fpm (pdo_pgsql), project mounted at `/var/www`
- `laranginx` — nginx on http://localhost:8000
- `larapg` — PostgreSQL 16 (db `larapg_db`, user `larapg`), port 5432
- `laragate` — DbGate DB UI on http://localhost:8080

PHP/artisan/composer commands run **inside the container**: `podman exec -it laradocker <cmd>`. npm/Vite run on the host.

```bash
make setup            # env + up + npm + composer + key + migrate:fresh --seed + perms
make up / make down   # start / stop containers
make migrate          # migrate:fresh --seed (destructive)
make seed-permissions # add new PermissionName cases to the DB (non-destructive)
make test / make lint # composer test / composer lint inside the container
make perms            # fix storage/ & bootstrap/cache ownership for www-data
npm run dev           # Vite hot reload

composer test         # config:clear + pint --test + phpstan + artisan test
composer lint         # pint --parallel (laravel preset, pint.json)
composer types:check  # phpstan (larastan) level 7
php artisan test --filter="filter permission"           # single test by name
php artisan test resources/views/pages/role/⚡index.test.php  # single file
```

Tests use in-memory SQLite (`phpunit.xml`), not Postgres. Pest's `RefreshDatabase` + `TestCase` binding applies to `tests/Feature` and `resources/views` (see `tests/Pest.php`).

## Architecture

- **Single-file Livewire page components** live in `resources/views/pages/**/⚡name.blade.php` (anonymous `new class extends Component` at the top, Blade below). They're routed with `Route::livewire('uri', 'pages::dir.name')` in `routes/web.php` / `routes/settings.php`. Component tests sit beside them as `⚡name.test.php` and form the `Components` test suite. Note the `⚡` character in filenames.
- **Access-control pages** (`pages/permission`, `pages/role`, `pages/user`) share one pattern: `#[Url(as: 'q')]` search + `WithPagination`, a `#[Computed]` query, create/edit via `<x-modal>`, validation and persistence in a Livewire Form Object (`app/Livewire/Forms/*Form.php`, bound as `public XForm $form`, fields addressed as `form.name`), and feedback via SweetAlert2 (`WithSweetAlert`). Permission/role name rules are shared in `app/Concerns/AccessControlValidationRules.php` (`^[a-z0-9._-]+$`, max 255, unique).
- **`<x-modal>`** (`app/View/Components/Modal.php` + `resources/views/components/modal.blade.php`) wraps `flux:modal` with a form; props `name`, `modal-title`, `modal-desc`, `btn-color`, `btn-text`, `submit` (the Livewire method called on submit). It calls the host component's `closeModal()` on close, so components using it must define that method.

## Authorization

- App permissions are the cases of `app/Enums/PermissionName.php` (`permission.*`, `role.*`, `user.view`, `user.assign-role`). Add a case there and re-run `RolePermissionSeeder` for new features; `can()`/`@can` accept the enum directly.
- Policies (`app/Policies/`) are registered manually with `Gate::policy()` in `AppServiceProvider` (Spatie's `Permission` model lives in the vendor namespace). Routes use `->can('viewAny', Model::class)`; **every mutating Livewire action must also call `$this->authorize()`**, because Livewire actions don't go through route middleware.
- Super-admin = the `super-admin` role (`App\Models\Role::SUPER_ADMIN`). `Gate::before` returns `true` for it, so **policies are skipped for super-admins** — invariants that must hold even for them live in the models (`App\Models\Role` and `App\Models\Permission` are registered in `config/permission.php`; always import these, not Spatie's):
  - `Role`: super-admin can't be deleted or renamed (`ProtectedRoleException`).
  - `Permission`: permissions listed in `PermissionName` are "system" (`isSystem()`) and can't be deleted or renamed (`ProtectedPermissionException`).
  - `User`: the last super-admin can't be deleted (`isLastSuperAdmin()`); the account-settings delete modal checks this before logging out. `UserRoleForm` also blocks revoking it from the last super-admin and lets only super-admins grant/revoke it.
  - **Deleting guards must be registered in `boot()` before `parent::boot()`**, not in `booted()`: Spatie's `HasRoles`/`HasPermissions` `deleting` listeners detach pivots first, and there's no transaction to roll that back.
- Search goes through the `search()` scope from `App\Models\Concerns\Searchable` (`whereLike`, case-insensitive → `ILIKE` on Postgres). Don't hand-write `where(..., 'like', ...)`: SQLite tests would pass while Postgres stays case-sensitive.
- Seeders: `RolePermissionSeeder` (permissions + super-admin role, idempotent) and `AdminUserSeeder` (account from `config('permission.default_admin')`, i.e. `ADMIN_*` env vars, default `admin@example.com` / `password`).
- Test helpers in `tests/Pest.php`: `seedPermissions()`, `userWithPermissions(PermissionName ...)`, `superAdmin()`. Component tests must `actingAs()` an authorized user or `mount()` returns 403. The app's 10 seeded permissions fill page 1 of the permission list (5 per page), so list tests filter by search.
- **Auth** is Fortify-based (`app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/`); shared validation rules are in `app/Concerns/`.
- Navigation lives in `resources/views/layouts/app/sidebar.blade.php`; new pages need a `flux:sidebar.item` there.
