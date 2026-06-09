# Localized routes (Polish URLs) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace English URL path segments with Polish canonical paths while keeping named routes unchanged; redirect legacy English URLs with 301; align backend locale to `pl`.

**Architecture:** Central segment map in `config/routes.php`, resolved via `LocalizedRoutePaths` + global `route_path()` helper. Route files use `route_path()` instead of English literals. `routes/redirects.php` registers legacy GET redirects and POST aliases for auth. Middleware sets `app()->setLocale('pl')` on web requests.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Ziggy, Sail for tests.

**Spec:** `.docs/superpowers/specs/2026-06-08-localized-routes-design.md`

**Suggested branch:** `improvement/localized-routes`

---

## File map

| Action | Path |
|--------|------|
| Create | `config/routes.php` |
| Create | `app/Support/Routing/LocalizedRoutePaths.php` |
| Create | `app/Support/Routing/LegacyRouteRedirector.php` |
| Create | `app/helpers.php` |
| Create | `app/Http/Middleware/SetApplicationLocale.php` |
| Create | `routes/redirects.php` |
| Create | `tests/Unit/Support/Routing/LocalizedRoutePathsTest.php` |
| Create | `tests/Feature/Routing/LocalizedRoutesTest.php` |
| Modify | `composer.json` (autoload `files`) |
| Modify | `config/app.php` |
| Modify | `bootstrap/app.php` |
| Modify | `app/Providers/AppServiceProvider.php` |
| Modify | `routes/web.php` |
| Modify | `routes/auth.php` |
| Modify | `routes/accounts.php` |
| Modify | `routes/transactions.php` |
| Modify | `routes/transfers.php` |
| Modify | `routes/categories.php` |
| Modify | `routes/pockets.php` |
| Modify | `routes/budgets.php` |
| Modify | `routes/imports.php` |
| Modify | `routes/settings.php` |
| Modify | `resources/js/app.ts` |
| Modify | ~17 Vue files (hardcoded breadcrumb hrefs) |
| Modify | ~40 Pest feature/unit test files (hardcoded EN paths) |

---

### Task 1: Config and path resolver

**Files:**
- Create: `config/routes.php`
- Create: `app/Support/Routing/LocalizedRoutePaths.php`
- Create: `app/helpers.php`
- Create: `tests/Unit/Support/Routing/LocalizedRoutePathsTest.php`
- Modify: `composer.json`

- [ ] **Step 1: Write failing unit test**

Create `tests/Unit/Support/Routing/LocalizedRoutePathsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Support\Routing\LocalizedRoutePaths;

test('get returns polish segment for known key', function () {
    expect(LocalizedRoutePaths::get('transactions'))->toBe('transakcje');
    expect(LocalizedRoutePaths::get('budget.monthly'))->toBe('budzet/miesieczny');
});

test('get returns key unchanged when missing from map', function () {
    expect(LocalizedRoutePaths::get('telemetry.event'))->toBe('telemetry.event');
});

test('route_path helper delegates to LocalizedRoutePaths', function () {
    expect(route_path('accounts'))->toBe('konta');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Routing/LocalizedRoutePathsTest.php`

Expected: FAIL — class/function not found.

- [ ] **Step 3: Create config**

Create `config/routes.php`:

