# Accounts Rachunki / Portfele Tabs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split `/konta` into Rachunki (`bank !== cash`) and Portfele (`bank === cash`) tabs, and move the PLN summary card from the accounts index onto the dashboard in place of the preview block.

**Architecture:** Same `accounts.index` route with query `kind=accounts|wallets`, registered in `pageRegistry` like pockets `filter`. Vue filters the already-loaded account list. Create prefills `bank` from `kind`. Store/update/destroy redirect with `kind` derived from the saved account’s bank. A thin invokable `DashboardController` passes the same `AccountResource` collection into `AccountsSummaryCard`.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Vue 3, Ziggy, vue-i18n, Vitest, Pest 4, Tailwind v3, Sail.

**Spec:** `.docs/superpowers/specs/2026-08-26-accounts-wallets-tabs-design.md`  
**Suggested branch:** `improvement/accounts-wallets-tabs`

## Global Constraints

- Wallet architecture Variant A: no `app/Services/`; dashboard controller lives at `app/Http/Controllers/DashboardController.php` (no new domain folder); do not add an Accounts Action for this list.
- Index query stays unfiltered — all of the user’s accounts in Inertia props. Filter only in Vue with `account.bank === 'cash'`.
- Query values stay English: `kind=accounts` | `kind=wallets`. Missing or invalid `kind` → `accounts`.
- `PageTabs` are navigation links (`<Link>`), not `role="tablist"`.
- UI: `.cursor/rules/wallet-ui-design.mdc` — do not restyle cards; move summary, don’t redesign it.
- Code, comments, commit messages in English. User-facing copy in `pl.json` / `en.json`.
- After PHP: `vendor/bin/pint --dirty --format agent` then `./vendor/bin/sail artisan test --compact` on the touched test file. After Vue/TS: `npx prettier --write` on touched files; `npm test` for the Vitest file.
- Do not change `checking` / `savings`, summary metrics, sidebar `nav.accounts`, or add new routes for the two tabs.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `resources/js/lib/pageRegistry.ts` | `pageTabGroups.accounts`; `pageNodes.accounts.tabGroup` |
| Modify | `resources/js/lib/pageRegistry.test.ts` | Integrity of the new tab group + `kind` params |
| Modify | `resources/js/lib/pageChrome.test.ts` | Accounts index now has tabs; default `kind` fallback |
| Create | `resources/js/lib/accountKind.ts` | Parse `kind`, wallet check, default bank for create |
| Create | `resources/js/lib/accountKind.test.ts` | Unit tests for that helper |
| Modify | `resources/js/locales/pl.json` | Tab labels, CTAs, empty states, create titles |
| Modify | `resources/js/locales/en.json` | Same keys in English |
| Modify | `resources/js/pages/accounts/Index.vue` | Filter, CTA, per-tab empty, drop summary |
| Modify | `resources/js/pages/accounts/Create.vue` | Prefill bank; chrome title/description overrides |
| Modify | `app/Http/Controllers/Accounts/AccountController.php` | Redirect `kind` on store/update/destroy |
| Create | `app/Http/Controllers/DashboardController.php` | Account list props for dashboard |
| Modify | `routes/web.php` | Bind dashboard route to the controller |
| Modify | `resources/js/pages/Dashboard.vue` | Drop preview; render `AccountsSummaryCard` |
| Modify | `tests/Feature/Accounts/AccountStoreTest.php` | Redirect `kind` |
| Modify | `tests/Feature/Accounts/AccountUpdateTest.php` | Redirect `kind` after bank change |
| Modify | `tests/Feature/Accounts/AccountDeleteTest.php` | Redirect `kind` |
| Modify | `tests/Feature/Telemetry/AccountTelemetryTest.php` | Destroy redirect URL includes `kind` |
| Modify | `tests/Feature/DashboardTest.php` | `accounts` prop + isolation |
| Modify | `.docs/checklist.md` | Record the UI split |

Unchanged on purpose: `AccountsSummaryCard.vue`, `AccountResource`, `AccountController::index` query, `AccountCard` / `AccountRow`.

