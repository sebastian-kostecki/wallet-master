# Stack technologiczny (skrót)

**wallet-master** — web app budżetu domowego (zakres: `mvp.md`).

## Aplikacja

| Obszar | Technologie |
|--------|----------------|
| Backend | Laravel 13, PHP `^8.2`, Inertia Laravel v2, Ziggy, Tinker, `laravel/mcp` |
| Frontend | Vue 3 + TypeScript, Inertia Vue v2, Vite 6, Tailwind 3 + PostCSS |
| Wejście UI | `resources/js/app.ts` · strony `resources/js/pages/**/*.vue` · `@` → `resources/js` |
| UI | Headless UI, Radix Vue, Lucide, VueUse, CVA / clsx / tailwind-merge |
| Auth | Sesja Laravel, `App\Http\Controllers\Auth\*` (starter Inertia; bez Fortify w `composer.json`) |
| DB | Domyślnie **SQLite** (`database/database.sqlite`). Sail: zwykle **MySQL**, host DNS **`mysql`**. |
| Kolejki | Domyślnie **`database`**; przy Sail możliwy **Redis** (`redis`) po zmianie `QUEUE_CONNECTION`. |

## Laravel Sail — `compose.yaml`

Sieć **`sail`** (bridge). Aplikacja: serwis **`laravel.test`**, obraz **`sail-8.5/app`**, build z `vendor/laravel/sail/runtimes/8.5`, mount `.` → `/var/www/html`, `LARAVEL_SAIL=1`, Xdebug przez `SAIL_XDEBUG_*`.

| Serwis | Obraz | Porty hosta (domyślne) |
|--------|--------|------------------------|
| `laravel.test` | PHP 8.5 (Sail) | `APP_PORT`→80, `VITE_PORT`→5173 (HMR) |
| `mysql` | mysql:8.4 | `FORWARD_DB_PORT`→3306 |
| `redis` | redis:alpine | `FORWARD_REDIS_PORT`→6379 |
| `typesense` | typesense/typesense:27.1 | `FORWARD_TYPESENSE_PORT`→8108 |
| `mailpit` | axllent/mailpit:latest | SMTP `FORWARD_MAILPIT_PORT`→1025, UI `FORWARD_MAILPIT_DASHBOARD_PORT`→8025 |

Wolumeny: `sail-mysql`, `sail-redis`, `sail-typesense`. `laravel.test` zależy od: mysql, redis, typesense, mailpit.

## Polecenia

- **Sail:** `./vendor/bin/sail up -d` · `sail artisan migrate` · `sail npm run dev`
- **Host (bez pełnego stacku Docker):** `composer run dev` — `serve` + `queue:listen` + `pail` + `vite` (`concurrently`)
- **Testy / styl:** Pest 4 + PHPUnit 12 · `vendor/bin/pint` · `npm run lint` / `npm run format` · ESLint 9 + Prettier 3 + vue-tsc