```php
<?php

declare(strict_types=1);

return [
    'default_locale' => 'pl',

    'segments' => [
        'pl' => [
            'transactions' => 'transakcje',
            'accounts' => 'konta',
            'categories' => 'kategorie',
            'pockets' => 'kieszenie',
            'imports' => 'importy',
            'transfers' => 'transfery',
            'dashboard' => 'panel',
            'settings' => 'ustawienia',
            'budget' => 'budzet',
            'budget.monthly' => 'budzet/miesieczny',
            'budget.yearly' => 'budzet/roczny',
            'settings.profile' => 'ustawienia/profil',
            'settings.password' => 'ustawienia/haslo',
            'settings.appearance' => 'ustawienia/wyglad',
            'accounts.balance' => 'konta/{account}/saldo',
            'categories.reorder' => 'kategorie/kolejnosc',
            'categories.estimates.annual' => 'kategorie/{category}/szacunki/roczny',
            'categories.estimates.monthly' => 'kategorie/{category}/szacunki/miesieczny',
            'pockets.reorder' => 'kieszenie/kolejnosc',
            'imports.upload' => 'importy/wgraj',
            'imports.commit' => 'importy/{import}/zatwierdz',
            'imports.failed_rows.dismiss_all' => 'nieudane-wiersze/odrzuc-wszystkie',
            'imports.failed_rows.dismiss' => 'nieudane-wiersze/{importFailedRow}/odrzuc',
            'transfers.create' => 'transfery/utworz',
            'transfers.candidates.confirm' => 'transfery/kandydaci/{transaction}/potwierdz',
            'transfers.candidates.reject' => 'transfery/kandydaci/{transaction}/odrzuc',
            'transfers.unlink' => 'transfery/{transferId}/odlacz',
            'auth.login' => 'logowanie',
            'auth.register' => 'rejestracja',
            'auth.password.request' => 'reset-hasla',
            'auth.password.reset' => 'reset-hasla/{token}',
            'auth.password.store' => 'reset-hasla',
            'auth.verification.notice' => 'weryfikacja-email',
            'auth.verification.verify' => 'weryfikacja-email/{id}/{hash}',
            'auth.verification.send' => 'email/weryfikacja',
            'auth.password.confirm' => 'potwierdz-haslo',
            'auth.logout' => 'wyloguj',
        ],
        'en' => [
            'transactions' => 'transactions',
            'accounts' => 'accounts',
            'categories' => 'categories',
            'pockets' => 'pockets',
            'imports' => 'imports',
            'transfers' => 'transfers',
            'dashboard' => 'dashboard',
            'settings' => 'settings',
            'budget' => 'budget',
            'budget.monthly' => 'budget/monthly',
            'budget.yearly' => 'budget/yearly',
            'settings.profile' => 'settings/profile',
            'settings.password' => 'settings/password',
            'settings.appearance' => 'settings/appearance',
            'accounts.balance' => 'accounts/{account}/balance',
            'categories.reorder' => 'categories/reorder',
            'categories.estimates.annual' => 'categories/{category}/estimates/annual',
            'categories.estimates.monthly' => 'categories/{category}/estimates/monthly',
            'pockets.reorder' => 'pockets/reorder',
            'imports.upload' => 'imports/upload',
            'imports.commit' => 'imports/{import}/commit',
            'imports.failed_rows.dismiss_all' => 'import-failed-rows/dismiss-all',
            'imports.failed_rows.dismiss' => 'import-failed-rows/{importFailedRow}/dismiss',
            'transfers.create' => 'transfers/create',
            'transfers.candidates.confirm' => 'transfers/candidates/{transaction}/confirm',
            'transfers.candidates.reject' => 'transfers/candidates/{transaction}/reject',
            'transfers.unlink' => 'transfers/{transferId}/unlink',
            'auth.login' => 'login',
            'auth.register' => 'register',
            'auth.password.request' => 'forgot-password',
            'auth.password.reset' => 'reset-password/{token}',
            'auth.password.store' => 'reset-password',
            'auth.verification.notice' => 'verify-email',
            'auth.verification.verify' => 'verify-email/{id}/{hash}',
            'auth.verification.send' => 'email/verification-notification',
            'auth.password.confirm' => 'confirm-password',
            'auth.logout' => 'logout',
        ],
    ],
];
```

- [ ] **Step 4: Create LocalizedRoutePaths**

Create `app/Support/Routing/LocalizedRoutePaths.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Routing;

final class LocalizedRoutePaths
{
    public static function get(string $key, ?string $locale = null): string
    {
        $locale ??= (string) config('routes.default_locale', 'pl');

        $segment = config("routes.segments.{$locale}.{$key}");

        if (is_string($segment)) {
            return $segment;
        }

        return $key;
    }

    public static function legacy(string $key): string
    {
        return self::get($key, 'en');
    }
}
```

- [ ] **Step 5: Create helper and register autoload**

Create `app/helpers.php`:

```php
<?php

declare(strict_types=1);

use App\Support\Routing\LocalizedRoutePaths;

if (! function_exists('route_path')) {
    function route_path(string $key): string
    {
        return LocalizedRoutePaths::get($key);
    }
}
```

In `composer.json`, add under `"autoload"`:

```json
"files": [
    "app/helpers.php"
]
```

Run: `composer dump-autoload`

- [ ] **Step 6: Run unit test**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Routing/LocalizedRoutePathsTest.php`

Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add config/routes.php app/Support/Routing/LocalizedRoutePaths.php app/helpers.php composer.json tests/Unit/Support/Routing/LocalizedRoutePathsTest.php
git commit -m "feat(routing): add localized route path config and resolver"
```

---

### Task 2: Application locale middleware and config

**Files:**
- Create: `app/Http/Middleware/SetApplicationLocale.php`
- Modify: `bootstrap/app.php`
- Modify: `config/app.php`

- [ ] **Step 1: Create middleware**

Create `app/Http/Middleware/SetApplicationLocale.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetApplicationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale((string) config('routes.default_locale', 'pl'));

        return $next($request);
    }
}
```

- [ ] **Step 2: Register middleware**

In `bootstrap/app.php`, prepend to web stack (before Inertia):

```php
use App\Http\Middleware\SetApplicationLocale;

$middleware->web(prepend: [
    SetApplicationLocale::class,
]);
```

Keep existing `append` block for HandleInertiaRequests unchanged.

- [ ] **Step 3: Update app locale default**

In `config/app.php`:

```php
'locale' => env('APP_LOCALE', 'pl'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
```

- [ ] **Step 4: Run pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/SetApplicationLocale.php bootstrap/app.php config/app.php
git commit -m "feat(routing): set application locale to pl on web requests"
```

---

### Task 3: Resource verbs and route file migration

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`
- Modify: `routes/auth.php`
- Modify: `routes/accounts.php`
- Modify: `routes/transactions.php`
- Modify: `routes/transfers.php`
- Modify: `routes/categories.php`
- Modify: `routes/pockets.php`
- Modify: `routes/budgets.php`
- Modify: `routes/imports.php`
- Modify: `routes/settings.php`

- [ ] **Step 1: Register localized resource verbs**

In `app/Providers/AppServiceProvider.php` `boot()` method, add:

```php
use Illuminate\Support\Facades\Route;

Route::resourceVerbs([
    'create' => 'utworz',
    'edit' => 'edytuj',
]);
```

- [ ] **Step 2: Update routes/web.php**

```php
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get(route_path('dashboard'), function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');
```

- [ ] **Step 3: Update routes/transactions.php**

```php
Route::middleware('auth')->group(function () {
    Route::resource(route_path('transactions'), TransactionController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
});
```

- [ ] **Step 4: Update routes/accounts.php**

Replace path literals:

```php
Route::resource(route_path('accounts'), AccountController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->middleware([...]);

Route::patch(route_path('accounts.balance'), [AccountBalanceController::class, 'update'])
    ->middleware('account.active')
    ->name('accounts.balance.update');
```

- [ ] **Step 5: Update routes/budgets.php**

```php
Route::get(route_path('budget.monthly'), [BudgetController::class, 'monthly'])->name('budget.monthly');
Route::get(route_path('budget.yearly'), [BudgetController::class, 'yearly'])->name('budget.yearly');
```

- [ ] **Step 6: Update routes/settings.php**

```php
Route::redirect(route_path('settings'), route_path('settings.profile'));

Route::get(route_path('settings.profile'), [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch(route_path('settings.profile'), [ProfileController::class, 'update'])->name('profile.update');
Route::delete(route_path('settings.profile'), [ProfileController::class, 'destroy'])->name('profile.destroy');

Route::get(route_path('settings.password'), [PasswordController::class, 'edit'])->name('password.edit');
Route::put(route_path('settings.password'), [PasswordController::class, 'update'])->name('password.update');

Route::get(route_path('settings.appearance'), function () {
    return Inertia::render('settings/Appearance');
})->name('appearance');
```

- [ ] **Step 7: Update routes/categories.php**