---

### Task 1: Accounts tab group in page chrome

**Files:**
- Modify: `resources/js/lib/pageRegistry.ts`
- Modify: `resources/js/lib/pageRegistry.test.ts`
- Modify: `resources/js/lib/pageChrome.test.ts`

**Interfaces:**
- Consumes: existing `PageTabGroup` / `PageNode` types; pockets pattern `{ key, labelKey, route, params }`.
- Produces: `pageTabGroups.accounts` with `defaultKey: 'accounts'` and tabs `accounts` / `wallets`; `pageNodes.accounts.tabGroup === 'accounts'`. Label keys: `accounts.tabs.accounts`, `accounts.tabs.wallets`.

- [ ] **Step 1: Rewrite the accounts-index chrome test to expect tabs**

In `resources/js/lib/pageChrome.test.ts`, replace the `'renders a root page as a single unlinked crumb'` test (it currently asserts `chrome.tabs` is `[]` on `accounts.index`) with:

```ts
    it('renders a root page as a single unlinked crumb', () => {
        const chrome = resolvePageChrome(at('dashboard'), {}, deps);

        expect(chrome.crumbs).toEqual([{ title: 'dashboard.title', href: null }]);
        expect(chrome.title).toBe('dashboard.title');
        expect(chrome.documentTitle).toBe('dashboard.title');
        expect(chrome.tabs).toEqual([]);
    });

    it('falls back to the Rachunki tab when kind is missing', () => {
        const chrome = resolvePageChrome(at('accounts.index'), {}, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'accounts.index.title', href: '/accounts.index' },
            { title: 'accounts.tabs.accounts', href: null },
        ]);
        expect(chrome.title).toBe('accounts.index.title');
        expect(chrome.documentTitle).toBe('accounts.index.title · accounts.tabs.accounts');
        expect(chrome.tabs).toHaveLength(2);
        expect(chrome.tabs.find((tab) => tab.isActive)?.key).toBe('accounts');
        expect(chrome.tabs.find((tab) => tab.key === 'wallets')?.href).toBe('/accounts.index?kind=wallets');
    });

    it('activates Portfele when kind=wallets', () => {
        const chrome = resolvePageChrome(at('accounts.index', { kind: 'wallets' }), {}, deps);

        expect(chrome.title).toBe('accounts.index.title');
        expect(chrome.documentTitle).toBe('accounts.index.title · accounts.tabs.wallets');
        expect(chrome.tabs.find((tab) => tab.isActive)?.key).toBe('wallets');
    });
```

Keep the existing `'links every ancestor...'` test for `accounts.create` unchanged (create is not in the tab group).

- [ ] **Step 2: Add a registry integrity assertion for the accounts group**

Append to `resources/js/lib/pageRegistry.test.ts`:

```ts
    it('registers accounts tabs with kind query params', () => {
        const group = pageTabGroups.accounts;

        expect(pageNodes.accounts.tabGroup).toBe('accounts');
        expect(group.defaultKey).toBe('accounts');
        expect(group.tabs.map((tab) => tab.key)).toEqual(['accounts', 'wallets']);
        expect(group.tabs[0]).toMatchObject({
            route: 'accounts.index',
            params: { kind: 'accounts' },
            labelKey: 'accounts.tabs.accounts',
        });
        expect(group.tabs[1]).toMatchObject({
            route: 'accounts.index',
            params: { kind: 'wallets' },
            labelKey: 'accounts.tabs.wallets',
        });
    });
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `npm test -- resources/js/lib/pageChrome.test.ts resources/js/lib/pageRegistry.test.ts`

Expected: FAIL — `pageTabGroups.accounts` undefined and/or `chrome.tabs` still `[]` on `accounts.index`.

- [ ] **Step 4: Register the tab group**

In `resources/js/lib/pageRegistry.ts`, add the group next to `pockets` inside `pageTabGroups`:

```ts
    accounts: {
        defaultKey: 'accounts',
        tabs: [
            { key: 'accounts', labelKey: 'accounts.tabs.accounts', route: 'accounts.index', params: { kind: 'accounts' } },
            { key: 'wallets', labelKey: 'accounts.tabs.wallets', route: 'accounts.index', params: { kind: 'wallets' } },
        ],
    },
