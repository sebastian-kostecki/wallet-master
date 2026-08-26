# Routes — Inertia page mapping

Canonical locale is **Polish** (`config/routes.php` `default_locale: pl`). `route_path()` maps named-route keys to URL segments. Laravel resource verbs stay English (`/create`, `/edit`). Legacy English paths redirect (see `routes/redirects.php`).

Layout: authenticated app pages use `AppLayout` → `AppSidebarLayout`. Settings pages nest `settings/Layout.vue` inside `AppLayout`.

## Page map

| Named route | URL (pl) | Vue page | Layout | Renders |
|---|---|---|---|---|
| `home` | `/` | `resources/js/pages/Welcome.vue` | none (marketing) | Marketing landing |
| `dashboard` | `/panel` | `resources/js/pages/Dashboard.vue` | AppLayout | Title, 3 quick-action cards (accounts/transactions/pockets), preview panel |
| `accounts.index` | `/konta` | `resources/js/pages/accounts/Index.vue` | AppLayout | Accounts list/grid, summary card, view toggle, adjust/delete dialogs |
| `accounts.create` | `/konta/create` | `resources/js/pages/accounts/Create.vue` | AppLayout | Create-account form (name, bank, type, currency, opening balance) |
| `accounts.edit` | `/konta/{account}/edit` | `resources/js/pages/accounts/Edit.vue` | AppLayout | Edit account + adjust balance + delete |
| `transactions.index` | `/transakcje` | `resources/js/pages/transactions/Index.vue` | AppLayout | Filterable transaction table, import, transfer candidates, pagination, row actions |
| `transactions.create` | `/transakcje/create` | `resources/js/pages/transactions/Create.vue` | AppLayout | Create income/expense form (account, amount, category, pocket, dates) |
| `transactions.edit` | `/transakcje/{transaction}/edit` | `resources/js/pages/transactions/Edit.vue` | AppLayout | Edit transaction + delete + unlink transfer |
| `transfers.create` | `/transfery/utworz` | `resources/js/pages/transfers/Create.vue` | AppLayout | Transfer between accounts form |
| `categories.index` | `/kategorie` | `resources/js/pages/categories/Index.vue` | AppLayout | Drag-reorder expense/income lists, create/edit/delete |
| `categories.create` | `/kategorie/create` | `resources/js/pages/categories/Create.vue` | AppLayout | Category form (name, type, icon, color) |
| `categories.edit` | `/kategorie/{category}/edit` | `resources/js/pages/categories/Edit.vue` | AppLayout | Edit category form |
| `pockets.index` | `/kieszenie` | `resources/js/pages/pockets/Index.vue` | AppLayout | Pocket cards with progress, archive filter, drag-reorder |
| `pockets.create` | `/kieszenie/create` | `resources/js/pages/pockets/Create.vue` | AppLayout | Create pocket (name, icon, color, target) |
| `pockets.edit` | `/kieszenie/{pocket}/edit` | `resources/js/pages/pockets/Edit.vue` | AppLayout | Edit pocket |
| `budget.monthly` | `/budzet/miesieczny` | `resources/js/pages/budget/Monthly.vue` | AppLayout | Monthly budget table: categories + pockets + summary |
| `budget.yearly` | `/budzet/roczny` | `resources/js/pages/budget/Yearly.vue` | AppLayout | Yearly budget table + summary |
| `profile.edit` | `/ustawienia/profil` | `resources/js/pages/settings/Profile.vue` | AppLayout + settings/Layout | Name/email form + delete user |
| `password.edit` | `/ustawienia/haslo` | `resources/js/pages/settings/Password.vue` | AppLayout + settings/Layout | Change password form |
| `appearance` | `/ustawienia/wyglad` | `resources/js/pages/settings/Appearance.vue` | AppLayout + settings/Layout | Light/dark appearance tabs |
| settings redirect | `/ustawienia` → `/ustawienia/profil` | — | — | Redirect |

English legacy equivalents: `/dashboard`, `/accounts`, `/transactions`, `/transfers/create`, `/categories`, `/pockets`, `/budget/monthly`, `/budget/yearly`, `/settings/profile`.

## Route file excerpts

