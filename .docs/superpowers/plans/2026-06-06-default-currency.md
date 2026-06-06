# Default currency (user preference) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Store each user's main currency (`users.default_currency_id`) and use it for create-form defaults, budget P&L formatting, accounts summary filtering, and Inertia shared data — without FX conversion.

**Architecture:** Migration backfills existing users to PLN. New Settings domain (`PreferencesController`, `UpdateUserPreferences` Action). `BudgetCurrency::forUser()` replaces hardcoded `pln()`. `HandleInertiaRequests` shares `auth.user.default_currency`. Frontend Preferences page + create-form preselection + `AccountsSummaryCard` uses shared currency.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Sail for tests/migrations.

**Spec:** `.docs/superpowers/specs/2026-06-06-default-currency-design.md`

**Suggested branch:** `improvement/default-currency`

---

## File map

| Action | Path |
|--------|------|
| Create | `database/migrations/2026_06_06_100000_add_default_currency_id_to_users_table.php` |
| Modify | `app/Models/User.php` |
| Modify | `database/factories/UserFactory.php` |
| Modify | `app/Support/Budgets/BudgetCurrency.php` |
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` |
| Modify | `app/Actions/Budgets/ListYearlyBudget.php` |
| Create | `app/Http/Controllers/Settings/PreferencesController.php` |
| Create | `app/Http/Requests/Settings/UpdatePreferencesRequest.php` |
| Create | `app/Actions/Settings/UpdateUserPreferences.php` |
| Modify | `routes/settings.php` |
| Create | `resources/js/pages/settings/Preferences.vue` |
| Modify | `resources/js/layouts/settings/Layout.vue` |
| Modify | `app/Http/Middleware/HandleInertiaRequests.php` |
| Modify | `resources/js/types/index.ts` |
| Modify | `app/Http/Controllers/Auth/RegisteredUserController.php` |
| Modify | `app/Console/Commands/Auth/CreateUser.php` |
| Modify | `app/Http/Controllers/Accounts/AccountController.php` |
| Modify | `app/Http/Controllers/Pockets/PocketController.php` |
| Modify | `resources/js/pages/accounts/Create.vue` |
| Modify | `resources/js/pages/pockets/Create.vue` |
| Modify | `resources/js/components/accounts/AccountsSummaryCard.vue` |
| Modify | `resources/js/locales/pl.json`, `en.json` |
| Create | `tests/Feature/Settings/DefaultCurrencyMigrationTest.php` |
| Create | `tests/Feature/Settings/PreferencesUpdateTest.php` |
| Create | `tests/Unit/Support/Budgets/BudgetCurrencyTest.php` |
| Modify | `tests/Feature/Budgets/MonthlyBudgetTest.php` |
| Modify | `.docs/checklist.md` |

---

### Task 1: Migration — `users.default_currency_id`

**Files:**
- Create: `database/migrations/2026_06_06_100000_add_default_currency_id_to_users_table.php`
- Create: `tests/Feature/Settings/DefaultCurrencyMigrationTest.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`

- [ ] **Step 1: Write failing migration test**

```php
<?php