```

Change the accounts node to:

```ts
    accounts: { titleKey: 'accounts.index.title', matchRoutes: ['accounts.index'], tabGroup: 'accounts' },
```

Leave `accounts.create` and `accounts.edit` without `tabGroup`.

- [ ] **Step 5: Run tests to verify they pass**

Run: `npm test -- resources/js/lib/pageChrome.test.ts resources/js/lib/pageRegistry.test.ts`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
npx prettier --write resources/js/lib/pageRegistry.ts resources/js/lib/pageRegistry.test.ts resources/js/lib/pageChrome.test.ts
git add resources/js/lib/pageRegistry.ts resources/js/lib/pageRegistry.test.ts resources/js/lib/pageChrome.test.ts
git commit -m "$(cat <<'EOF'
feat(accounts): register Rachunki and Portfele page tabs

EOF
)"
```

---

### Task 2: Redirect to the matching tab after write

**Files:**
- Modify: `app/Http/Controllers/Accounts/AccountController.php`
- Modify: `tests/Feature/Accounts/AccountStoreTest.php`
- Modify: `tests/Feature/Accounts/AccountUpdateTest.php`
- Modify: `tests/Feature/Accounts/AccountDeleteTest.php`
- Modify: `tests/Feature/Telemetry/AccountTelemetryTest.php`

**Interfaces:**
- Consumes: `StoreAccount::handle(User, array): Account`; `UpdateAccountDetails::handle(Account, array): void` (in-memory `$account` is stale until `refresh()`); `Account::$bank` is `App\Enums\Bank`.
- Produces: `to_route('accounts.index', ['kind' => 'wallets'|'accounts'])` where `wallets` iff `$account->bank === Bank::Cash`.

- [ ] **Step 1: Write the failing redirect tests**

Add to `tests/Feature/Accounts/AccountStoreTest.php` (after the existing create test is fine; do not remove it):

```php
test('creating a cash account redirects to the wallets tab', function () {
    $user = User::factory()->create();
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)
        ->post(route('accounts.store', absolute: false), [
            'name' => 'Gotówka',
            'bank' => 'cash',
            'type' => 'checking',
            'currency_id' => $plnId,
            'opening_balance' => 0,
        ])
        ->assertRedirect(route('accounts.index', ['kind' => 'wallets'], absolute: false));
});

test('creating a bank account redirects to the accounts tab', function () {
    $user = User::factory()->create();
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)
        ->post(route('accounts.store', absolute: false), [
            'name' => 'mBank',
            'bank' => 'mbank',
            'type' => 'checking',
            'currency_id' => $plnId,
            'opening_balance' => 0,
        ])
        ->assertRedirect(route('accounts.index', ['kind' => 'accounts'], absolute: false));
});
```

Add to `tests/Feature/Accounts/AccountUpdateTest.php`:

```php
test('updating a cash account to a bank redirects to the accounts tab', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Gotówka',
        'bank' => 'cash',
        'type' => 'checking',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)
        ->patch(route('accounts.update', $account, absolute: false), [
            'name' => 'mBank',
            'bank' => 'mbank',
            'type' => 'checking',
            'opening_balance' => 0,
        ])
        ->assertRedirect(route('accounts.index', ['kind' => 'accounts'], absolute: false));
});
```

In `tests/Feature/Accounts/AccountDeleteTest.php`, change the existing redirect assertion to include `kind=wallets` (that fixture is `bank => 'cash'`), and add a bank-account case:

```php
    $response->assertRedirect(route('accounts.index', ['kind' => 'wallets'], absolute: false));
```

```php
test('deleting a bank account redirects to the accounts tab', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'mBank',
        'bank' => 'mbank',
        'type' => 'checking',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)
        ->delete(route('accounts.destroy', $account, absolute: false))
        ->assertRedirect(route('accounts.index', ['kind' => 'accounts'], absolute: false));

    $this->assertSoftDeleted('accounts', ['id' => $account->id]);
});
```