```php
Route::patch(route_path('categories.reorder'), [CategoryController::class, 'reorder'])
    ->name('categories.reorder');

Route::resource(route_path('categories'), CategoryController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

Route::patch(route_path('categories.estimates.annual'), [CategoryController::class, 'saveAnnualEstimate'])
    ->name('categories.estimates.annual');

Route::patch(route_path('categories.estimates.monthly'), [CategoryController::class, 'saveMonthlyEstimate'])
    ->name('categories.estimates.monthly');
```

- [ ] **Step 8: Update routes/pockets.php**

```php
Route::patch(route_path('pockets.reorder'), [PocketController::class, 'reorder'])->name('pockets.reorder');

Route::resource(route_path('pockets'), PocketController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
```

- [ ] **Step 9: Update routes/imports.php**

```php
Route::get(route_path('imports'), [ImportController::class, 'index'])->name('imports.index');
Route::get(route_path('imports').'/{import}', [ImportController::class, 'show'])->name('imports.show');

Route::post(route_path('imports.failed_rows.dismiss_all'), [ImportFailedRowController::class, 'dismissAll'])
    ->name('import-failed-rows.dismiss-all');
Route::post(route_path('imports.failed_rows.dismiss'), [ImportFailedRowController::class, 'dismiss'])
    ->name('import-failed-rows.dismiss');

Route::middleware('throttle:imports')->group(function () {
    Route::post(route_path('imports.upload'), [ImportController::class, 'upload'])->name('imports.upload');
    Route::post(route_path('imports.commit'), [ImportController::class, 'commit'])->name('imports.commit');
});
```

- [ ] **Step 10: Update routes/transfers.php**

```php
Route::get(route_path('transfers.create'), [TransferController::class, 'create'])->name('transfers.create');
Route::post(route_path('transfers'), [TransferController::class, 'store'])->name('transfers.store');
Route::post(route_path('transfers.candidates.confirm'), [TransferCandidateController::class, 'confirm'])
    ->name('transfers.candidates.confirm');
Route::post(route_path('transfers.candidates.reject'), [TransferCandidateController::class, 'reject'])
    ->name('transfers.candidates.reject');
Route::post(route_path('transfers.unlink'), [TransferController::class, 'unlink'])->name('transfers.unlink');
```

- [ ] **Step 11: Update routes/auth.php**

Replace every path literal with `route_path('auth.*')` keys from config. Example:

```php
Route::get(route_path('auth.login'), [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post(route_path('auth.login'), [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

Route::get(route_path('auth.register'), [RegisteredUserController::class, 'create'])->name('register');
Route::post(route_path('auth.register'), [RegisteredUserController::class, 'store']);
```

Apply same pattern for password reset, verification, confirm-password, logout paths using config keys.

- [ ] **Step 12: Verify route list**

Run: `./vendor/bin/sail artisan route:list --name=transactions`

Expected: URIs use `/transakcje`, `/transakcje/utworz`, etc.

- [ ] **Step 13: Commit**

```bash
git add app/Providers/AppServiceProvider.php routes/
git commit -m "feat(routing): register polish localized route paths"
```

---

### Task 4: Legacy redirects and POST aliases

**Files:**
- Create: `app/Support/Routing/LegacyRouteRedirector.php`
- Create: `routes/redirects.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create LegacyRouteRedirector**

Create `app/Support/Routing/LegacyRouteRedirector.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Routing;

use Illuminate\Support\Facades\Route;