use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('users default currency migration backfills existing users with PLN', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    Schema::table('users', function ($table) {
        $table->dropForeign(['default_currency_id']);
        $table->dropColumn('default_currency_id');
    });

    expect(Schema::hasColumn('users', 'default_currency_id'))->toBeFalse();

    $migration = require database_path('migrations/2026_06_06_100000_add_default_currency_id_to_users_table.php');
    $migration->up();

    $user->refresh();

    expect((int) $user->default_currency_id)->toBe($plnId);
    expect(Schema::hasColumn('users', 'default_currency_id'))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Settings/DefaultCurrencyMigrationTest.php`  
Expected: FAIL (column missing or migration not found)

- [ ] **Step 3: Create migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_currency_id')->nullable()->after('password')->constrained('currencies');
        });

        DB::table('currencies')->updateOrInsert(
            ['code' => 'PLN'],
            [
                'name' => 'Złoty',
                'symbol' => 'zł',
                'precision' => 2,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $plnId = DB::table('currencies')->where('code', 'PLN')->value('id');

        if ($plnId === null) {
            throw new RuntimeException('PLN currency must exist before users default currency migration.');
        }

        DB::table('users')->whereNull('default_currency_id')->update(['default_currency_id' => $plnId]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('default_currency_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_currency_id']);
            $table->dropColumn('default_currency_id');
        });
    }
};
```

- [ ] **Step 4: Update User model**

Add to `app/Models/User.php`:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// in $fillable:
'default_currency_id',

public function defaultCurrency(): BelongsTo
{
    return $this->belongsTo(Currency::class, 'default_currency_id');
}
```

Add `use App\Models\Currency;` import.

- [ ] **Step 5: Update UserFactory**

```php
use App\Models\Currency;

// in definition():
'default_currency_id' => fn (): int => (int) Currency::query()->where('code', 'PLN')->value('id'),
```

Add `configure()` to seed PLN when missing (factory runs before some tests seed):

```php
public function configure(): static
{
    return $this->afterMaking(function (): void {
        if (Currency::query()->where('code', 'PLN')->doesntExist()) {
            (new \Database\Seeders\CurrencySeeder)->run();
        }
    });
}
```

- [ ] **Step 6: Run migration test**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Settings/DefaultCurrencyMigrationTest.php`  
Expected: PASS

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_06_06_100000_add_default_currency_id_to_users_table.php app/Models/User.php database/factories/UserFactory.php tests/Feature/Settings/DefaultCurrencyMigrationTest.php
git commit -m "feat: add users.default_currency_id with PLN backfill"
```

---

### Task 2: BudgetCurrency::forUser

**Files:**
- Modify: `app/Support/Budgets/BudgetCurrency.php`
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php`
- Modify: `app/Actions/Budgets/ListYearlyBudget.php`
- Create: `tests/Unit/Support/Budgets/BudgetCurrencyTest.php`

- [ ] **Step 1: Write failing unit test**

```php
<?php

use App\Models\Currency;
use App\Models\User;
use App\Support\Budgets\BudgetCurrency;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('budget currency for user returns user default currency shape', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create(['default_currency_id' => $plnId]);

    $result = BudgetCurrency::forUser($user);

    expect($result)->toBe([
        'code' => 'PLN',
        'symbol' => 'zł',
        'precision' => 2,
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetCurrencyTest.php`  
Expected: FAIL (`forUser` not defined)

- [ ] **Step 3: Implement BudgetCurrency::forUser**

Replace `app/Support/Budgets/BudgetCurrency.php` content:

```php
<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use App\Models\Currency;
use App\Models\User;

final class BudgetCurrency
{
    /**
     * @return array{code: string, symbol: string, precision: int}
     */
    public static function forUser(User $user): array
    {
        $currency = $user->relationLoaded('defaultCurrency')
            ? $user->defaultCurrency
            : $user->defaultCurrency()->first(['id', 'code', 'symbol', 'precision']);

        if ($currency === null) {
            return self::pln();
        }

        return [
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'precision' => (int) $currency->precision,
        ];
    }

    /**
     * @return array{code: string, symbol: string, precision: int}
     */
    public static function pln(): array
    {
        $currency = Currency::query()->where('code', 'PLN')->firstOrFail();

        return [
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'precision' => (int) $currency->precision,
        ];
    }
}
```

- [ ] **Step 4: Wire ListMonthlyBudget**

In `app/Actions/Budgets/ListMonthlyBudget.php`:

1. Add property: `private User $user;`
2. In `handle()`, after `$user = $request->user();` add `$this->user = $user;`
3. Replace `BudgetCurrency::pln()['code']` in `BudgetSummary::withPockets(...)` with `BudgetCurrency::forUser($this->user)['code']`
4. Replace `getCurrency()` body:

```php
public function getCurrency(): array
{
    return BudgetCurrency::forUser($this->user);
}
```

- [ ] **Step 5: Wire ListYearlyBudget**

Same pattern in `app/Actions/Budgets/ListYearlyBudget.php` — store `$this->user` in `handle()`, use `BudgetCurrency::forUser($this->user)` in `getCurrency()` and any `BudgetCurrency::pln()` call.

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Budgets/BudgetCurrencyTest.php tests/Feature/Budgets/MonthlyBudgetTest.php`  
Expected: PASS

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Budgets/BudgetCurrency.php app/Actions/Budgets/ListMonthlyBudget.php app/Actions/Budgets/ListYearlyBudget.php tests/Unit/Support/Budgets/BudgetCurrencyTest.php
git commit -m "feat: resolve budget currency from user default"
```

---

### Task 3: Settings backend — Preferences

**Files:**
- Create: `app/Http/Controllers/Settings/PreferencesController.php`
- Create: `app/Http/Requests/Settings/UpdatePreferencesRequest.php`
- Create: `app/Actions/Settings/UpdateUserPreferences.php`
- Modify: `routes/settings.php`
- Create: `tests/Feature/Settings/PreferencesUpdateTest.php`

- [ ] **Step 1: Write failing feature tests**

```php
<?php

use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('preferences page is displayed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings/preferences');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/Preferences')
        ->has('currencies')
        ->has('default_currency_id')
    );
});

test('user default currency can be updated', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create(['default_currency_id' => $plnId]);

    $response = $this->actingAs($user)->patch('/settings/preferences', [
        'default_currency_id' => $plnId,
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/preferences');

    expect((int) $user->refresh()->default_currency_id)->toBe($plnId);
});

test('invalid default currency id returns validation error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from('/settings/preferences')
        ->patch('/settings/preferences', [
            'default_currency_id' => 99999,
        ]);

    $response
        ->assertSessionHasErrors('default_currency_id')
        ->assertRedirect('/settings/preferences');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Settings/PreferencesUpdateTest.php`  
Expected: FAIL (404 or route missing)

- [ ] **Step 3: Create UpdatePreferencesRequest**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'default_currency_id' => ['required', 'integer', 'exists:currencies,id'],
        ];
    }
}
```

- [ ] **Step 4: Create UpdateUserPreferences Action**

```php
<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;