In `tests/Feature/Telemetry/AccountTelemetryTest.php`, the destroy fixture is `Bank::Cash`. Change the assertion inside `captureTelemetryLogs` to:

```php
->assertRedirect(route('accounts.index', ['kind' => 'wallets'], absolute: false));
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Accounts/AccountStoreTest.php tests/Feature/Accounts/AccountUpdateTest.php tests/Feature/Accounts/AccountDeleteTest.php tests/Feature/Telemetry/AccountTelemetryTest.php`

Expected: FAIL on `assertRedirect` (current redirect has no `kind` query).

- [ ] **Step 3: Implement redirects**

At the top of `app/Http/Controllers/Accounts/AccountController.php` add:

```php
use App\Enums\Bank;
```

Replace `store`, `update`, and `destroy` returns, and add the helper at the bottom of the class:

```php
    public function store(StoreAccountRequest $request, StoreAccount $storeAccount): RedirectResponse
    {
        $account = $storeAccount->handle($request->user(), $request->validated());

        return to_route('accounts.index', $this->accountsIndexQuery($account))->with('toast', [
            'type' => 'success',
            'message_key' => 'accounts.toast.created',
        ]);
    }
```

```php
    public function update(UpdateAccountRequest $request, Account $account, UpdateAccountDetails $updater): RedirectResponse
    {
        $updater->handle($account, $request->validated());
        $account->refresh();

        return to_route('accounts.index', $this->accountsIndexQuery($account))->with('toast', [
            'type' => 'success',
            'message_key' => 'accounts.toast.updated',
        ]);
    }
```

In `destroy`, keep telemetry and `$account->delete()` as they are; only change the return:

```php
        $account->delete();

        return to_route('accounts.index', $this->accountsIndexQuery($account));
```

Soft-delete keeps `bank` on the model, so reading it after `delete()` is correct.

```php
    /**
     * @return array{kind: string}
     */
    private function accountsIndexQuery(Account $account): array
    {
        return [
            'kind' => $account->bank === Bank::Cash ? 'wallets' : 'accounts',
        ];
    }
```

Do not change `index()`.

- [ ] **Step 4: Pint and re-run tests**

Run:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Accounts/AccountStoreTest.php tests/Feature/Accounts/AccountUpdateTest.php tests/Feature/Accounts/AccountDeleteTest.php tests/Feature/Telemetry/AccountTelemetryTest.php
```

Expected: Pint clean; all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Accounts/AccountController.php tests/Feature/Accounts/AccountStoreTest.php tests/Feature/Accounts/AccountUpdateTest.php tests/Feature/Accounts/AccountDeleteTest.php tests/Feature/Telemetry/AccountTelemetryTest.php
git commit -m "$(cat <<'EOF'
feat(accounts): redirect writes to the matching accounts tab

EOF
)"
```

---

### Task 3: Dashboard summary

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/DashboardTest.php`
- Modify: `resources/js/pages/Dashboard.vue`

**Interfaces:**
- Consumes: the same Eloquent query and `AccountResource` as `AccountController::index` (copy the query; do not add an Action).
- Produces: Inertia page `Dashboard` with prop `accounts` (`list` of resolved `AccountResource` arrays). `AccountsSummaryCard` still computes totals client-side.

- [ ] **Step 1: Write the failing dashboard tests**

Append to `tests/Feature/DashboardTest.php`:

```php
use App\Models\Account;
use App\Models\Currency;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard includes only the authenticated user accounts', function () {
    $this->seed(CurrencySeeder::class);
    $plnId = Currency::query()->where('code', 'PLN')->value('id');

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $own = Account::query()->create([
        'user_id' => $userA->id,
        'currency_id' => $plnId,
        'name' => 'Cash A',
        'bank' => 'cash',
        'type' => 'checking',
        'opening_balance' => 10,
        'current_balance' => 10,
    ]);

    Account::query()->create([
        'user_id' => $userB->id,
        'currency_id' => $plnId,
        'name' => 'Cash B',
        'bank' => 'cash',
        'type' => 'checking',
        'opening_balance' => 99,
        'current_balance' => 99,
    ]);

    $this->actingAs($userA)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('accounts', 1)
            ->where('accounts.0.id', $own->id)
            ->where('accounts.0.bank', 'cash')
            ->where('accounts.0.type', 'checking')
            ->where('accounts.0.currency.code', 'PLN')
        );
});
```

Keep the existing guest and authenticated tests.

- [ ] **Step 2: Run the new test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact --filter="dashboard includes only"`