### `routes/web.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get(route_path('dashboard'), function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/imports.php';
require __DIR__.'/categories.php';
require __DIR__.'/pockets.php';
require __DIR__.'/budgets.php';
require __DIR__.'/accounts.php';
require __DIR__.'/transactions.php';
require __DIR__.'/transfers.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/telemetry.php';
require __DIR__.'/redirects.php';
```

### `app/helpers.php`

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

### `config/routes.php` (path segments)

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
            'categories.estimates.yearly-plan' => 'kategorie/{category}/szacunki/plan-roczny',
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
            'auth.password.store' => 'reset-hasla/zapis',
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
            'categories.estimates.yearly-plan' => 'categories/{category}/estimates/yearly-plan',
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

### `routes/accounts.php`

```php
<?php

use App\Http\Controllers\Accounts\AccountBalanceController;
use App\Http\Controllers\Accounts\AccountController;
use App\Models\Account;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('account', fn (string $value) => Account::withTrashed()->findOrFail($value));

    Route::resource(route_path('accounts'), AccountController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->names('accounts')
        ->parameters([route_path('accounts') => 'account'])
        ->middleware([
            'edit' => 'account.active',
            'update' => 'account.active',
            'destroy' => 'account.active',
        ]);

    Route::patch(route_path('accounts.balance'), [AccountBalanceController::class, 'update'])
        ->middleware('account.active')
        ->name('accounts.balance.update');
});
```

### `routes/transactions.php`

```php
<?php

use App\Http\Controllers\Transactions\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource(route_path('transactions'), TransactionController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->names('transactions')
        ->parameters([route_path('transactions') => 'transaction']);
});
```

### `routes/transfers.php`

```php
<?php

use App\Http\Controllers\Transfers\TransferCandidateController;
use App\Http\Controllers\Transfers\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get(route_path('transfers.create'), [TransferController::class, 'create'])->name('transfers.create');
    Route::post(route_path('transfers'), [TransferController::class, 'store'])->name('transfers.store');
    Route::post(route_path('transfers.candidates.confirm'), [TransferCandidateController::class, 'confirm'])
        ->name('transfers.candidates.confirm');
    Route::post(route_path('transfers.candidates.reject'), [TransferCandidateController::class, 'reject'])
        ->name('transfers.candidates.reject');
    Route::post(route_path('transfers.unlink'), [TransferController::class, 'unlink'])->name('transfers.unlink');
});
```

### `routes/categories.php`

```php
<?php

use App\Http\Controllers\Categories\CategoryController;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('category', fn (string $value) => Category::query()->findOrFail($value));

    Route::patch(route_path('categories.reorder'), [CategoryController::class, 'reorder'])
        ->name('categories.reorder');

    Route::resource(route_path('categories'), CategoryController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->names('categories')
        ->parameters([route_path('categories') => 'category']);

    Route::patch(route_path('categories.estimates.annual'), [CategoryController::class, 'saveAnnualEstimate'])
        ->name('categories.estimates.annual');

    Route::patch(route_path('categories.estimates.monthly'), [CategoryController::class, 'saveMonthlyEstimate'])
        ->name('categories.estimates.monthly');

    Route::patch(route_path('categories.estimates.yearly-plan'), [CategoryController::class, 'saveYearlyCategoryPlan'])
        ->name('categories.estimates.yearly-plan');
});
```

### `routes/pockets.php`

```php
<?php

use App\Http\Controllers\Pockets\PocketController;
use App\Models\Pocket;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('pocket', fn (string $value) => Pocket::query()->findOrFail($value));

    Route::patch(route_path('pockets.reorder'), [PocketController::class, 'reorder'])->name('pockets.reorder');

    Route::resource(route_path('pockets'), PocketController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->names('pockets')
        ->parameters([route_path('pockets') => 'pocket']);
});
```

### `routes/budgets.php`

```php
<?php

use App\Http\Controllers\Budgets\BudgetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get(route_path('budget.monthly'), [BudgetController::class, 'monthly'])->name('budget.monthly');
    Route::get(route_path('budget.yearly'), [BudgetController::class, 'yearly'])->name('budget.yearly');
});
```

### `routes/settings.php`

```php
<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect(route_path('settings'), route_path('settings.profile'));

    Route::get(route_path('settings.profile'), [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch(route_path('settings.profile'), [ProfileController::class, 'update'])->name('profile.update');
    Route::delete(route_path('settings.profile'), [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get(route_path('settings.password'), [PasswordController::class, 'edit'])->name('password.edit');
    Route::put(route_path('settings.password'), [PasswordController::class, 'update'])->name('password.update');

    Route::get(route_path('settings.appearance'), function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});
```

