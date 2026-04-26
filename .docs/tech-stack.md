# Tech stack (short)

**wallet-master** — a household budgeting web app (scope: `mvp.md`).

## Application

| Area            | Tech                                                                                              |
|-----------------|---------------------------------------------------------------------------------------------------|
| Backend         | Laravel 13, PHP `^8.5`, Inertia Laravel v2, Ziggy, Tinker, `laravel/mcp`                          |
| Frontend        | Vue 3 + TypeScript, Inertia Vue v2, Vite 6, Tailwind 3 + PostCSS                                  |
| UI entrypoints  | `resources/js/app.ts` · pages `resources/js/pages/**/*.vue` · `@` → `resources/js`                |
| UI libs         | Headless UI, Radix Vue, Lucide, VueUse, CVA / clsx / tailwind-merge                               |
| Auth            | Laravel session auth, `App\Http\Controllers\Auth\*` (Inertia starter; no Fortify in `composer.json`) |
| DB              | **MySQL**, DNS host **`mysql`**.                                                         |
| Queues          | **Redis** (`redis`).                                                |
| Static analysis | Larastan (PHPStan) `larastan/larastan` `^3.0`                                                     |

## Laravel Sail — `compose.yaml`

Network **`sail`** (bridge). App service **`laravel.test`**, image **`sail-8.5/app`**, built from `vendor/laravel/sail/runtimes/8.5`, mounts `.` → `/var/www/html`, `LARAVEL_SAIL=1`, Xdebug via `SAIL_XDEBUG_*`.

| Service | Image | Host ports (defaults) |
|---------|-------|------------------------|
| `laravel.test` | PHP 8.5 (Sail) | `APP_PORT`→80, `VITE_PORT`→5173 (HMR) |
| `mysql` | mysql:8.4 | `FORWARD_DB_PORT`→3306 |
| `redis` | redis:alpine | `FORWARD_REDIS_PORT`→6379 |
| `typesense` | typesense/typesense:27.1 | `FORWARD_TYPESENSE_PORT`→8108 |
| `mailpit` | axllent/mailpit:latest | SMTP `FORWARD_MAILPIT_PORT`→1025, UI `FORWARD_MAILPIT_DASHBOARD_PORT`→8025 |

Volumes: `sail-mysql`, `sail-redis`, `sail-typesense`. `laravel.test` depends on: mysql, redis, typesense, mailpit.

## Commands

- **Sail:** `./vendor/bin/sail up -d` · `sail artisan migrate` · `sail npm run dev`
- **Host (without the full Docker stack):** `composer run dev` — `serve` + `queue:listen` + `pail` + `vite` (via `concurrently`)
- **Tests / style:** Pest 4 + PHPUnit 12 · `vendor/bin/pint` · `npm run lint` / `npm run format` · ESLint 9 + Prettier 3 + vue-tsc
- **Static analysis:** `./vendor/bin/phpstan analyse` (Larastan)