Expected: FAIL — `accounts` prop missing (dashboard is still a closure with no props).

- [ ] **Step 3: Add the controller and wire the route**

Run: `./vendor/bin/sail artisan make:controller DashboardController --invokable --no-interaction`

Replace the generated class with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Accounts\AccountResource;
use App\Models\Account;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $accounts = AccountResource::collection(
            Account::query()
                ->whereBelongsTo(auth()->user())
                ->with(['currency:id,code,symbol,precision'])
                ->orderBy('name')
                ->get(['id', 'currency_id', 'name', 'current_balance', 'bank', 'type'])
        )->resolve();

        return Inertia::render('Dashboard', [
            'accounts' => $accounts,
        ]);
    }
}
```

In `routes/web.php`, add `use App\Http\Controllers\DashboardController;` and replace the dashboard closure:

```php
Route::get(route_path('dashboard'), DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
```

Leave the `/` Welcome route as it is.

- [ ] **Step 4: Replace the dashboard preview with the summary card**

`resources/js/pages/Dashboard.vue` — drop `BarChart3` and `previewItems`. Pass `accounts` into `AccountsSummaryCard`. Full file:

```vue
<script setup lang="ts">
import AccountsSummaryCard from '@/components/accounts/AccountsSummaryCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, Target, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type Currency = {
    code: string;
};

type Account = {
    type: string;
    current_balance: string;
    currency: Currency;
};

defineProps<{
    accounts: Account[];
}>();

const { t } = useI18n();

const quickActions = computed(() => [
    { key: 'accounts', href: route('accounts.index'), icon: Wallet },
    { key: 'transactions', href: route('transactions.index'), icon: ArrowLeftRight },
    { key: 'pockets', href: route('pockets.index'), icon: Target },
]);
</script>

<template>
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6">
            <section class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="action in quickActions"
                    :key="action.key"
                    class="rounded-2xl border border-transparent bg-card p-6 transition-colors hover:border-border"
                >
                    <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-foreground/[0.06] text-foreground">
                        <component :is="action.icon" class="size-5" :stroke-width="1.7" />
                    </div>
                    <h2 class="text-lg font-semibold tracking-tight">{{ t(`dashboard.quickActions.${action.key}.title`) }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ t(`dashboard.quickActions.${action.key}.description`) }}</p>
                    <Button class="mt-5 w-full" variant="outline" as-child>
                        <Link :href="action.href">{{ t(`dashboard.quickActions.${action.key}.cta`) }}</Link>
                    </Button>
                </article>
            </section>

            <AccountsSummaryCard :accounts="accounts" />
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 5: Pint, Prettier, tests**

```bash
vendor/bin/pint --dirty --format agent
npx prettier --write resources/js/pages/Dashboard.vue
./vendor/bin/sail artisan test --compact tests/Feature/DashboardTest.php
```

Expected: PASS, including the original guest redirect and authenticated 200.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/DashboardController.php routes/web.php tests/Feature/DashboardTest.php resources/js/pages/Dashboard.vue
git commit -m "$(cat <<'EOF'
feat(dashboard): show accounts summary instead of preview

EOF
)"
```

---

### Task 4: Filter `/konta` by tab

**Files:**
- Create: `resources/js/lib/accountKind.ts`
- Create: `resources/js/lib/accountKind.test.ts`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`
- Modify: `resources/js/pages/accounts/Index.vue`

