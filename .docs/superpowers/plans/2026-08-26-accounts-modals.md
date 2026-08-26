# Accounts Index Modals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep add/edit on `/konta` as dialogs, restyle the accounts list to OpenShip, and give every dialog the same chrome via `ui/dialog`.

**Architecture:** `accounts.index` ships the account list plus create/edit options (`currencies`, `banks`, `accountTypes`). Vue opens `AccountFormDialog` with no extra URL. GET `create`/`edit` redirect to the index. Shared dialog primitive is restyled once; Import keeps `sm:max-w-3xl`.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Vue 3, Ziggy, vue-i18n, Pest 4, Vitest, Tailwind v3, shadcn-vue Dialog (radix-vue), Sail.

**Spec:** `.docs/superpowers/specs/2026-08-26-accounts-modals-design.md`  
**Suggested branch:** `improvement/accounts-index-modals`

## Global Constraints

- Variant A: no `app/Services/`, no `Data/*Result`, no Action for this list. Reuse `AccountFormOptions` and the existing store/update Actions.
- Create/edit have **no** modal query string and **no** Inertia pages. Overlay state is Vue-only on `accounts.index`.
- Named routes `accounts.create` and `accounts.edit` stay (redirects + isolation). Do not change localized path strings.
- Edit modal is the form only. Delete and adjust stay on the card/row.
- `kind` values stay `accounts` | `wallets`. Prefill bank with `defaultBankForKind` from `resources/js/lib/accountKind.ts`.
- Dialog chrome is changed on `components/ui/dialog`, not a parallel wrapper. Import width override must still win.
- Code, comments, commits in English. User-facing copy in `pl.json` / `en.json`.
- After PHP: `vendor/bin/pint --dirty --format agent` then `./vendor/bin/sail artisan test --compact` on the touched test file. After Vue/TS: `npx prettier --write` on touched files; `npm test` for Vitest files.
- UI: `.cursor/rules/wallet-ui-design.mdc` and skill `openship-inspired-ui`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `app/Http/Controllers/Accounts/AccountController.php` | Index props; `create`/`edit` redirects |
| Modify | `app/Http/Controllers/Accounts/AccountBalanceController.php` | Redirect to index + `kind` |
| Modify | `tests/Feature/Accounts/AccountIndexTest.php` | `opening_balance` + form option props |
| Create | `tests/Feature/Accounts/AccountFormRouteTest.php` | GET create/edit redirects |
| Modify | `tests/Feature/Accounts/AdjustAccountBalanceTest.php` | Redirect to index + `kind` |
| Modify | `resources/js/components/ui/dialog/DialogContent.vue` | Shared OpenShip chrome |
| Modify | `resources/js/components/ui/dialog/DialogScrollContent.vue` | Same chrome |
| Create | `resources/js/components/accounts/modals/AccountFormDialog.vue` | Create/edit form overlay |
| Modify | `resources/js/pages/accounts/Index.vue` | Options, modal state, Button CTAs, list wrapper |
| Delete | `resources/js/pages/accounts/Create.vue` | Replaced by the dialog |
| Delete | `resources/js/pages/accounts/Edit.vue` | Replaced by the dialog |
| Modify | `resources/js/components/accounts/AccountCard.vue` | OpenShip surface |
| Modify | `resources/js/components/accounts/AccountRow.vue` | Row-in-list surface |
| Modify | `resources/js/components/accounts/modals/DeleteAccountDialog.vue` | Inner `rounded-xl` |
| Modify | `resources/js/components/accounts/modals/AdjustAccountBalanceDialog.vue` | Inner `rounded-xl`; ignore dismiss while processing |
| Modify | `resources/js/lib/pageRegistry.ts` | Drop `accounts.create` / `accounts.edit` |
| Modify | `resources/js/lib/pageChrome.test.ts` | Ancestor/detail examples on transactions |
| Modify | `resources/js/pages/transfers/Create.vue` | Link to `accounts.index` |
| Modify | `resources/js/locales/pl.json`, `en.json` | Remove unused create/edit keys |
| Modify | `.docs/checklist.md`, `.docs/prd.md` | Record list + modals |

Unchanged on purpose: `StoreAccount`, `UpdateAccountDetails`, `AccountFormOptions`, `AccountResource` (it already exposes `opening_balance` via `whenHas`), store/update/destroy redirect `kind`.

---

### Task 1: Index ships opening balance and form options

**Files:**
- Modify: `tests/Feature/Accounts/AccountIndexTest.php`
- Modify: `app/Http/Controllers/Accounts/AccountController.php`

