<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13" />
  <img src="https://img.shields.io/badge/Podman-Compose-892CA0?style=for-the-badge&logo=podman&logoColor=white" alt="Podman Compose" />
  <img src="https://img.shields.io/badge/PHP-8.5--fpm--alpine-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.5" />
  <img src="https://img.shields.io/badge/PostgreSQL-16--alpine-4169E1?style=for-the-badge&logo=postgresql&logoColor=white" alt="PostgreSQL 16" />
</p>

<h1 align="center">🚀 Laravel Podman Starter</h1>

<p align="center">
  A batteries-included, container-native Laravel starter — spin up a full dev stack in minutes, not hours.
</p>

<p align="center">
  <img src="https://img.shields.io/github/stars/hadiset/laravel-podman-starter?style=flat-square" alt="stars" />
  <img src="https://img.shields.io/github/last-commit/hadiset/laravel-podman-starter?style=flat-square" alt="last commit" />
  <img src="https://img.shields.io/github/license/hadiset/laravel-podman-starter?style=flat-square" alt="license" />
</p>

---

## ✨ Features

| | |
|---|---|
| 🐘 | **Laravel 13** with the Livewire 4 + Flux starter kit (Fortify auth, 2FA, passkeys) |
| 🔐 | **Roles & permissions** — [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) with management pages for permissions, roles and user roles |
| 📦 | **Podman Compose** — daemonless, rootless containers |
| 🌐 | **Nginx (alpine)** — lean, fast reverse proxy |
| ⚙️ | **PHP 8.5-fpm-alpine** — slim runtime, modern PHP |
| 🐘 | **PostgreSQL 16 (alpine)** — solid, production-grade DB, plus DbGate as a web UI |
| ⚡ | **Vite Hot Reload** — instant frontend feedback loop |
| ✅ | **Pest, Pint & PHPStan (level 7)** — one command to check everything |

---

## 📋 Requirements