final class LegacyRouteRedirector
{
    public static function register(): void
    {
        $pairs = [
            ['transactions', 'transactions/{transaction}/edit', 'transactions/{transaction}'],
            ['accounts', 'accounts/create', 'accounts/{account}/edit', 'accounts/{account}/balance'],
            ['categories', 'categories/create', 'categories/{category}/edit', 'categories/reorder',
                'categories/{category}/estimates/annual', 'categories/{category}/estimates/monthly'],
            ['pockets', 'pockets/create', 'pockets/{pocket}/edit', 'pockets/reorder'],
            ['imports', 'imports/{import}', 'imports/upload', 'imports/{import}/commit',
                'import-failed-rows/dismiss-all', 'import-failed-rows/{importFailedRow}/dismiss'],
            ['transfers/create', 'transfers', 'transfers/candidates/{transaction}/confirm',
                'transfers/candidates/{transaction}/reject', 'transfers/{transferId}/unlink'],
            ['budget/monthly', 'budget/yearly'],
            ['settings', 'settings/profile', 'settings/password', 'settings/appearance'],
            ['dashboard'],
            ['login', 'register', 'forgot-password', 'reset-password/{token}', 'reset-password',
                'verify-email', 'verify-email/{id}/{hash}', 'email/verification-notification',
                'confirm-password'],
        ];

        $flatLegacy = collect($pairs)->flatten()->all();

        foreach ($flatLegacy as $legacyPath) {
            $target = self::resolveTarget($legacyPath);

            if ($target !== null && $target !== $legacyPath) {
                Route::redirect($legacyPath, $target, 301);
            }
        }
    }

    private static function resolveTarget(string $legacyPath): ?string
    {
        $map = [
            'transactions' => route_path('transactions'),
            'transactions/create' => route_path('transactions').'/utworz',
            'transactions/{transaction}/edit' => route_path('transactions').'/{transaction}/edytuj',
            'transactions/{transaction}' => route_path('transactions').'/{transaction}',
            'accounts' => route_path('accounts'),
            'accounts/create' => route_path('accounts').'/utworz',
            'accounts/{account}/edit' => route_path('accounts').'/{account}/edytuj',
            'accounts/{account}/balance' => route_path('accounts.balance'),
            'categories' => route_path('categories'),
            'categories/create' => route_path('categories').'/utworz',
            'categories/{category}/edit' => route_path('categories').'/{category}/edytuj',
            'categories/reorder' => route_path('categories.reorder'),
            'categories/{category}/estimates/annual' => route_path('categories.estimates.annual'),
            'categories/{category}/estimates/monthly' => route_path('categories.estimates.monthly'),
            'pockets' => route_path('pockets'),
            'pockets/create' => route_path('pockets').'/utworz',
            'pockets/{pocket}/edit' => route_path('pockets').'/{pocket}/edytuj',
            'pockets/reorder' => route_path('pockets.reorder'),
            'imports' => route_path('imports'),
            'imports/{import}' => route_path('imports').'/{import}',
            'imports/upload' => route_path('imports.upload'),
            'imports/{import}/commit' => route_path('imports.commit'),
            'import-failed-rows/dismiss-all' => route_path('imports.failed_rows.dismiss_all'),
            'import-failed-rows/{importFailedRow}/dismiss' => route_path('imports.failed_rows.dismiss'),
            'transfers/create' => route_path('transfers.create'),
            'transfers' => route_path('transfers'),
            'transfers/candidates/{transaction}/confirm' => route_path('transfers.candidates.confirm'),
            'transfers/candidates/{transaction}/reject' => route_path('transfers.candidates.reject'),
            'transfers/{transferId}/unlink' => route_path('transfers.unlink'),
            'budget/monthly' => route_path('budget.monthly'),
            'budget/yearly' => route_path('budget.yearly'),
            'settings' => route_path('settings'),
            'settings/profile' => route_path('settings.profile'),
            'settings/password' => route_path('settings.password'),
            'settings/appearance' => route_path('settings.appearance'),
            'dashboard' => route_path('dashboard'),
            'login' => route_path('auth.login'),
            'register' => route_path('auth.register'),
            'forgot-password' => route_path('auth.password.request'),
            'reset-password/{token}' => route_path('auth.password.reset'),
            'reset-password' => route_path('auth.password.store'),
            'verify-email' => route_path('auth.verification.notice'),
            'verify-email/{id}/{hash}' => route_path('auth.verification.verify'),
            'email/verification-notification' => route_path('auth.verification.send'),
            'confirm-password' => route_path('auth.password.confirm'),
        ];

        return $map[$legacyPath] ?? null;
    }
}
```

- [ ] **Step 2: Create routes/redirects.php**

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Support\Routing\LegacyRouteRedirector;
use App\Support\Routing\LocalizedRoutePaths;
use Illuminate\Support\Facades\Route;

LegacyRouteRedirector::register();

Route::post(LocalizedRoutePaths::legacy('auth.login'), [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1']);
Route::post(LocalizedRoutePaths::legacy('auth.register'), [RegisteredUserController::class, 'store'])
    ->middleware(['guest', 'registration.enabled']);
Route::post(LocalizedRoutePaths::legacy('auth.password.request'), [PasswordResetLinkController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1']);
Route::post(LocalizedRoutePaths::legacy('auth.password.store'), [NewPasswordController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1']);
Route::post(LocalizedRoutePaths::legacy('auth.verification.send'), [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1']);
Route::post(LocalizedRoutePaths::legacy('auth.password.confirm'), [ConfirmablePasswordController::class, 'store'])
    ->middleware('auth');
Route::post(LocalizedRoutePaths::legacy('auth.logout'), [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth');
```

