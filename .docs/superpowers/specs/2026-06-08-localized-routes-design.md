# Localized routes (Polish URLs) — design spec

**Status:** Approved in brainstorming (2026-06-08)  
**Canonical requirements target:** `.docs/prd.md` (UI language PL)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)

## Summary

Replace English URL path segments with Polish equivalents across the authenticated app and auth flows. Named routes (`transactions.index`, `login`, …) stay unchanged; only URI strings change. Old English paths redirect with HTTP 301 to the new Polish canonical URLs. Application locale is set to `pl` for backend formatting and Inertia/vue-i18n alignment.

Architecture uses a **central segment map in config** so a future per-user locale preference can drive URL generation without rewriting route registration.

## Problem

The UI is Polish (vue-i18n), but URLs remain English (`/transactions`, `/accounts`, `/settings/profile`). This mismatch feels inconsistent for a Polish-first product and makes future per-user locale routing harder if segments are scattered as literals in `routes/*.php`.

## Decisions log

| Topic | Decision |
|-------|----------|
| URL strategy | Translated path segments, **no** `/pl/` locale prefix |
| Canonical locale (now) | Polish only — all generated links use PL segments |
| Old English URLs | Permanent redirect **301**, query string preserved |
| Named routes | **Unchanged** (architecture rule) |
| Route registration | Central map in `config/routes.php` + helper to resolve paths |
| REST action verbs | `Route::resourceVerbs(['create' => 'utworz', 'edit' => 'edytuj'])` |
| Route model parameters | Keep English (`{transaction}`, `{account}`, …) — out of scope to rename |
| Dashboard | `/dashboard` → `/panel` |
| Auth paths | Polish (e.g. `/logowanie`, `/rejestracja`) |
| App locale | `config/app.php` → `'locale' => 'pl'` |
| Per-user locale | **Not implemented now**; config reserves `en` segment map for later |
| Telemetry | `/telemetry/event` unchanged (internal API-style endpoint) |
| Home | `/` unchanged |

## Approach (selected)

**Config-driven segment map (approach 2)** — not a third-party localization package, not a one-off string replace without abstraction.

Rejected alternatives:

- **Direct URI replace in route files** — fast but blocks clean per-user URL generation later.
- **`mcamara/laravel-localization`** — overkill for PL-only; typically assumes locale prefix.

## Segment map (Polish canonical)

### Core domains (resource roots)

| Key | EN (legacy) | PL (canonical) |
|-----|-------------|----------------|
| `transactions` | `transactions` | `transakcje` |
| `accounts` | `accounts` | `konta` |
| `categories` | `categories` | `kategorie` |
| `pockets` | `pockets` | `kieszenie` |
| `imports` | `imports` | `importy` |
| `transfers` | `transfers` | `transfery` |
| `dashboard` | `dashboard` | `panel` |
| `settings` | `settings` | `ustawienia` |
| `budget` | `budget` | `budzet` |

### Nested / action segments

| Context | EN (legacy) | PL (canonical) |
|---------|-------------|----------------|
| Budget views | `monthly` | `miesieczny` |
| Budget views | `yearly` | `roczny` |
| Settings pages | `profile` | `profil` |
| Settings pages | `password` | `haslo` |
| Settings pages | `appearance` | `wyglad` |
| Account patch | `balance` | `saldo` |
| Reorder endpoints | `reorder` | `kolejnosc` |
| Category estimates | `estimates/annual` | `szacunki/roczny` |
| Category estimates | `estimates/monthly` | `szacunki/miesieczny` |
| Import upload | `upload` | `wgraj` |
| Import commit | `commit` | `zatwierdz` |
| Import failed rows root | `import-failed-rows` | `nieudane-wiersze` |
| Dismiss all failed rows | `dismiss-all` | `odrzuc-wszystkie` |
| Dismiss single failed row | `dismiss` | `odrzuc` |
| Transfer candidates | `candidates` | `kandydaci` |
| Confirm candidate | `confirm` | `potwierdz` |
| Reject candidate | `reject` | `odrzuc` |
| Unlink transfer | `unlink` | `odlacz` |
| REST create (global verb) | `create` | `utworz` |
| REST edit (global verb) | `edit` | `edytuj` |

### Auth

| Named route | EN (legacy) | PL (canonical) |
|-------------|-------------|----------------|
| `login` | `/login` | `/logowanie` |
| `register` | `/register` | `/rejestracja` |
| `password.request` | `/forgot-password` | `/reset-hasla` |
| `password.email` (POST) | `/forgot-password` | `/reset-hasla` |
| `password.reset` | `/reset-password/{token}` | `/reset-hasla/{token}` |
| `password.store` (POST) | `/reset-password` | `/reset-hasla` |
| `verification.notice` | `/verify-email` | `/weryfikacja-email` |
| `verification.verify` | `/verify-email/{id}/{hash}` | `/weryfikacja-email/{id}/{hash}` |
| `verification.send` (POST) | `/email/verification-notification` | `/email/weryfikacja` |
| `password.confirm` | `/confirm-password` | `/potwierdz-haslo` |
| `logout` (POST) | `/logout` | `/wyloguj` |

### Example resolved URIs

| Named route | PL URI |
|-------------|--------|
| `transactions.index` | `/transakcje` |
| `transactions.create` | `/transakcje/utworz` |
| `transactions.edit` | `/transakcje/{transaction}/edytuj` |
| `accounts.balance.update` | `/konta/{account}/saldo` |
| `budget.monthly` | `/budzet/miesieczny` |
| `imports.upload` | `/importy/wgraj` |
| `imports.commit` | `/importy/{import}/zatwierdz` |
| `profile.edit` | `/ustawienia/profil` |
| `dashboard` | `/panel` |