- [Podman](https://podman.io/) and [podman-compose](https://github.com/containers/podman-compose)
- Node.js + npm on the host (for Vite)
- `make` (optional — every target is a plain command you can run yourself)

PHP and Composer run **inside** the `laradocker` container, so you don't need them on the host.

---

## 🛠️ Setup from scratch

```bash
git clone https://github.com/hadiset/laravel-podman-starter.git
cd laravel-podman-starter

make setup      # .env → containers → npm + composer install → key → migrate + seed → permissions
npm run dev     # Vite with hot reload (keep it running)
```

Then open **http://localhost:8000** and log in with the [default admin](#-default-admin-account).

<details>
<summary>What <code>make setup</code> runs, step by step</summary>

```bash
cp .env.example .env                                      # make env (skipped if .env exists)
podman-compose up -d --build                              # make up
npm install                                               # make npm
podman exec -it laradocker composer install               # make composer
podman exec -it laradocker php artisan key:generate       # make key
podman exec -it laradocker php artisan migrate:fresh --seed   # make migrate
podman exec -it laradocker chown -R www-data:www-data storage/ bootstrap/cache   # make perms
podman exec -it laradocker chmod -R 755 storage/ bootstrap/cache
```

</details>

> ⚠️ `make migrate` runs `migrate:fresh` — it **drops every table**. Use it to reset a dev database, never on data you care about.

### Services

| Service | Container | URL / port |
|---|---|---|
| App (nginx → php-fpm) | `laranginx`, `laradocker` | http://localhost:8000 |
| PostgreSQL | `larapg` | `localhost:5432` — db `larapg_db`, user `larapg`, password `larapg123` |
| DbGate (DB web UI) | `laragate` | http://localhost:8080 |

`.env.example` is already wired to the `postgres` service (`DB_HOST=postgres`, the service name inside the container network). If you change the credentials in `docker-compose.yml`, change them in `.env` too.

---

## 🔑 Default admin account

The seeder creates one **super-admin** from these `.env` values:

| Variable | Dev default |
|---|---|
| `ADMIN_NAME` | `Administrator` |
| `ADMIN_EMAIL` | `admin@example.com` |
| `ADMIN_PASSWORD` | `password` |

- Super-admins pass **every** permission check (via `Gate::before`), so the role carries no permissions of its own.
- Change the values in `.env` **before** seeding. Re-seeding never overwrites an existing account's password.
- With `APP_ENV=production` the seeder **refuses** to run while `ADMIN_PASSWORD` is still `password`.
- Some things stay protected even from super-admins: the `super-admin` role can't be deleted or renamed, built-in permissions can't be deleted or renamed, and the last super-admin can't delete their account or lose the role.

---

## 🧩 Adding a new permission

All permissions the code checks live in one enum, [`app/Enums/PermissionName.php`](app/Enums/PermissionName.php).

**1. Add a case**

```php
enum PermissionName: string
{
    // ...
    case PostEdit = 'post.edit';
}
```

Names use lowercase letters, numbers, `.`, `_` and `-` (e.g. `post.edit`, `report.export-pdf`).

**2. Create it in the database** — the seeder only adds what's missing, so it's safe on a live database:

```bash
make seed-permissions
# = podman exec -it laradocker php artisan db:seed --class=RolePermissionSeeder
```

**3. Check it in code** — `can()` and `@can` accept the enum directly:

```php
// Route
Route::livewire('posts/{post}/edit', 'pages::post.edit')->name('posts.edit')->can(PermissionName::PostEdit);

// Livewire action — always authorize actions too, not just the route:
// each action is its own request and skips route middleware.
$this->authorize(PermissionName::PostEdit);
```

```blade
@can(App\Enums\PermissionName::PostEdit)
    <flux:button>Edit</flux:button>
@endcan
```

For permissions about a model, prefer a policy (`app/Policies/PostPolicy.php`) whose methods call `$user->can(PermissionName::PostEdit)` — see [`PermissionPolicy`](app/Policies/PermissionPolicy.php) for the pattern.

**4. Grant it** — open **Access Control → Roles**, edit a role, tick the permission, then assign that role to users under **Users**.

**5. Test it** with the helpers from [`tests/Pest.php`](tests/Pest.php):

```php
it('lets editors edit posts', function () {
    $this->actingAs(userWithPermissions(PermissionName::PostEdit))
        ->get(route('posts.edit', $post))
        ->assertOk();
});
```

Permissions that are in the enum show a **system** badge on the Permissions page and can't be renamed or deleted there. To retire one, remove the enum case and its usages first; it then becomes deletable from the UI. Permissions created only through the UI (not in the enum) are free-form and fully editable.

---

## ✅ Running tests

```bash
make test       # = podman exec -it laradocker composer test
make lint       # fix code style with Pint
```

`composer test` runs, in order: Pint (style check) → PHPStan level 7 → the Pest suite. It fails on the first problem.

Running only part of the suite:

```bash
podman exec -it laradocker php artisan test --filter="super-admin"                     # by test name
podman exec -it laradocker php artisan test tests/Feature/Authorization                # a directory
podman exec -it laradocker php artisan test "resources/views/pages/role/⚡index.test.php"  # one file
podman exec -it laradocker php artisan test --testsuite=Components                     # Unit | Feature | Components
```

Where tests live:

- `tests/Unit` — no database (enum, validation rules).
- `tests/Feature` — HTTP, policies, seeders, models.
- `resources/views/pages/**/⚡*.test.php` — Livewire page tests, next to the page they test (the `Components` suite).

Tests use an **in-memory SQLite** database (see `phpunit.xml`), so they never touch your dev data. The container has the `pdo_sqlite` driver; if you run tests on the host, your PHP needs it too.

<details>
<summary>Running the suite against PostgreSQL instead</summary>

Useful when you touch queries whose behaviour differs between SQLite and Postgres (e.g. `LIKE` vs `ILIKE`). Uses a separate database, so dev data is safe:

```bash
podman exec larapg psql -U larapg -d larapg_db -c "CREATE DATABASE larapg_test"
podman exec -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_DATABASE=larapg_test \
  -e DB_USERNAME=larapg -e DB_PASSWORD=larapg123 -e DB_URL= \
  laradocker sh -c 'cd /var/www && vendor/bin/pest'
podman exec larapg psql -U larapg -d larapg_db -c "DROP DATABASE larapg_test"
```

</details>

---

## 🔧 Troubleshooting

- **`Permission denied` writing to `storage/` or `bootstrap/cache`** — the containers run as `www-data`: run `make perms`.
- **Page loads without styles** — `npm run dev` isn't running (or run `npm run build` once).
- **Changed permissions don't apply** — Spatie caches permissions; they're flushed automatically when changed through the app, but after editing the DB by hand run `podman exec -it laradocker php artisan permission:cache-reset`.

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/hadiset">Hadi Setiawan</a>
</p>