- [ ] **Step 3: Load redirects from routes/web.php**

At end of `routes/web.php`:

```php
require __DIR__.'/redirects.php';
```

- [ ] **Step 4: Commit**

```bash
git add app/Support/Routing/LegacyRouteRedirector.php routes/redirects.php routes/web.php
git commit -m "feat(routing): add legacy english redirects and post aliases"
```

---

### Task 5: LocalizedRoutes feature test (TDD verification)

**Files:**
- Create: `tests/Feature/Routing/LocalizedRoutesTest.php`

- [ ] **Step 1: Write feature test**

Create `tests/Feature/Routing/LocalizedRoutesTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;

test('canonical polish transaction index is reachable when authenticated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/transakcje')->assertOk();
});

test('legacy english transaction index redirects to polish with query string', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/transactions?sort=date&direction=desc')
        ->assertRedirect('/transakcje?sort=date&direction=desc');
});

test('login page renders at polish path', function () {
    $this->get('/logowanie')->assertOk();
});

test('legacy login path redirects to polish login', function () {
    $this->get('/login')->assertRedirect('/logowanie');
});

test('named route helper generates polish paths', function () {
    expect(route('transactions.index', absolute: false))->toBe('/transakcje');
    expect(route('login', absolute: false))->toBe('/logowanie');
    expect(route('dashboard', absolute: false))->toBe('/panel');
});

test('legacy post login still authenticates', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
```

- [ ] **Step 2: Run test**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Routing/LocalizedRoutesTest.php`

Expected: PASS (after Tasks 1–4).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Routing/LocalizedRoutesTest.php
git commit -m "test(routing): cover polish canonical urls and legacy redirects"
```

---

### Task 6: Update Pest tests with hardcoded English paths

**Files:**
- Modify: all files matched by `tests/**/*.php` containing hardcoded `/login`, `/transactions`, etc.

- [ ] **Step 1: Replace paths using route() where possible**

Preferred pattern in tests:

```php
// Before
$this->get('/login')

// After
$this->get(route('login', absolute: false))

// Before
$this->actingAs($user)->get('/budget/monthly?year=2026&month=3')

// After
$this->actingAs($user)->get(route('budget.monthly', ['year' => 2026, 'month' => 3], absolute: false))
```

Priority files (update all occurrences):

- `tests/Feature/Auth/*.php`
- `tests/Feature/Settings/*.php`
- `tests/Feature/DashboardTest.php`
- `tests/Feature/Accounts/*.php`
- `tests/Feature/Transactions/*.php`
- `tests/Feature/Transfers/*.php`
- `tests/Feature/Categories/*.php`
- `tests/Feature/Budgets/*.php`
- `tests/Feature/Pockets/*.php`
- `tests/Feature/Imports/*.php`
- `tests/Feature/Security/*.php`
- `tests/Feature/Telemetry/*.php`
- `tests/Unit/Support/Transactions/TransactionsIndexQueryTest.php`

For `TransactionsIndexQueryTest.php`, change request URL:

```php
Route::get('/'.route_path('transactions'), fn () => 'ok')->name('transactions.index');
$request = Request::create('/transakcje', 'GET', [...]);
```