**Interfaces:**
- Consumes: `usePage().url` (path + query, e.g. `/konta?kind=wallets`); `Account.bank` string from `AccountResource`.
- Produces:
  - `export type AccountKind = 'accounts' | 'wallets'`
  - `accountKindFromUrl(url: string): AccountKind`
  - `isWalletAccount(account: { bank: string }): boolean`
  - `defaultBankForKind(kind: AccountKind, banks: Array<{ value: string }>): string`

- [ ] **Step 1: Write the failing helper tests**

Create `resources/js/lib/accountKind.test.ts`:

```ts
import { accountKindFromUrl, defaultBankForKind, isWalletAccount } from '@/lib/accountKind';
import { describe, expect, it } from 'vitest';

describe('accountKindFromUrl', () => {
    it('defaults to accounts when kind is missing or invalid', () => {
        expect(accountKindFromUrl('/konta')).toBe('accounts');
        expect(accountKindFromUrl('/konta?kind=accounts')).toBe('accounts');
        expect(accountKindFromUrl('/konta?kind=nope')).toBe('accounts');
    });

    it('returns wallets only for kind=wallets', () => {
        expect(accountKindFromUrl('/konta?kind=wallets')).toBe('wallets');
    });
});

describe('isWalletAccount', () => {
    it('treats cash as a wallet and every other bank as a rachunek', () => {
        expect(isWalletAccount({ bank: 'cash' })).toBe(true);
        expect(isWalletAccount({ bank: 'mbank' })).toBe(false);
        expect(isWalletAccount({ bank: 'bnp-paribas' })).toBe(false);
    });
});

describe('defaultBankForKind', () => {
    const banks = [{ value: 'bnp-paribas' }, { value: 'mbank' }, { value: 'cash' }];

    it('prefills cash for wallets', () => {
        expect(defaultBankForKind('wallets', banks)).toBe('cash');
    });

    it('prefills the first non-cash bank for accounts', () => {
        expect(defaultBankForKind('accounts', banks)).toBe('bnp-paribas');
    });
});
```

- [ ] **Step 2: Run to verify fail**

Run: `npm test -- resources/js/lib/accountKind.test.ts`

Expected: FAIL — module not found.

- [ ] **Step 3: Implement the helper**

Create `resources/js/lib/accountKind.ts`:

```ts
export type AccountKind = 'accounts' | 'wallets';

export function accountKindFromUrl(url: string): AccountKind {
    const kind = new URLSearchParams(url.split('?')[1] ?? '').get('kind');

    return kind === 'wallets' ? 'wallets' : 'accounts';
}

export function isWalletAccount(account: { bank: string }): boolean {
    return account.bank === 'cash';
}

export function defaultBankForKind(kind: AccountKind, banks: Array<{ value: string }>): string {
    if (kind === 'wallets') {
        return 'cash';
    }

    return banks.find((bank) => bank.value !== 'cash')?.value ?? banks[0]?.value ?? '';
}
```

- [ ] **Step 4: Re-run helper tests**

Run: `npm test -- resources/js/lib/accountKind.test.ts`

Expected: PASS.

- [ ] **Step 5: Add i18n keys**

In `resources/js/locales/pl.json` under `accounts`:

- After `index.title` / `index.description`, keep `addAccount` but change its value to `"Dodaj rachunek"`, and add `"addWallet": "Dodaj portfel"`.
- Replace `index.empty` with:

```json
            "empty": {
                "accounts": {
                    "heading": "Brak rachunków",
                    "message": "Nie masz jeszcze żadnych rachunków bankowych.",
                    "cta": "Dodaj rachunek"
                },
                "wallets": {
                    "heading": "Brak portfeli",
                    "message": "Nie masz jeszcze żadnych portfeli gotówkowych.",
                    "cta": "Dodaj portfel"
                }
            }
```

- Add sibling of `index` (still inside `accounts`):

```json
        "tabs": {
            "accounts": "Rachunki",
            "wallets": "Portfele"
        },
```