**Interfaces:**
- Consumes: `AccountFormOptions::toArray()` → `banks`, `accountTypes`; `Currency` query used today in `create()`.
- Produces: Inertia `accounts/Index` props `accounts` (each row includes `opening_balance`), `currencies`, `banks`, `accountTypes`.

- [ ] **Step 1: Extend the index Inertia assertion**

In `tests/Feature/Accounts/AccountIndexTest.php`, add `use App\Enums\AccountType;` and `use App\Enums\Bank;` if you assert counts via enums; otherwise keep the existing imports.

In the `'users see only their own accounts'` test, extend `assertInertia` so it also expects form options and opening balances (decimal:2 strings):

```php
    $response->assertInertia(fn (Assert $page) => $page
        ->where('locale', app()->getLocale())
        ->has('accounts', 2)
        ->where('accounts.0.id', $cashAccount->id)
        ->where('accounts.0.opening_balance', '100.00')
        ->where('accounts.0.bank_icon_url', null)
        ->where('accounts.0.type_label_key', 'accounts.enums.accountType.checking')
        ->where('accounts.1.id', $mBankAccount->id)
        ->where('accounts.1.opening_balance', '50.00')
        ->where('accounts.1.bank_icon_url', asset('icons/banks/mbank.jpeg'))
        ->where('accounts.1.type_label_key', 'accounts.enums.accountType.checking')
        ->has('banks')
        ->has('accountTypes')
        ->has('currencies')
        ->where('banks.0.value', Bank::BnpParibas->value)
        ->where('accountTypes.0.value', AccountType::Checking->value)
        ->where('currencies.0.code', 'PLN')
    );
```

`Bank::cases()` order is BnpParibas, MBank, Cash; `AccountType::cases()` starts with Checking.

- [ ] **Step 2: Run the test — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Accounts/AccountIndexTest.php`

Expected: FAIL — `opening_balance` missing and/or `banks` / `accountTypes` / `currencies` missing on the page.

- [ ] **Step 3: Implement index props**

In `AccountController::index`, add `opening_balance` to the `get([...])` list. Inject `AccountFormOptions $options`. Pass currencies (same query as today’s `create()`) and spread `$options->toArray()`:

```php
    public function index(AccountFormOptions $options): Response
    {
        $accounts = AccountResource::collection(
            Account::query()
                ->whereBelongsTo(auth()->user())
                ->with(['currency:id,code,symbol,precision'])
                ->orderBy('name')
                ->get(['id', 'currency_id', 'name', 'current_balance', 'opening_balance', 'bank', 'type'])
        )->resolve();

        $currencies = Currency::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol', 'precision']);

        return Inertia::render('accounts/Index', [
            'accounts' => $accounts,
            'currencies' => $currencies,
            ...$options->toArray(),
        ]);
    }
```

Do **not** change `create()` / `edit()` yet.

- [ ] **Step 4: Pint + re-run tests — expect PASS**

Run:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Accounts/AccountIndexTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Accounts/AccountIndexTest.php app/Http/Controllers/Accounts/AccountController.php
git commit -m "$(cat <<'EOF'
feat(accounts): ship form options and opening balance on the index

EOF
)"
```

---

### Task 2: GET create/edit redirect to the index

**Files:**
- Create: `tests/Feature/Accounts/AccountFormRouteTest.php`
- Modify: `app/Http/Controllers/Accounts/AccountController.php`
- Unchanged: `tests/Feature/Authorization/AccountIsolationTest.php` (other user `GET edit` stays 403)

**Interfaces:**
- Consumes: `accountsIndexQuery(Account $account): array{kind: string}` already on the controller (private).
- Produces: `create(Request $request): RedirectResponse`; `edit(Account $account): RedirectResponse`. `create`/`edit` no longer `Inertia::render`.

- [ ] **Step 1: Write redirect tests**

Create `tests/Feature/Accounts/AccountFormRouteTest.php`:

```php
<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('guests are redirected to login from create', function () {
    $this->get(route('accounts.create', absolute: false))
        ->assertRedirect(route('login', absolute: false));
});

test('create redirects to the accounts index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('accounts.create', absolute: false))
        ->assertRedirect(route('accounts.index', absolute: false));
});

test('create forwards a valid kind query to the index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('accounts.create', ['kind' => 'wallets'], absolute: false))
        ->assertRedirect(route('accounts.index', ['kind' => 'wallets'], absolute: false));
});

test('create ignores an invalid kind query', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('accounts.create', ['kind' => 'nope'], absolute: false))
        ->assertRedirect(route('accounts.index', absolute: false));
});

test('owner edit redirects to the index tab for that account bank', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $cash = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $bank = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'mBank',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.edit', $cash, absolute: false))
        ->assertRedirect(route('accounts.index', ['kind' => 'wallets'], absolute: false));

    $this->actingAs($user)
        ->get(route('accounts.edit', $bank, absolute: false))
        ->assertRedirect(route('accounts.index', ['kind' => 'accounts'], absolute: false));
});
```