- [ ] **Step 2: Run affected test directories**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Auth
./vendor/bin/sail artisan test --compact tests/Feature/Transactions
./vendor/bin/sail artisan test --compact tests/Feature/Accounts
./vendor/bin/sail artisan test --compact tests/Feature/Budgets
./vendor/bin/sail artisan test --compact tests/Feature/Categories
./vendor/bin/sail artisan test --compact tests/Feature/Transfers
./vendor/bin/sail artisan test --compact tests/Feature/Settings
./vendor/bin/sail artisan test --compact tests/Unit/Support/Transactions/TransactionsIndexQueryTest.php
```

Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add tests/
git commit -m "test: use localized routes in feature and unit tests"
```

---

### Task 7: Frontend hardcoded hrefs and locale fallback

**Files:**
- Modify: `resources/js/app.ts`
- Modify: Vue files with hardcoded breadcrumb hrefs (17 files from grep)

- [ ] **Step 1: Fix locale fallback in app.ts**

Change fallback from `'pl'` when prop missing (already partially there); ensure:

```typescript
const initialLocale = (props.initialPage.props.locale as string | undefined) ?? 'pl';
const resolvedLocale: SupportedLocale = supportedLocales.includes(initialLocale as SupportedLocale)
    ? (initialLocale as SupportedLocale)
    : 'pl';
```

- [ ] **Step 2: Replace hardcoded hrefs with route()**

Example in `resources/js/components/AppSidebar.vue`:

```typescript
import { route } from 'ziggy-js';

// Before: href: '/dashboard'
{ title: t('nav.dashboard'), href: route('dashboard') },
{ title: t('nav.accounts'), href: route('accounts.index') },
```

Apply to:

- `resources/js/components/AppSidebar.vue`
- `resources/js/components/AppHeader.vue`
- `resources/js/pages/Dashboard.vue`
- `resources/js/pages/transactions/Index.vue`
- `resources/js/pages/transactions/Create.vue`
- `resources/js/pages/transfers/Create.vue`
- `resources/js/pages/accounts/Index.vue`
- `resources/js/pages/accounts/Create.vue`
- `resources/js/pages/accounts/Edit.vue`
- `resources/js/pages/settings/Profile.vue`
- `resources/js/pages/settings/Password.vue`
- `resources/js/pages/settings/Appearance.vue`
- `resources/js/layouts/settings/Layout.vue`

Add `import { route } from 'ziggy-js';` where missing.

- [ ] **Step 3: Run frontend lint**

Run: `./vendor/bin/sail npm run lint`

Expected: no new errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/
git commit -m "fix(frontend): use ziggy routes for localized polish urls"
```

---

### Task 8: Final verification and docs

**Files:**
- Modify: `.docs/checklist.md` (optional checkbox if URL localization tracked)

- [ ] **Step 1: Run pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 2: Run full test suite**

Run: `./vendor/bin/sail artisan test --compact`

Expected: all tests PASS.

- [ ] **Step 3: Run phpstan (routes/middleware touched)**

Run: `./vendor/bin/phpstan analyse`

Expected: no new errors.

- [ ] **Step 4: Smoke-check route generation**

Run: `./vendor/bin/sail artisan route:list --name=login --name=transactions.index --name=dashboard`

Expected: `/logowanie`, `/transakcje`, `/panel`.

- [ ] **Step 5: Commit any remaining fixes**

```bash
git add -A
git commit -m "chore: finalize localized routes verification"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Config segment map PL + EN reserve | Task 1 |
| `route_path()` helper | Task 1 |
| Polish route registration | Task 3 |
| `Route::resourceVerbs` | Task 3 |
| 301 legacy redirects | Task 4 |
| POST auth aliases | Task 4 |
| `app()->setLocale('pl')` | Task 2 |
| `config/app.php` locale pl | Task 2 |
| LocalizedRoutesTest | Task 5 |
| Update feature tests | Task 6 |
| Vue hardcoded hrefs → route() | Task 7 |
| Ziggy picks PL URIs | Task 3 (automatic) |
| Telemetry `/telemetry/event` unchanged | No change (out of scope) |
| Home `/` unchanged | No change |

## POST legacy decision (resolved)

Dual POST registration on English auth paths in `routes/redirects.php` (Task 4). Polish paths remain canonical named/canonical handlers in `routes/auth.php`. No 301 for POST.