Place `tabs` immediately after `toast` (before `index`) so keys stay grouped.

In `resources/js/locales/en.json`, the same shape:

- `addAccount`: `"Add bank account"`
- `addWallet`: `"Add wallet"`
- empty.accounts: heading `"No bank accounts yet"`, message `"You don't have any bank accounts yet."`, cta `"Add bank account"`
- empty.wallets: heading `"No wallets yet"`, message `"You don't have any cash wallets yet."`, cta `"Add wallet"`
- tabs.accounts: `"Bank accounts"`
- tabs.wallets: `"Wallets"`

Do not change `accounts.summary.*`.

- [ ] **Step 6: Filter the index page**

In `resources/js/pages/accounts/Index.vue`:

1. Remove the `AccountsSummaryCard` import and `<AccountsSummaryCard :accounts="accounts" />`.
2. Import `usePage` from `@inertiajs/vue3` and:

```ts
import { accountKindFromUrl, isWalletAccount } from '@/lib/accountKind';
```

3. After `defineProps`, add:

```ts
const page = usePage();
const kind = computed(() => accountKindFromUrl(page.url));

const visibleAccounts = computed(() => {
    return props.accounts.filter((account) =>
        kind.value === 'wallets' ? isWalletAccount(account) : !isWalletAccount(account),
    );
});
```

`Account` already has `bank: string`.

4. Header CTA — use `kind` for href and label:

```vue
        <template #headerActions>
            <Button as-child>
                <Link :href="route('accounts.create', { kind })">{{
                    t(kind === 'wallets' ? 'accounts.index.addWallet' : 'accounts.index.addAccount')
                }}</Link>
            </Button>
        </template>
```

5. Empty state: `v-if="visibleAccounts.length === 0"`. Copy:

```vue
                <h2 id="accounts-empty-heading" class="text-lg font-semibold">{{ t(`accounts.index.empty.${kind}.heading`) }}</h2>
                <p class="mt-2 text-sm text-muted-foreground">{{ t(`accounts.index.empty.${kind}.message`) }}</p>
                <Link :href="route('accounts.create', { kind })" :class="cn(buttonVariants({}), 'mt-4')">{{
                    t(`accounts.index.empty.${kind}.cta`)
                }}</Link>
```

6. View toggle and lists: switch `accounts.length > 0` / `v-for="account in accounts"` to `visibleAccounts`. Keep dialogs resolving from `props.accounts` (full list) so adjust/delete still find the selected id.

7. Run Prettier on the file.

There is no Pest assertion for which rows Vue shows. Existing `AccountIndexTest` must still see both cash and mBank in props.

- [ ] **Step 7: Verify helper tests and index feature test still pass**

```bash
npx prettier --write resources/js/lib/accountKind.ts resources/js/lib/accountKind.test.ts resources/js/pages/accounts/Index.vue resources/js/locales/pl.json resources/js/locales/en.json
npm test -- resources/js/lib/accountKind.test.ts
./vendor/bin/sail artisan test --compact tests/Feature/Accounts/AccountIndexTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/lib/accountKind.ts resources/js/lib/accountKind.test.ts resources/js/pages/accounts/Index.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "$(cat <<'EOF'
feat(accounts): split the index into Rachunki and Portfele tabs

EOF
)"
```

---

### Task 5: Prefill create from `kind`

**Files:**
- Modify: `resources/js/pages/accounts/Create.vue`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

**Interfaces:**
- Consumes: `accountKindFromUrl`, `defaultBankForKind` from Task 4; `props.banks`.
- Produces: `form.bank` initial value; `AppLayout` `:title` / `:description` overrides (`accounts.create.title` vs `accounts.create.titleWallet`).

- [ ] **Step 1: Add create title variants**

In both locale files, inside `accounts.create`, keep `title` / `description` as the rachunek copy and add wallet variants.

`pl.json`:

```json
            "title": "Dodaj rachunek",
            "titleWallet": "Dodaj portfel",
            "description": "Dodaj rachunek bankowy i saldo początkowe.",
            "descriptionWallet": "Dodaj portfel gotówkowy i saldo początkowe.",
```