- [ ] **Step 2: Run the new tests — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Accounts/AccountFormRouteTest.php`

Expected: FAIL — create/edit still return 200 Inertia pages, not redirects.

- [ ] **Step 3: Replace create/edit with redirects**

Add `use Illuminate\Http\Request;`.

Replace `create` and `edit` (remove `Inertia::render` and the unused `$options` on `edit`):

```php
    public function create(Request $request): RedirectResponse
    {
        $kind = $request->query('kind');

        return to_route(
            'accounts.index',
            is_string($kind) && in_array($kind, ['accounts', 'wallets'], true)
                ? ['kind' => $kind]
                : [],
        );
    }

    public function edit(Account $account): RedirectResponse
    {
        return to_route('accounts.index', $this->accountsIndexQuery($account));
    }
```

`authorizeResource` still runs: other users hitting `edit` remain 403 (`AccountIsolationTest`).

- [ ] **Step 4: Pint + tests — expect PASS**

Run:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Accounts/AccountFormRouteTest.php tests/Feature/Authorization/AccountIsolationTest.php
```

Expected: PASS (isolation still 403 on GET edit).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Accounts/AccountFormRouteTest.php app/Http/Controllers/Accounts/AccountController.php
git commit -m "$(cat <<'EOF'
feat(accounts): redirect create and edit GET to the index

EOF
)"
```

---

### Task 3: Balance adjust redirects to the index

**Files:**
- Modify: `tests/Feature/Accounts/AdjustAccountBalanceTest.php`
- Modify: `app/Http/Controllers/Accounts/AccountBalanceController.php`

**Interfaces:**
- Consumes: same `kind` rule as `AccountController::accountsIndexQuery` (`Bank::Cash` → `wallets`, else `accounts`).
- Produces: `to_route('accounts.index', ['kind' => ...])` after a successful patch. Do not add an Action.

- [ ] **Step 1: Assert redirect on the happy-path test**

In `test('user can set current balance and adjustment is audited'...)`, after `assertSessionHasNoErrors()`, add:

```php
    $response->assertRedirect(route('accounts.index', ['kind' => 'wallets'], absolute: false));
```

(The fixture uses `Bank::Cash`.)

- [ ] **Step 2: Run — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact --filter='user can set current balance'`

Expected: FAIL — redirect is still `accounts.edit`.

- [ ] **Step 3: Redirect to the index**

In `AccountBalanceController::update`, replace `return to_route('accounts.edit', $account);` with:

```php
        return to_route('accounts.index', [
            'kind' => $account->bank === Bank::Cash ? 'wallets' : 'accounts',
        ]);
```

Add `use App\Enums\Bank;`. Duplicate the one-liner; do not extract a shared Action.

If `$account->bank` might be a string after handle(), compare with `Bank::Cash` the same way `AccountController::accountsIndexQuery` does (`$account->bank === Bank::Cash`).

- [ ] **Step 4: Pint + full adjust file — expect PASS**

Run:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Accounts/AdjustAccountBalanceTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Accounts/AdjustAccountBalanceTest.php app/Http/Controllers/Accounts/AccountBalanceController.php
git commit -m "$(cat <<'EOF'
fix(accounts): return to the index after a balance adjustment