## Architecture

### New / modified files

| File | Role |
|------|------|
| `config/routes.php` | Segment keys → PL (and reserved EN map) |
| `app/Support/Routing/LocalizedRoutePaths.php` | Resolve path segments for current locale |
| `app/helpers.php` or support function | `route_path(string $key, ?string $locale = null): string` |
| `routes/redirects.php` | 301 from legacy EN paths to PL canonical |
| `app/Http/Middleware/SetApplicationLocale.php` | `app()->setLocale('pl')` on web requests |
| `bootstrap/app.php` | Register middleware on web group |
| `AppServiceProvider::boot()` | `Route::resourceVerbs([...])` |
| `routes/*.php` | Use path helper instead of English literals |

### Data flow

```
GET /transakcje
  → SetApplicationLocale (pl)
  → transactions.index → Controller → Action
  → Inertia props locale: pl → vue-i18n pl

route('transactions.index') → Ziggy → /transakcje

GET /transactions?sort=date
  → 301 → /transakcje?sort=date
```

- Link generation: Laravel `route()` + Ziggy (frontend) use registered PL URIs.
- Guest/auth middleware: unchanged — bound to named routes, not raw paths.
- Signed URLs (email verification): route **names** unchanged; generated links automatically use PL paths.
- Password reset emails: verify `PasswordResetTest` / `EmailVerificationTest` after change.

### Redirect layer

`routes/redirects.php` loaded after primary routes. Each legacy EN path maps to the PL equivalent via `Route::redirect($from, $to, 301)` or a small redirect controller when query strings / route parameters need preservation.

Rules:

- Preserve query string on redirect.
- Parameterized redirects use the same parameter names (`{transaction}`, `{token}`, …).
- POST endpoints: register redirect only where safe; POST body replay does not happen on 301 — legacy POST paths remain registered as aliases **or** tests/clients migrate to PL (prefer dual registration for POST auth endpoints during transition if needed).

**POST dual registration (auth):** For `POST /login` → keep handling at `/logowanie` only; register `Route::post('login', ...)` pointing to same controller **without** a separate name, or use `Route::match` — implementation plan should pick the simplest approach that keeps external clients working.

## Frontend

| Area | Change |
|------|--------|
| Ziggy | No config change — picks URIs from Laravel route list |
| Vue breadcrumbs | Replace hardcoded `href: '/accounts'` etc. with `route('accounts.index')` (~10 files) |
| `resources/js/i18n.ts` | Default locale alignment: follow Inertia `locale` prop (already in `app.ts`) |
| `resources/js/app.ts` | Fallback locale `'pl'` when prop missing (match backend default) |

## Configuration

```php
// config/app.php
'locale' => env('APP_LOCALE', 'pl'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
```

## Future: per-user locale (out of scope)

When `users.locale` and settings UI ship:

1. Add `users.locale` column + preference in Settings.
2. Extend `LocalizedRoutePaths` to resolve segments for `auth()->user()->locale`.
3. Introduce `LocalizedUrl::route($name, $params, $locale = null)` for link generation — Laravel cannot register two URIs with the same route name, so user-locale URLs are built from the segment map, not duplicate `Route::get` registrations.
4. Pass active locale to Inertia/Ziggy for client-side `route()` parity.
5. Optional: accept both locale URL variants without redirect when user preference differs from bookmarked URL (product decision at that time).

This phase does **not** block the PL-only migration.

## Testing

### New

- `tests/Feature/Routing/LocalizedRoutesTest.php`
  - Canonical PL paths return expected status (200 / redirect to login for protected routes).
  - Legacy EN paths return 301 to PL equivalent.
  - Query string preserved on redirect.

### Update

- Feature tests with hardcoded English paths (~15 files) → `route()` helper or PL paths.
- `tests/Unit/Support/Transactions/TransactionsIndexQueryTest.php` — request URL `/transakcje`.
- `tests/Feature/Security/ProductionUrlSchemeTest.php`, `SecurityHeadersTest.php` — sample URLs.
- Auth tests: login, register, password reset, email verification, profile/settings.

### Verification commands

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact --filter=LocalizedRoutes
./vendor/bin/sail artisan test --compact tests/Feature/Auth
./vendor/bin/sail artisan test --compact
```

## Out of scope

- Locale prefix in URL (`/pl/transakcje`)
- User locale preference UI and DB column (future phase)
- Translating route parameter names (`{transaction}` → `{transakcja}`)
- hreflang / public SEO
- Third-party localization package
- Translating `/telemetry/event` or `/`

## Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Broken bookmarks / external links | 301 redirect layer |
| Feature tests with hardcoded EN paths | Bulk update + `LocalizedRoutesTest` |
| Email links (reset, verify) | Named routes unchanged; add/extend auth feature tests |
| Architecture rule “preserve URIs” | Named routes preserved; URI change is explicit product decision documented here |
| POST to legacy auth URLs | Dual POST registration or documented breaking change — resolve in implementation plan |

## Success criteria

- All user-facing navigation uses Polish URL segments.
- `route('transactions.index')` and Ziggy generate `/transakcje`.
- Legacy `/transactions` redirects 301 to `/transakcje` with query string intact.
- `app()->getLocale()` is `pl` on web requests; Inertia `locale` prop matches.
- Full test suite passes after updates.