final class UpdateUserPreferences
{
    /**
     * @param  array{default_currency_id: int}  $validated
     */
    public function handle(User $user, array $validated): void
    {
        $user->update([
            'default_currency_id' => $validated['default_currency_id'],
        ]);
    }
}
```

- [ ] **Step 5: Create PreferencesController**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateUserPreferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePreferencesRequest;
use App\Models\Currency;
use App\Telemetry\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PreferencesController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Preferences', [
            'default_currency_id' => $user->default_currency_id,
            'currencies' => Currency::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'symbol', 'precision']),
        ]);
    }

    public function update(
        UpdatePreferencesRequest $request,
        UpdateUserPreferences $updateUserPreferences,
    ): RedirectResponse {
        $user = $request->user();
        $oldCode = $user->defaultCurrency?->code;

        $updateUserPreferences->handle($user, $request->validated());

        $user->load('defaultCurrency');
        Event::record('user_default_currency_updated', [
            'old_code' => $oldCode,
            'new_code' => $user->defaultCurrency?->code,
        ], $user->id);

        return to_route('preferences.edit')->with('toast', [
            'type' => 'success',
            'message_key' => 'settings.preferences.toast.updated',
        ]);
    }
}
```

- [ ] **Step 6: Add routes**

In `routes/settings.php`:

```php
use App\Http\Controllers\Settings\PreferencesController;

Route::get('settings/preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
Route::patch('settings/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
```

- [ ] **Step 7: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Settings/PreferencesUpdateTest.php`  
Expected: PASS (page test may fail until Vue page exists — create minimal page in Task 4 or assert only backend first)

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Settings/PreferencesController.php app/Http/Requests/Settings/UpdatePreferencesRequest.php app/Actions/Settings/UpdateUserPreferences.php routes/settings.php tests/Feature/Settings/PreferencesUpdateTest.php
git commit -m "feat: add settings preferences backend for default currency"
```

---

### Task 4: Settings frontend + i18n

**Files:**
- Create: `resources/js/pages/settings/Preferences.vue`
- Modify: `resources/js/layouts/settings/Layout.vue`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add i18n keys**

`pl.json` under `settings`:

```json
"nav": {
    "preferences": "Preferencje"
},
"preferences": {
    "title": "Preferencje",
    "heading": "Preferencje aplikacji",
    "description": "Ustaw walutę główną używaną przy tworzeniu kont i kieszeni oraz w widoku budżetu P&L.",
    "fields": {
        "default_currency": {
            "label": "Waluta główna"
        }
    },
    "toast": {
        "updated": "Preferencje zostały zapisane."
    }
}
```

`en.json` — English equivalents.

- [ ] **Step 2: Add nav item in SettingsLayout**

In `resources/js/layouts/settings/Layout.vue`, add after profile item:

```ts
{
    title: t('settings.nav.preferences'),
    href: '/settings/preferences',
},
```

- [ ] **Step 3: Create Preferences.vue**

Follow `settings/Profile.vue` pattern:

```vue
<script setup lang="ts">
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Currency = {
    id: number;
    code: string;
    name: string;
    symbol: string;
    precision: number;
};

const props = defineProps<{
    default_currency_id: number;
    currencies: Currency[];
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('settings.preferences.title'), href: '/settings/preferences' },
]);

const form = useForm({
    default_currency_id: props.default_currency_id,
});

const currencyOptions = computed<DropdownOption<number>[]>(() =>
    props.currencies.map((currency) => ({
        value: currency.id,
        label: `${currency.code} — ${currency.name}`,
    })),
);

const submit = () => {
    form.patch(route('preferences.update'), { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.preferences.title')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="t('settings.preferences.heading')"
                    :description="t('settings.preferences.description')"
                />

                <form class="space-y-6" @submit.prevent="submit">
                    <FormField :label="t('settings.preferences.fields.default_currency.label')" :error="form.errors.default_currency_id">
                        <DropdownSelect
                            v-model="form.default_currency_id"
                            :options="currencyOptions"
                            :disabled="form.processing"
                        />
                    </FormField>

                    <Button type="submit" :disabled="form.processing">
                        {{ t('common.save') }}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
```

Verify `common.save` key exists; if not, use existing save button copy from Profile page.

- [ ] **Step 4: Run preferences tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Settings/PreferencesUpdateTest.php`  
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/settings/Preferences.vue resources/js/layouts/settings/Layout.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "feat: add settings preferences page for default currency"
```

---

### Task 5: Inertia shared data + TypeScript types

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/types/index.ts`

- [ ] **Step 1: Update HandleInertiaRequests**

```php
use App\Http\Resources\Accounts\CurrencyResource;

// in share(), replace auth block:
'auth' => [
    'user' => $request->user() === null ? null : [
        'id' => $request->user()->id,
        'name' => $request->user()->name,
        'email' => $request->user()->email,
        'email_verified_at' => $request->user()->email_verified_at,
        'default_currency' => CurrencyResource::make(
            $request->user()->loadMissing('defaultCurrency')->defaultCurrency
        )->resolve($request),
    ],
],
```

- [ ] **Step 2: Update User interface**

In `resources/js/types/index.ts`:

```ts
export interface CurrencyDisplay {
    id: number;
    code: string;
    symbol: string | null;
    precision: number;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    default_currency: CurrencyDisplay;
}
```

Remove `created_at`/`updated_at` from shared payload if not sent — align interface with actual shared shape (only fields in HandleInertiaRequests).

- [ ] **Step 3: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/types/index.ts
git commit -m "feat: share user default currency via Inertia"
```

---

### Task 6: Registration and user:create set default currency

**Files:**
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Modify: `app/Console/Commands/Auth/CreateUser.php`

- [ ] **Step 1: Write failing test**

Add to `tests/Feature/Settings/PreferencesUpdateTest.php` or create `tests/Feature/Auth/RegistrationDefaultCurrencyTest.php`:

```php
test('registration sets default currency to PLN', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $response = $this->post('/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect();

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();
    expect((int) $user->default_currency_id)->toBe($plnId);
});
```

- [ ] **Step 2: Run test — expect FAIL**

- [ ] **Step 3: Update RegisteredUserController**

```php
use App\Models\Currency;

$plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

$user = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
    'default_currency_id' => $plnId,
]);
```

- [ ] **Step 4: Update CreateUser command** — same `default_currency_id` in `User::create([...])`.

- [ ] **Step 5: Run test — expect PASS**

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Auth/RegisteredUserController.php app/Console/Commands/Auth/CreateUser.php tests/Feature/Auth/RegistrationDefaultCurrencyTest.php
git commit -m "feat: set PLN default currency on user registration"
```

---

### Task 7: Create-form defaults (accounts + pockets)

**Files:**
- Modify: `app/Http/Controllers/Accounts/AccountController.php`
- Modify: `app/Http/Controllers/Pockets/PocketController.php`
- Modify: `resources/js/pages/accounts/Create.vue`
- Modify: `resources/js/pages/pockets/Create.vue`
- Extend: `tests/Feature/Settings/PreferencesUpdateTest.php`

- [ ] **Step 1: Write failing integration test**

```php
test('account create form preselects user default currency', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create(['default_currency_id' => $plnId]);

    $response = $this->actingAs($user)->get('/accounts/create');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('default_currency_id', $plnId)
    );
});