EOF
)"
```

---

### Task 4: Shared dialog chrome

**Files:**
- Modify: `resources/js/components/ui/dialog/DialogContent.vue`
- Modify: `resources/js/components/ui/dialog/DialogScrollContent.vue`

**Interfaces:**
- Consumes: existing radix-vue `DialogContent` API; callers may pass `class` (Import: `sm:max-w-3xl`).
- Produces: overlay `bg-black/40`; panel `bg-card rounded-2xl border-border shadow-lg max-w-lg max-h-[90vh] overflow-y-auto p-6`.

- [ ] **Step 1: Restyle `DialogContent.vue`**

Replace the overlay class `bg-black/80` with `bg-black/40`.

Replace the panel `cn(...)` string with:

```
'fixed left-1/2 top-1/2 z-50 grid w-full max-w-lg max-h-[90vh] -translate-x-1/2 -translate-y-1/2 gap-4 overflow-y-auto rounded-2xl border border-border bg-card p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%]'
```

Keep the close button. Do **not** keep `sm:rounded-lg` (radius is always `rounded-2xl`). Leave `props.class` last in `cn()` so Import’s `sm:max-w-3xl` still wins.

- [ ] **Step 2: Restyle `DialogScrollContent.vue`**

Overlay: `bg-black/80` → `bg-black/40`.

Panel classes: `sm:rounded-lg` → `rounded-2xl`; `bg-background` → `bg-card`; keep `max-w-lg`, `p-6`, `shadow-lg`, `border border-border`. Add `max-h-[90vh]` if not already implied by the scroll overlay.

- [ ] **Step 3: Prettier**

Run: `npx prettier --write resources/js/components/ui/dialog/DialogContent.vue resources/js/components/ui/dialog/DialogScrollContent.vue`

- [ ] **Step 4: Manual check**

Open any existing dialog (accounts delete, Import). Overlay is lighter; corners are 16px; Import is still wider than the default.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/ui/dialog/DialogContent.vue resources/js/components/ui/dialog/DialogScrollContent.vue
git commit -m "$(cat <<'EOF'
feat(ui): restyle dialogs to the OpenShip overlay chrome

EOF
)"
```

---

### Task 5: Account form dialog on the index

**Files:**
- Create: `resources/js/components/accounts/modals/AccountFormDialog.vue`
- Modify: `resources/js/pages/accounts/Index.vue`
- Delete: `resources/js/pages/accounts/Create.vue`
- Delete: `resources/js/pages/accounts/Edit.vue`
- Modify: `resources/js/pages/transfers/Create.vue`
- Modify: `resources/js/lib/pageRegistry.ts`
- Modify: `resources/js/lib/pageChrome.test.ts`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`
- Modify: `resources/js/components/accounts/modals/AdjustAccountBalanceDialog.vue`
- Modify: `resources/js/components/accounts/modals/DeleteAccountDialog.vue`

**Interfaces:**
- Consumes: Index props `currencies`, `banks`, `accountTypes`; `defaultBankForKind(kind, banks)` from `@/lib/accountKind`; `displayAmount` / `normalizeAmount` from `@/lib/money`.
- Produces: `AccountFormDialog` with `open`, `mode: 'create' | 'edit'`, `kind`, optional `account`, `currencies`, `banks`, `accountTypes`; emits `update:open`. POST `accounts.store` / PATCH `accounts.update` with `preserveScroll: true`, `preserveState: true`; `onSuccess` emits `update:open` false.

- [ ] **Step 1: Retarget chrome tests off accounts create/edit**

In `resources/js/lib/pageChrome.test.ts`, change the ancestor test to `transactions.create` and the detail-override test to `transactions.edit`:

```ts
    it('links every ancestor and leaves only the leaf unlinked', () => {
        const chrome = resolvePageChrome(at('transactions.create'), {}, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'transactions.index.title', href: '/transactions.index' },
            { title: 'transactions.create.title', href: null },
        ]);
    });

    it('lets a detail page override the leaf, the title and the document title', () => {
        const chrome = resolvePageChrome(
            at('transactions.edit', { transaction: '12' }),
            { title: 'Lunch', description: 'transactions.edit.title' },
            deps,
        );

        expect(chrome.crumbs).toEqual([
            { title: 'transactions.index.title', href: '/transactions.index' },
            { title: 'Lunch', href: null },
        ]);
        expect(chrome.title).toBe('Lunch');
        expect(chrome.description).toBe('transactions.edit.title');
        expect(chrome.documentTitle).toBe('Lunch');
    });
```

- [ ] **Step 2: Run Vitest — expect PASS (registry nodes still exist)**

Run: `npm test -- resources/js/lib/pageChrome.test.ts`

Expected: PASS.

- [ ] **Step 3: Remove registry nodes**

Delete these two lines from `pageNodes` in `resources/js/lib/pageRegistry.ts`:

```ts
    'accounts.create': { titleKey: 'accounts.create.title', parent: 'accounts' },
    'accounts.edit': { titleKey: 'accounts.edit.title', parent: 'accounts' },
```

- [ ] **Step 4: Run Vitest again — expect PASS**

Run: `npm test -- resources/js/lib/pageChrome.test.ts resources/js/lib/pageRegistry.test.ts`

Expected: PASS (`pageRegistry.test.ts` walks remaining parents only).

- [ ] **Step 5: Create `AccountFormDialog.vue`**

Create `resources/js/components/accounts/modals/AccountFormDialog.vue`. This is the full component (fields copied from today’s `Create.vue` / `Edit.vue`):

```vue
<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { defaultBankForKind, type AccountKind } from '@/lib/accountKind';
import { displayAmount, normalizeAmount } from '@/lib/money';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type Currency = {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    precision: number;
};