`en.json`:

```json
            "title": "Add bank account",
            "titleWallet": "Add wallet",
            "description": "Add a bank account and opening balance.",
            "descriptionWallet": "Add a cash wallet and opening balance.",
```

`pageRegistry` still uses `accounts.create.title`; Create.vue overrides chrome when `kind === 'wallets'`.

- [ ] **Step 2: Prefill bank and override chrome**

In `resources/js/pages/accounts/Create.vue`:

```ts
import { accountKindFromUrl, defaultBankForKind } from '@/lib/accountKind';
import { Link, useForm, usePage } from '@inertiajs/vue3';
```

After props / `useI18n()`:

```ts
const page = usePage();
const kind = accountKindFromUrl(page.url);
```

Change the form’s initial `bank` from `props.banks[0]?.value ?? ''` to:

```ts
    bank: defaultBankForKind(kind, props.banks),
```

Change `<AppLayout>` to:

```vue
    <AppLayout
        :title="t(kind === 'wallets' ? 'accounts.create.titleWallet' : 'accounts.create.title')"
        :description="t(kind === 'wallets' ? 'accounts.create.descriptionWallet' : 'accounts.create.description')"
    >
```

Leave the bank dropdown enabled (user may change it). Do not change Edit.vue.

- [ ] **Step 3: Format**

```bash
npx prettier --write resources/js/pages/accounts/Create.vue resources/js/locales/pl.json resources/js/locales/en.json
npm test -- resources/js/lib/accountKind.test.ts
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/accounts/Create.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "$(cat <<'EOF'
feat(accounts): prefill create bank from the active tab

EOF
)"
```

---

### Task 6: Checklist

**Files:**
- Modify: `.docs/checklist.md` section 3 (Konta)

- [ ] **Step 1: Record the shipped UI split**

Under `### 3) Konta — API/CRUD + UI`, after the existing “Lista kont” bullet, add:

```markdown
- [x] Lista `/konta` w tabach **Rachunki** (`bank !== cash`) / **Portfele** (`bank === cash`); query `kind`; empty state per tab **[plan 2026-08-26-accounts-wallets-tabs]**
- [x] Podsumowanie PLN (w tym oszczędności) na dashboardzie zamiast na `/konta` **[plan 2026-08-26-accounts-wallets-tabs]**
```

Do not rewrite the PRD glossary (Konto stays the entity name).

- [ ] **Step 2: Commit**

```bash
git add .docs/checklist.md
git commit -m "$(cat <<'EOF'
docs(checklist): record accounts tabs and dashboard summary

EOF
)"
```

---

## Manual smoke (after Task 5)

On a user with at least one cash account and one bank account:

1. `/konta` shows Rachunki only; tab Portfele shows cash only.
2. Empty tab still offers the matching CTA while the other tab has data.
3. “Dodaj portfel” opens create with Gotówka preselected; changing bank to mBank and saving lands on Rachunki (`?kind=accounts`).
4. Dashboard: quick actions on top, old preview gone, summary totals match what `/konta` used to show.

---

## Self-review (spec coverage)

| Spec requirement | Task |
|------------------|------|
| Tabs via `?kind=` on `accounts.index` | 1 |
| Default / invalid `kind` → Rachunki | 1, 4 (`accountKindFromUrl`) |
| Portfele = `bank === cash` | 4 |
| Backend index still returns all accounts | 2 (index untouched), 4 (Pest `AccountIndexTest`) |
| Per-tab empty + CTA | 4 |
| Grid/list toggle on current tab | 4 |
| Summary removed from `/konta` | 4 |
| Same summary on dashboard, preview gone | 3 |
| Create prefill, bank still editable | 5 |
| Create chrome titles | 5 |
| Redirect from saved bank (store/update/destroy) | 2 |
| Vitest registry / chrome | 1 |
| Checklist | 6 |
| Out of scope (new routes, backend filter, metric changes) | not in any task |