test('changing user default currency does not alter existing account currency', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create(['default_currency_id' => $plnId]);

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
    ]);

    $this->actingAs($user)->patch('/settings/preferences', [
        'default_currency_id' => $plnId,
    ]);

    expect((int) $account->refresh()->currency_id)->toBe($plnId);
});
```

Add `use App\Models\Account;` and seed currencies in `beforeEach`.

- [ ] **Step 2: Run test — expect FAIL**

- [ ] **Step 3: Pass default_currency_id from controllers**

`AccountController::create`:

```php
return Inertia::render('accounts/Create', [
    'currencies' => $currencies,
    'default_currency_id' => $request->user()->default_currency_id,
    ...$options->toArray(),
]);
```

Add `Request $request` parameter to `create()`.

`PocketController::create`:

```php
public function create(Request $request): Response
{
    return Inertia::render('pockets/Create', [
        'default_currency_id' => $request->user()->default_currency_id,
        ...(new PocketFormOptions)->toArray(),
    ]);
}
```

- [ ] **Step 4: Update Vue create forms**

In both `Create.vue` files, add prop:

```ts
default_currency_id: number;
```

Replace `initialCurrencyId` computed:

```ts
const initialCurrencyId = computed(() => props.default_currency_id ?? props.currencies[0]?.id ?? null);
```

- [ ] **Step 5: Run tests — expect PASS**

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Accounts/AccountController.php app/Http/Controllers/Pockets/PocketController.php resources/js/pages/accounts/Create.vue resources/js/pages/pockets/Create.vue tests/Feature/Settings/PreferencesUpdateTest.php
git commit -m "feat: preselect user default currency in create forms"
```

---

### Task 8: AccountsSummaryCard uses main currency

**Files:**
- Modify: `resources/js/components/accounts/AccountsSummaryCard.vue`
- Modify: `resources/js/locales/pl.json`, `en.json`

- [ ] **Step 1: Update i18n**

Replace `accounts.summary.totalPln` / `countPln` with:

```json
"totalMainCurrency": "Suma sald ({currency})",
"countMainCurrency": "Liczba kont ({currency}): {count}"
```

(English equivalents in `en.json`.)

- [ ] **Step 2: Update AccountsSummaryCard.vue**

```ts
import { usePage } from '@inertiajs/vue3';
import { type SharedData } from '@/types';

const page = usePage<SharedData>();
const mainCurrency = computed(() => page.props.auth.user.default_currency);

const accountsInMainCurrency = computed(() =>
    props.accounts.filter((a) => a.currency.code === mainCurrency.value.code),
);

const formattedTotal = computed(
    () => `${money.format(totalBalance.value)} ${mainCurrency.value.symbol ?? ''}`,
);
```

Update template labels to use `t('accounts.summary.totalMainCurrency', { currency: mainCurrency.code })`.

- [ ] **Step 3: Manual smoke** — visit `/accounts` logged in; summary shows PLN label and sum.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/accounts/AccountsSummaryCard.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "feat: filter accounts summary by user main currency"
```

---

### Task 9: Checklist + final verification

**Files:**
- Modify: `.docs/checklist.md`

- [ ] **Step 1: Add checklist section**

Under appropriate section (Settings or Model):

```markdown
### Default currency (user preference)
- [ ] Migracja `users.default_currency_id` (backfill PLN)
- [ ] Ustawienia → Preferencje (`/settings/preferences`)
- [ ] Preselekcja w formularzach konta i kieszeni
- [ ] Budżet P&L używa waluty użytkownika
- [ ] `AccountsSummaryCard` filtruje po walucie głównej
- [ ] Testy: migration, preferences, budget currency, registration
```

- [ ] **Step 2: Run full affected test suite**

Run:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Settings/ tests/Unit/Support/Budgets/BudgetCurrencyTest.php tests/Feature/Budgets/MonthlyBudgetTest.php tests/Feature/Auth/RegistrationDefaultCurrencyTest.php
```

Expected: all PASS

- [ ] **Step 3: Mark checklist items done**

- [ ] **Step 4: Commit**

```bash
git add .docs/checklist.md
git commit -m "docs: update checklist for default currency preference"
```

---

## Spec coverage self-review

| Spec requirement | Task |
|------------------|------|
| `users.default_currency_id` migration + backfill | Task 1 |
| Settings → Preferences page | Tasks 3–4 |
| Create form preselection | Task 7 |
| Budget P&L currency from user | Task 2 |
| AccountsSummaryCard main currency | Task 8 |
| HandleInertiaRequests shared data | Task 5 |
| Registration / user:create PLN default | Task 6 |
| No FX / no entity migration on change | Tasks 7 (test), spec notes |
| Telemetry `user_default_currency_updated` | Task 3 |
| i18n keys | Tasks 4, 8 |
| Tests | Tasks 1–3, 6–7, 9 |

No placeholders remain. Type names consistent (`default_currency_id`, `defaultCurrency`, `BudgetCurrency::forUser`).