type Option = {
    value: string;
    label_key: string;
    icon_url?: string | null;
    icon_name?: string | null;
};

type Account = {
    id: number;
    name: string;
    bank: string;
    type: string;
    currency_id: number;
    opening_balance: string;
    current_balance: string;
    currency: { symbol: string | null } | null;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    kind: AccountKind;
    account: Account | null;
    currencies: Currency[];
    banks: Option[];
    accountTypes: Option[];
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();

const form = useForm<{
    name: string;
    bank: string;
    type: string;
    currency_id: number | null;
    opening_balance: string;
}>({
    name: '',
    bank: '',
    type: '',
    currency_id: null,
    opening_balance: '0,00',
});

const title = computed(() => {
    if (props.mode === 'edit') {
        return t('accounts.edit.title');
    }

    return t(props.kind === 'wallets' ? 'accounts.create.titleWallet' : 'accounts.create.title');
});

const description = computed(() => {
    if (props.mode === 'edit') {
        return props.account?.name ?? '';
    }

    return t(props.kind === 'wallets' ? 'accounts.create.descriptionWallet' : 'accounts.create.description');
});

const selectedBank = computed(() => props.banks.find((b) => b.value === form.bank) ?? null);
const selectedAccountType = computed(() => props.accountTypes.find((item) => item.value === form.type) ?? null);
const currencySymbol = computed(() => props.account?.currency?.symbol ?? t('currency.defaultSymbol'));

function accountTypeIconClasses(typeValue: string | undefined): string {
    if (typeValue === 'checking') {
        return 'bg-gradient-to-br from-sky-100 to-indigo-200 text-sky-900 dark:from-sky-950/40 dark:to-indigo-950/40 dark:text-sky-200';
    }

    if (typeValue === 'savings') {
        return 'bg-gradient-to-br from-emerald-100 to-lime-200 text-emerald-900 dark:from-emerald-950/40 dark:to-lime-950/40 dark:text-emerald-200';
    }

    return 'bg-muted text-muted-foreground';
}

function resolveBankIconUrl(bankValue: string): string | null {
    return props.banks.find((b) => b.value === bankValue)?.icon_url ?? null;
}

const bankOptions = computed<DropdownOption<string>[]>(() => props.banks.map((b) => ({ value: b.value, label: t(b.label_key) })));
const accountTypeOptions = computed<DropdownOption<string>[]>(() =>
    props.accountTypes.map((item) => ({ value: item.value, label: t(item.label_key) })),
);
const currencyOptions = computed<DropdownOption<number>[]>(() =>
    props.currencies.map((c) => ({ value: c.id, label: `${c.code} — ${c.name}` })),
);

function resetForm(): void {
    if (props.mode === 'edit' && props.account) {
        form.clearErrors();
        form.name = props.account.name;
        form.bank = props.account.bank;
        form.type = props.account.type;
        form.currency_id = props.account.currency_id;
        form.opening_balance = displayAmount(props.account.opening_balance);
        return;
    }

    form.reset();
    form.clearErrors();
    form.name = '';
    form.bank = defaultBankForKind(props.kind, props.banks);
    form.type = props.accountTypes[0]?.value ?? '';
    form.currency_id = props.currencies[0]?.id ?? null;
    form.opening_balance = '0,00';
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);

function onOpenChange(value: boolean): void {
    if (form.processing && !value) {
        return;
    }

    emit('update:open', value);
}

function submit(): void {
    form.opening_balance = normalizeAmount(form.opening_balance);

    const visit = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    };

    if (props.mode === 'edit' && props.account) {
        form.patch(route('accounts.update', props.account.id), visit);
        return;
    }

    form.post(route('accounts.store'), visit);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <form class="grid gap-6" @submit.prevent="submit">
                <FormField for-id="account_name" :label="t('accounts.create.fields.name.label')" :error="form.errors.name">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="account_name"
                            v-model="form.name"
                            required
                            autofocus
                            :placeholder="t('accounts.create.fields.name.placeholder')"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <FormField for-id="account_bank" :label="t('accounts.create.fields.bank.label')" :error="form.errors.bank">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="account_bank"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.bank"
                            :options="bankOptions"
                            :placeholder="t('accounts.create.fields.bank.placeholder')"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.bank = value ?? '')"
                        >
                            <template #trigger-leading>
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded"
                                    :class="
                                        selectedBank?.value === 'cash'
                                            ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                            : 'bg-muted'
                                    "
                                    aria-hidden="true"
                                >
                                    <img
                                        v-if="selectedBank?.icon_url"
                                        :src="selectedBank.icon_url"
                                        :alt="t(selectedBank.label_key)"
                                        class="h-5 w-5 object-cover"
                                        loading="lazy"
                                    />
                                    <Icon v-else :name="selectedBank?.value === 'cash' ? 'coins' : 'landmark'" class="h-3.5 w-3.5" aria-hidden="true" />
                                </span>
                            </template>
                            <template #option-leading="{ option }">
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded"
                                    :class="
                                        option.value === 'cash'
                                            ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                            : 'bg-muted'
                                    "
                                    aria-hidden="true"
                                >
                                    <img
                                        v-if="resolveBankIconUrl(option.value)"
                                        :src="resolveBankIconUrl(option.value) ?? undefined"
                                        :alt="option.label"
                                        class="h-5 w-5 object-cover"
                                        loading="lazy"
                                    />
                                    <Icon v-else :name="option.value === 'cash' ? 'coins' : 'landmark'" class="h-3.5 w-3.5" aria-hidden="true" />
                                </span>
                            </template>
                        </DropdownSelect>
                    </template>
                </FormField>

                <FormField for-id="account_type" :label="t('accounts.create.fields.type.label')" :error="form.errors.type">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="account_type"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.type"
                            :options="accountTypeOptions"
                            :placeholder="t('accounts.create.fields.type.placeholder')"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.type = value ?? '')"
                        >
                            <template #trigger-leading>
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded"
                                    :class="accountTypeIconClasses(selectedAccountType?.value)"
                                    aria-hidden="true"
                                >
                                    <Icon :name="selectedAccountType?.icon_name ?? 'wallet'" class="h-3.5 w-3.5" aria-hidden="true" />
                                </span>
                            </template>
                            <template #option-leading="{ option }">
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded"
                                    :class="accountTypeIconClasses(option.value)"
                                    aria-hidden="true"
                                >
                                    <Icon
                                        :name="accountTypes.find((item) => item.value === option.value)?.icon_name ?? 'wallet'"
                                        class="h-3.5 w-3.5"
                                        aria-hidden="true"
                                    />
                                </span>
                            </template>
                        </DropdownSelect>
                    </template>
                </FormField>

                <FormField
                    v-if="mode === 'create'"
                    for-id="account_currency"
                    :label="t('accounts.create.fields.currency.label')"
                    :error="form.errors.currency_id"
                >
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="account_currency"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.currency_id"
                            :options="currencyOptions"
                            :placeholder="t('accounts.create.fields.currency.placeholder')"
                            :disabled="form.processing || currencies.length === 0"
                            @update:model-value="(value) => (form.currency_id = value)"
                        >
                            <template #trigger-leading>
                                <Icon name="coins" class="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                            </template>
                        </DropdownSelect>
                    </template>
                </FormField>

                <FormField
                    for-id="account_opening_balance"
                    :label="t('accounts.create.fields.openingBalance.label')"
                    :error="form.errors.opening_balance"
                    :hint="mode === 'edit' ? t('accounts.edit.openingBalanceHint') : undefined"
                >
                    <template #default="{ errorId, hasError }">
                        <div class="relative">
                            <Input
                                id="account_opening_balance"
                                v-model="form.opening_balance"
                                inputmode="decimal"
                                :placeholder="t('accounts.create.fields.openingBalance.placeholder')"
                                :class="mode === 'edit' ? 'pr-10' : undefined"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                            />
                            <span
                                v-if="mode === 'edit'"
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                            >
                                {{ currencySymbol }}
                            </span>
                        </div>
                    </template>
                </FormField>

                <DialogFooter>
                    <Button type="button" variant="secondary" :disabled="form.processing" @click="onOpenChange(false)">
                        {{ t('actions.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing" :aria-busy="form.processing || undefined">
                        {{ t('actions.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
```

Field ids use the `account_` prefix so they do not clash with other dialogs on the same page.

- [ ] **Step 6: Wire `Index.vue`**

Extend the `Account` type with `opening_balance: string` and `currency_id: number`.

Add props:

```ts
const props = defineProps<{
    accounts: Account[];
    currencies: Array<{
        id: number;
        code: string;
        name: string;
        symbol: string | null;
        precision: number;
    }>;
    banks: Array<{
        value: string;
        label_key: string;
        icon_url?: string | null;
        icon_name?: string | null;
    }>;
    accountTypes: Array<{
        value: string;
        label_key: string;
        icon_url?: string | null;
        icon_name?: string | null;
    }>;
}>();
```

Replace `goToEditAccount` / `Link` CTAs with modal state:

```ts
import AccountFormDialog from '@/components/accounts/modals/AccountFormDialog.vue';

const formDialogOpen = ref(false);
const formDialogMode = ref<'create' | 'edit'>('create');
const editingAccountId = ref<number | null>(null);
const editingAccount = computed(() => props.accounts.find((account) => account.id === editingAccountId.value) ?? null);

function openCreateDialog() {
    formDialogMode.value = 'create';
    editingAccountId.value = null;
    formDialogOpen.value = true;
}

function openEditDialog(accountId: number) {
    formDialogMode.value = 'edit';
    editingAccountId.value = accountId;
    formDialogOpen.value = true;
}
```

Header: `<Button @click="openCreateDialog">` (not `as-child` + `Link`). Empty CTA: same `Button` click. Card/row `@edit="openEditDialog"`.

At the bottom of the template, next to the existing dialogs:

```vue
        <AccountFormDialog
            v-model:open="formDialogOpen"
            :mode="formDialogMode"
            :kind="kind"
            :account="editingAccount"
            :currencies="currencies"
            :banks="banks"
            :account-types="accountTypes"
        />
```

Remove `router.visit` for edit. `kind` is already a computed `AccountKind`.

- [ ] **Step 7: Delete Create/Edit pages; retarget transfer empty CTA**

Delete `resources/js/pages/accounts/Create.vue` and `resources/js/pages/accounts/Edit.vue`.

In `resources/js/pages/transfers/Create.vue`, replace:

```vue
<Link :href="route('accounts.create')">{{ t('accounts.index.addAccount') }}</Link>
```

with:

```vue
<Link :href="route('accounts.index')">{{ t('accounts.index.addAccount') }}</Link>
```

- [ ] **Step 8: Remove unused i18n keys**

From `accounts.edit` in both `pl.json` and `en.json`, delete: `description`, `back`, `deleteAction`, `dangerZone` (object).

From `accounts.create` in both files, delete: `back`.

Keep `accounts.edit.title` and `accounts.edit.openingBalanceHint`.

- [ ] **Step 9: Block dismiss while adjust is processing; inner radius**

In `AdjustAccountBalanceDialog.vue`, change the Dialog open handler to ignore close while processing:

```vue
<Dialog :open="open" @update:open="(value) => !(form.processing && !value) && emit('update:open', value)">
```

(or an `onOpenChange` function equivalent). Change the inner confirm box from `rounded-lg` to `rounded-xl`.

In `DeleteAccountDialog.vue`, change the account summary box from `rounded-lg` to `rounded-xl`.

- [ ] **Step 10: Prettier + backend tests still green**

Run:

```bash
npx prettier --write resources/js/components/accounts/modals/AccountFormDialog.vue resources/js/pages/accounts/Index.vue resources/js/pages/transfers/Create.vue resources/js/lib/pageRegistry.ts resources/js/lib/pageChrome.test.ts resources/js/locales/pl.json resources/js/locales/en.json resources/js/components/accounts/modals/AdjustAccountBalanceDialog.vue resources/js/components/accounts/modals/DeleteAccountDialog.vue
npm test -- resources/js/lib/pageChrome.test.ts resources/js/lib/pageRegistry.test.ts
./vendor/bin/sail artisan test --compact tests/Feature/Accounts tests/Feature/Authorization/AccountIsolationTest.php
```

Expected: Vitest PASS; Pest PASS. `Create.vue` / `Edit.vue` are gone.

- [ ] **Step 11: Manual smoke**

Logged in: `/konta` → Dodaj rachunek opens modal, prefills a non-cash bank. Portfele → Dodaj portfel prefills cash. Save → toast, modal closes, correct tab. Edytuj from card and row. Empty name → 422, modal stays. Esc while saving does not close. Old `/konta/utworz` lands on the list.

- [ ] **Step 12: Commit**

```bash
git add resources/js/components/accounts/modals/AccountFormDialog.vue resources/js/pages/accounts/Index.vue resources/js/pages/transfers/Create.vue resources/js/lib/pageRegistry.ts resources/js/lib/pageChrome.test.ts resources/js/locales/pl.json resources/js/locales/en.json resources/js/components/accounts/modals/AdjustAccountBalanceDialog.vue resources/js/components/accounts/modals/DeleteAccountDialog.vue
git rm resources/js/pages/accounts/Create.vue resources/js/pages/accounts/Edit.vue
git commit -m "$(cat <<'EOF'
feat(accounts): add and edit accounts in an index modal

EOF
)"
```

---

### Task 6: OpenShip list (cards, rows, empty)

**Files:**
- Modify: `resources/js/pages/accounts/Index.vue`
- Modify: `resources/js/components/accounts/AccountCard.vue`
- Modify: `resources/js/components/accounts/AccountRow.vue`

**Interfaces:**
- Consumes: existing card/row props and emits (`edit`, `delete`, `adjustBalance`).
- Produces: card surface `rounded-2xl border-transparent bg-card`; list = one `rounded-2xl bg-card` wrapper with row hover `hover:bg-foreground/[0.03]` and `border-border/50`; empty state without a bordered box.

- [ ] **Step 1: Restyle `AccountCard.vue`**

Replace the root class `rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border` with:

```
rounded-2xl border border-transparent bg-card p-4
```

Do not change the action layout (trash, Edytuj, Ustaw saldo).

- [ ] **Step 2: Restyle `AccountRow.vue`**

Replace the root `list-row-hover rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border` with:

```
list-row-hover px-3 py-2.5 hover:bg-foreground/[0.03]
```

The row is no longer its own card.

- [ ] **Step 3: Wrap rows and restyle empty state in `Index.vue`**

Empty section: remove the bordered `rounded-xl border ... p-8` wrapper. Use a centered stack:

```vue
            <section v-if="visibleAccounts.length === 0" role="region" aria-labelledby="accounts-empty-heading" class="flex flex-col items-center py-16 text-center">
                <h2 id="accounts-empty-heading" class="text-2xl font-medium tracking-tight">
                    {{ t(`accounts.index.empty.${kind}.heading`) }}
                </h2>
                <p class="mt-2 max-w-sm text-sm text-muted-foreground">{{ t(`accounts.index.empty.${kind}.message`) }}</p>
                <Button class="mt-4" @click="openCreateDialog">{{ t(`accounts.index.empty.${kind}.cta`) }}</Button>
            </section>
```

List mode wrapper (replace the `flex flex-col gap-2` around `AccountRow`):

```vue
            <div v-else-if="visibleAccounts.length > 0" class="overflow-hidden rounded-2xl border border-transparent bg-card">
                <AccountRow
                    v-for="account in visibleAccounts"
                    :key="account.id"
                    class="border-b border-border/50 last:border-b-0"
                    ...existing props...
                />
            </div>
```

Keep grid markup; only the card component’s surface changes.

- [ ] **Step 4: Prettier**

Run: `npx prettier --write resources/js/pages/accounts/Index.vue resources/js/components/accounts/AccountCard.vue resources/js/components/accounts/AccountRow.vue`

- [ ] **Step 5: Manual smoke**

Grid: white (or lifted dark) cards on the grey page, no resting shadow. List: one panel, row hover, separators. Empty tab: no boxed empty card. Actions still work.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/accounts/Index.vue resources/js/components/accounts/AccountCard.vue resources/js/components/accounts/AccountRow.vue
git commit -m "$(cat <<'EOF'
feat(accounts): restyle the index list to OpenShip surfaces

EOF
)"
```

---

### Task 7: Docs

**Files:**
- Modify: `.docs/checklist.md`
- Modify: `.docs/prd.md`

**Interfaces:**
- Consumes: shipped behavior from tasks 1–6.
- Produces: checklist §3 notes add/edit from the list (modal); PRD screens line says Konta = list + modals.

- [ ] **Step 1: Checklist**

Under `### 3) Konta — API/CRUD + UI`, after the existing add/edit bullets, add a checked item:

```markdown
- [x] Dodanie i edycja konta z listy `/konta` (modal; GET create/edit → redirect na index) **[plan 2026-08-26-accounts-modals]**
```

- [ ] **Step 2: PRD screens**

In `.docs/prd.md` §7, change `Konta (lista, dodaj, edytuj)` to `Konta (lista; dodaj/edytuj w modalu)`.

- [ ] **Step 3: Commit**

```bash
git add .docs/checklist.md .docs/prd.md
git commit -m "$(cat <<'EOF'
docs: record account add/edit as index modals

EOF
)"
```

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| Index props: `opening_balance`, currencies, banks, accountTypes | 1 |
| GET create/edit redirect; other user 403 | 2 |
| Adjust redirect to index + `kind` | 3 |
| Shared `ui/dialog` chrome; Import width | 4 |
| `AccountFormDialog`; no Create/Edit pages; preserveState 422; processing blocks dismiss | 5 |
| Transfer empty CTA → index | 5 |
| pageRegistry drop create/edit; unused i18n | 5 |
| OpenShip cards, one list container, empty state | 6 |
| Checklist + PRD | 7 |
| No modal URL / no sheet / no other-domain Add/Edit | Out of scope (not tasked) |
| Card action layout unchanged | 6 (tokens only) |
