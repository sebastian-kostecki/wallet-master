# Transactions Index Modals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep P&L add/edit on `/transakcje` as a dialog, with sidebar and `GET create` opening create, while transfer create stays a full page.

**Architecture:** `transactions.index` already ships accounts, categories, and list rows with the edit fields. This slice adds `pockets`, a `TransactionFormDialog` overlay, a one-shot `?new=1` from `GET create`, and a client signal so the sidebar does not round-trip when the user is already on the index. `GET edit` redirects to the index without opening a modal (the row may not be on the current page).

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Vue 3, Ziggy, vue-i18n, Pest 4, Vitest, Tailwind v3, shadcn-vue Dialog (radix-vue), Sail.

**Spec:** `.docs/superpowers/specs/2026-08-26-transactions-modals-design.md`  
**Suggested branch:** `improvement/transactions-index-modals`

## Global Constraints

- Variant A: no `app/Services/`, no `Data/*Result`. Reuse `ListPockets`, `PocketResource`, existing store/update Actions.
- Overlay state is Vue-only on `transactions.index`, except one-shot `?new=1` which Index strips and must never enter `TransactionsIndexQuery::ALLOWED_KEYS`.
- Named routes `transactions.create` and `transactions.edit` stay (redirects + isolation). Do not change localized path strings.
- Transfer create, Import, and row delete stay. No list/summary/filter restyle.
- Edit modal has no danger zone. Unlink + collapsible statement live in the modal. Delete stays on the row.
- Code, comments, commits in English. User-facing copy in `pl.json` / `en.json`.
- After PHP: `vendor/bin/pint --dirty --format agent` then `./vendor/bin/sail artisan test --compact` on the touched test file. After Vue/TS: `npx prettier --write` on touched files; `npm test` for Vitest files.
- UI: `.cursor/rules/wallet-ui-design.mdc` and skill `openship-inspired-ui`. Dialog chrome is already shipped — do not restyle `ui/dialog`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `app/Http/Controllers/Transactions/TransactionController.php` | Index `pockets`; `create`/`edit` redirects |
| Delete | `app/Http/Resources/Transactions/TransactionEditResource.php` | Only the edit page used it |
| Modify | `tests/Feature/Transactions/TransactionPocketTest.php` | Pockets on index |
| Modify | `tests/Feature/Transactions/TransactionCreatePageTest.php` | GET create → redirect + `new=1` |
| Modify | `tests/Feature/Transactions/TransactionEditTest.php` | GET edit → redirect index |
| Modify | `tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php` | Index `transfer_id`; unlink `from` index |
| Create | `resources/js/composables/useTransactionFormDialog.ts` | Module-level create-open request |
| Create | `resources/js/components/transactions/modals/TransactionFormDialog.vue` | Create/edit overlay |
| Modify | `resources/js/pages/transactions/Index.vue` | Props, modal state, CTAs, `?new=1` |
| Modify | `resources/js/components/AppSidebar.vue` | Button vs Link |
| Modify | `resources/js/components/transfers/UnlinkTransferDialog.vue` | `preserveState` so the edit modal survives |
| Delete | `resources/js/pages/transactions/Create.vue` | Replaced by the dialog |
| Delete | `resources/js/pages/transactions/Edit.vue` | Replaced by the dialog |
| Modify | `resources/js/lib/pageRegistry.ts` | Drop `transactions.create` / `transactions.edit` |
| Modify | `resources/js/lib/pageChrome.test.ts` | Ancestor → `transfers.create`; detail → `pockets.edit` |
| Modify | `resources/js/locales/pl.json`, `en.json` | Remove unused create/edit keys |
| Modify | `.docs/checklist.md`, `.docs/prd.md` | Record list + P&L modals |

Unchanged on purpose: `StoreTransaction`, `UpdateTransaction`, `TransactionsIndexQuery::ALLOWED_KEYS`, `TransactionResource`, store/update/destroy redirects, `transfers.create` page, Import dialog, `DeleteTransactionDialog`.

---

### Task 1: Index ships pockets

**Files:**
- Modify: `tests/Feature/Transactions/TransactionPocketTest.php`
- Modify: `app/Http/Controllers/Transactions/TransactionController.php`

**Interfaces:**
- Consumes: `ListPockets::handle(User, ?string $filter = 'active')`, `getPockets()`, `PocketResource`.
- Produces: Inertia `transactions/Index` prop `pockets` (active pockets for the current user).

- [ ] **Step 1: Point the create-page pockets test at the index**

In `tests/Feature/Transactions/TransactionPocketTest.php`, replace `transaction create page includes pockets list` with:

```php
test('transaction index includes pockets list', function () {
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    $response = $this->actingAs($user)->get(route('transactions.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->has('pockets', 1)
        ->where('pockets.0.id', $pocket->id)
        ->where('pockets.0.name', 'Wakacje')
    );
});
```

Leave `transaction edit page includes pockets list` unchanged until Task 2.

- [ ] **Step 2: Run the test — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionPocketTest.php --filter="transaction index includes pockets list"`

Expected: FAIL — `pockets` missing on `transactions/Index`.

- [ ] **Step 3: Add `pockets` to `index()`**

In `TransactionController::index`, inject `ListPockets $listPockets`, call `$listPockets->handle($request->user())`, and add the prop next to `categories`. `PocketResource` is already imported.

```php
    public function index(
        TransactionIndexRequest $request,
        ListTransactions $listTransactions,
        ListCategories $listCategories,
        ListPockets $listPockets,
    ): Response {
        $listTransactions->handle($request);
        $listCategories->handle($request->user());
        $listPockets->handle($request->user());

        TransactionsIndexQuery::remember($request);

        return Inertia::render('transactions/Index', [
            'filters' => $listTransactions->getFilters(),
            'accounts' => AccountResource::collection($listTransactions->getAccounts())->resolve(),
            'categories' => CategoryResource::collection($listCategories->getCategories())->resolve(),
            'pockets' => PocketResource::collection($listPockets->getPockets())->resolve(),
            'transactions' => TransactionResource::collection($listTransactions->getTransactionPaginator()),
            'summary' => $listTransactions->getSummary(),
            'unresolved_import_failed_rows' => ImportFailedRowResource::collection(
                $listTransactions->getUnresolvedImportFailedRows(),
            )->resolve(),
            'pending_transfer_candidates' => TransferCandidatePairResource::collection(
                $listTransactions->getPendingTransferCandidates(),
            )->resolve(),
        ]);
    }
```

Do **not** change `create()` / `edit()` yet.

- [ ] **Step 4: Pint + re-run — expect PASS**

Run:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionPocketTest.php
```

Expected: PASS (including the still-green edit-page pockets test).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Transactions/TransactionPocketTest.php app/Http/Controllers/Transactions/TransactionController.php
git commit -m "$(cat <<'EOF'
feat(transactions): ship pockets on the index

EOF
)"
```

---

### Task 2: GET create/edit redirect to the index

**Files:**
- Modify: `tests/Feature/Transactions/TransactionCreatePageTest.php`
- Modify: `tests/Feature/Transactions/TransactionEditTest.php`
- Modify: `tests/Feature/Transactions/TransactionPocketTest.php`
- Modify: `tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php`
- Modify: `app/Http/Controllers/Transactions/TransactionController.php`

**Interfaces:**
- Consumes: `TransactionsIndexQuery::params()`, `TransactionsIndexQuery::redirect(Request)`.
- Produces: `create(): RedirectResponse` to index + remembered filters + `new=1`; `edit(): RedirectResponse` to index + remembered filters, no `new`. Policy 403 on edit is unchanged (`authorizeResource` runs first).

- [ ] **Step 1: Rewrite create/edit/unlink page tests**

Replace the body of `tests/Feature/Transactions/TransactionCreatePageTest.php` (keep `beforeEach` seeder + account setup) so the existing test becomes a redirect, and add a remembered-filters case:

```php
test('create page redirects to the index with new=1', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this
        ->actingAs($user)
        ->get(route('transactions.create', absolute: false))
        ->assertRedirect(route('transactions.index', ['new' => 1], absolute: false));
});

test('create page redirects to the index with remembered filters and new=1', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->get(route('transactions.index', [
        'account_id' => $account->id,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
    ], absolute: false))->assertOk();

    $this
        ->actingAs($user)
        ->get(route('transactions.create', absolute: false))
        ->assertRedirect(route('transactions.index', [
            'account_id' => $account->id,
            'from' => '01-04-2026',
            'to' => '30-04-2026',
            'new' => 1,
        ], absolute: false));
});
```

Replace `tests/Feature/Transactions/TransactionEditTest.php` (keep factory setup) with:

```php
test('edit page redirects to the index', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Account',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-20',
        'booked_at' => '2026-04-20',
        'amount' => -10,
        'type' => 'expense',
        'description' => 'Coffee',
        'subject' => null,
        'normalized_description' => 'coffee',
        'dedupe_hash' => md5('2026-04-20|-10.00|coffee', true),
    ]);

    $this
        ->actingAs($user)
        ->get(route('transactions.edit', $transaction, absolute: false))
        ->assertRedirect(route('transactions.index', absolute: false));
});
```

Do not assert `new` on that redirect.

In `TransactionPocketTest.php`, delete `transaction edit page includes pockets list` (index already covers pockets). `Assert` import can stay if still used.

In `TransactionEditTransferUnlinkTest.php`:

1. Replace `edit page exposes transfer_id for linked leg` so it GETs the **index** (date filter covering `01-04-2026`) and asserts the row:

```php
test('index exposes transfer_id for linked leg', function () {
    // same setup as today through $withdrawal

    $this->actingAs($user)
        ->get(route('transactions.index', [
            'from' => '01-04-2026',
            'to' => '01-04-2026',
        ], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index', false)
            ->where('transactions.data.0.transfer_id', $transferId)
        );
});
```

2. Change unlink `from` + expected redirect from `transactions.edit` to `transactions.index`:

```php
    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->post(route('transfers.unlink', $transferId))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('toast.message_key', 'transfers.toast.unlinked');
```

Keep the `expect($withdrawal->transfer_id)->toBeNull()` assertions.

Do **not** change `TransactionAuthorizationTest` (guest → login, other user GET edit → 403).

- [ ] **Step 2: Run tests — expect FAIL**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionCreatePageTest.php tests/Feature/Transactions/TransactionEditTest.php tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php
```

Expected: FAIL — create/edit still `Inertia::render` (assertRedirect fails). Unlink `from` index may already pass (`back()`); the edit Inertia test fails.

- [ ] **Step 3: Redirect `create()` and `edit()`**

Replace `create()` and `edit()` in `TransactionController`. Drop unused `ListCategories` / `ListPockets` / `Account` / `TransactionEditResource` usage from those methods. `index()` still uses `ListPockets`.

```php
    public function create(): RedirectResponse
    {
        return to_route('transactions.index', [
            ...TransactionsIndexQuery::params(),
            'new' => 1,
        ]);
    }

    public function edit(Request $request, Transaction $transaction): RedirectResponse
    {
        return TransactionsIndexQuery::redirect($request);
    }
```

Keep the `$transaction` parameter so `authorizeResource` still binds and authorizes **before** the redirect. Do not add `new` to `TransactionsIndexQuery::ALLOWED_KEYS`.

Remove the `TransactionEditResource` import if unused. Leave the class file until Task 5.

- [ ] **Step 4: Pint + re-run — expect PASS**

Run:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionCreatePageTest.php tests/Feature/Transactions/TransactionEditTest.php tests/Feature/Transactions/TransactionPocketTest.php tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php tests/Feature/Transactions/TransactionAuthorizationTest.php
```

Expected: PASS. Guest GET create still redirects to login (auth middleware). Other-user GET edit still 403.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Transactions/TransactionCreatePageTest.php tests/Feature/Transactions/TransactionEditTest.php tests/Feature/Transactions/TransactionPocketTest.php tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php app/Http/Controllers/Transactions/TransactionController.php
git commit -m "$(cat <<'EOF'
feat(transactions): redirect create and edit GET to the index

EOF
)"
```

---

### Task 3: Form dialog on the index

**Files:**
- Create: `resources/js/composables/useTransactionFormDialog.ts`
- Create: `resources/js/components/transactions/modals/TransactionFormDialog.vue`
- Modify: `resources/js/pages/transactions/Index.vue`

**Interfaces:**
- Consumes: Index props `accounts`, `categories`, `pockets`, `filters.account_id`, `transactions.data[]` (already has edit fields). `requestOpenCreateTransaction()` from Task 3 composable. Existing `transactions.store` / `transactions.update` + `useTransactionsIndexSearch`.
- Produces: `TransactionFormDialog` with `open`, `mode: 'create' | 'edit'`, `transaction`, `accounts`, `categories`, `pockets`, `accountIdFilter`. Create-open via header/empty CTA, `?new=1`, and `createRequested`.

- [ ] **Step 1: Add the create-open composable**

Create `resources/js/composables/useTransactionFormDialog.ts`:

```ts
import { ref } from 'vue';

const createRequested = ref(false);

export function requestOpenCreateTransaction(): void {
    createRequested.value = true;
}

export function useTransactionFormDialog() {
    return { createRequested };
}
```

- [ ] **Step 2: Add `TransactionFormDialog.vue`**

Create `resources/js/components/transactions/modals/TransactionFormDialog.vue`. Reuse field UI from today’s Create/Edit (DropdownSelect, CategoryBadge, DatePickerInput, amount helpers). Do **not** use `AdvancedSectionCard`. Nested `UnlinkTransferDialog` for linked rows.

```vue
<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import UnlinkTransferDialog from '@/components/transfers/UnlinkTransferDialog.vue';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import { categoriesByIdMap, filterCategoriesByType, firstCategoryId, type CategoryOption } from '@/lib/categories';
import { displayAmount, normalizeAmount } from '@/lib/money';
import { track } from '@/lib/telemetry';
import { useForm } from '@inertiajs/vue3';
import { ChevronDown, Coins } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type Account = {
    id: number;
    name: string;
    currency_id?: number;
    bank?: string;
    bank_icon_url: string | null;
};

type Transaction = {
    id: number;
    account_id: number;
    date: string;
    booked_at: string;
    amount: string | number;
    type: string;
    description: string;
    subject: string | null;
    raw_statement_description: string | null;
    transfer_id: string | null;
    category_id: number | null;
    pocket_id: number | null;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    transaction: Transaction | null;
    accounts: Account[];
    categories: CategoryOption[];
    pockets: { id: number; name: string }[];
    accountIdFilter: number | null;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();
const { transactionsIndexSearch } = useTransactionsIndexSearch();

const transactionKind = ref<'income' | 'expense'>('expense');
const statementOpen = ref(false);
const unlinkDialogOpen = ref(false);
const unlinkProcessing = ref(false);

const form = useForm<{
    account_id: number | null;
    category_id: number | null;
    pocket_id: number | null;
    date: string;
    booked_at: string;
    amount: string;
    description: string;
    subject: string;
}>({
    account_id: null,
    category_id: null,
    pocket_id: null,
    date: '',
    booked_at: '',
    amount: '0,00',
    description: '',
    subject: '',
});

const accountOptions = computed<DropdownOption<number>[]>(() => props.accounts.map((a) => ({ value: a.id, label: a.name })));
const accountsById = computed(() => new Map(props.accounts.map((a) => [a.id, a])));
const categoriesById = computed(() => categoriesByIdMap(props.categories));

const editType = computed<'income' | 'expense'>(() =>
    props.transaction?.type === 'income' ? 'income' : 'expense',
);

const activeType = computed<'income' | 'expense'>(() => (props.mode === 'edit' ? editType.value : transactionKind.value));

const categoryOptions = computed<DropdownOption<number>[]>(() =>
    filterCategoriesByType(props.categories, activeType.value).map((c) => ({
        value: c.id,
        label: c.name,
    })),
);

const pocketOptions = computed<DropdownOption<number | null>[]>(() => [
    { value: null, label: t('transactions.fields.pocketNone') },
    ...props.pockets.map((p) => ({
        value: p.id,
        label: p.name,
    })),
]);

const selectedAccount = computed(() => {
    if (form.account_id === null) {
        return null;
    }

    return accountsById.value.get(form.account_id) ?? null;
});

const isLinkedTransfer = computed(() => Boolean(props.mode === 'edit' && props.transaction?.transfer_id));
const hasStatement = computed(() => (props.transaction?.raw_statement_description ?? '').trim() !== '');

const title = computed(() => t(props.mode === 'edit' ? 'transactions.edit.title' : 'transactions.create.title'));

const description = computed(() => {
    if (props.mode !== 'edit' || props.transaction === null) {
        return t('transactions.create.description');
    }

    const text = props.transaction.description.trim();

    return text !== '' ? text : (props.transaction.subject ?? '').trim();
});

function todayDdMmYyyy(): string {
    const now = new Date();
    const d = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));

    return `${String(d.getUTCDate()).padStart(2, '0')}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${d.getUTCFullYear()}`;
}

function isoToDdMmYyyy(input: string): string {
    const trimmed = input.trim();
    const datePart = /^\d{4}-\d{2}-\d{2}/.test(trimmed) ? trimmed.slice(0, 10) : trimmed;
    const parts = datePart.split('-');

    if (parts.length !== 3) {
        return input;
    }

    const [yyyy, mm, dd] = parts;

    return `${dd}-${mm}-${yyyy}`;
}

function applyAmountSign(amount: string): string {
    const trimmed = amount.trim();

    if (trimmed === '') {
        return amount;
    }

    if (transactionKind.value === 'expense') {
        return trimmed.startsWith('-') ? amount : `-${amount}`;
    }

    return trimmed.startsWith('-') ? amount.replace(/^\s*-+/, '') : amount;
}

function defaultAccountId(): number | null {
    if (props.accountIdFilter !== null && props.accounts.some((a) => a.id === props.accountIdFilter)) {
        return props.accountIdFilter;
    }

    return props.accounts[0]?.id ?? null;
}

function resetForm(): void {
    form.clearErrors();
    statementOpen.value = false;

    if (props.mode === 'edit' && props.transaction) {
        transactionKind.value = editType.value;
        form.account_id = props.transaction.account_id;
        form.category_id = props.transaction.category_id;
        form.pocket_id = props.transaction.pocket_id;
        form.date = isoToDdMmYyyy(props.transaction.date);
        form.booked_at =
            props.transaction.booked_at === props.transaction.date ? '' : isoToDdMmYyyy(props.transaction.booked_at);
        form.amount = displayAmount(String(props.transaction.amount));
        form.description = props.transaction.description;
        form.subject = props.transaction.subject ?? '';

        return;
    }

    form.reset();
    transactionKind.value = 'expense';
    form.account_id = defaultAccountId();
    form.category_id = firstCategoryId(filterCategoriesByType(props.categories, 'expense'));
    form.pocket_id = null;
    form.date = todayDdMmYyyy();
    form.booked_at = '';
    form.amount = '0,00';
    form.description = '';
    form.subject = '';
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();

            if (props.mode === 'create') {
                track('transaction_create_opened');
            }
        }
    },
);

watch(transactionKind, (kind) => {
    if (props.mode === 'edit') {
        return;
    }

    const filtered = filterCategoriesByType(props.categories, kind);

    if (!filtered.some((c) => c.id === form.category_id)) {
        form.category_id = firstCategoryId(filtered);
    }
});

function onOpenChange(value: boolean): void {
    if (form.processing && !value) {
        return;
    }

    emit('update:open', value);
}

function fieldDescribedBy(errorId: string | undefined, hasError: boolean, hintId: string | undefined, includeHint: boolean): string | undefined {
    const ids: string[] = [];

    if (hasError && errorId) {
        ids.push(errorId);
    }

    if (includeHint && hintId) {
        ids.push(hintId);
    }

    return ids.length > 0 ? ids.join(' ') : undefined;
}

function submit(): void {
    form.amount = normalizeAmount(form.amount);

    if (props.mode === 'create') {
        form.amount = applyAmountSign(form.amount);
    }

    if ((form.booked_at ?? '').trim() === '') {
        form.booked_at = form.date;
    }

    const visit = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    };

    if (props.mode === 'edit' && props.transaction) {
        form.put(route('transactions.update', props.transaction.id) + transactionsIndexSearch.value, visit);

        return;
    }

    form.post(route('transactions.store') + transactionsIndexSearch.value, visit);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <div
                v-if="isLinkedTransfer"
                class="rounded-xl border border-primary/30 bg-primary/5 p-4 dark:border-primary/40 dark:bg-primary/10"
            >
                <p class="text-sm font-semibold text-foreground">{{ t('transactions.edit.transfer.bannerTitle') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('transactions.edit.transfer.bannerDescription') }}</p>
                <Button class="mt-3" type="button" variant="secondary" :disabled="unlinkProcessing" @click="unlinkDialogOpen = true">
                    {{ t('transactions.edit.transfer.unlinkAction') }}
                </Button>
            </div>

            <form class="grid gap-6" @submit.prevent="submit" :aria-busy="form.processing ? 'true' : 'false'">
                <fieldset v-if="mode === 'create'" class="grid gap-2 border-0 p-0" :disabled="form.processing">
                    <legend class="text-sm font-medium">{{ t('transactions.form.type') }}</legend>
                    <div class="grid grid-cols-2 gap-1 rounded-xl border border-input bg-muted/30 p-1">
                        <Button
                            type="button"
                            :variant="transactionKind === 'income' ? 'secondary' : 'ghost'"
                            class="h-9 justify-center rounded-lg"
                            :aria-pressed="transactionKind === 'income'"
                            :disabled="form.processing"
                            @click="transactionKind = 'income'"
                        >
                            {{ t('transactions.types.income') }}
                        </Button>
                        <Button
                            type="button"
                            :variant="transactionKind === 'expense' ? 'secondary' : 'ghost'"
                            class="h-9 justify-center rounded-lg"
                            :aria-pressed="transactionKind === 'expense'"
                            :disabled="form.processing"
                            @click="transactionKind = 'expense'"
                        >
                            {{ t('transactions.types.expense') }}
                        </Button>
                    </div>
                </fieldset>

                <FormField
                    for-id="account_id"
                    :label="t('transactions.form.account')"
                    :error="form.errors.account_id"
                    :hint="isLinkedTransfer ? t('transactions.edit.transfer.accountHint') : null"
                >
                    <template #default="{ errorId, hintId, hasError }">
                        <DropdownSelect
                            id="account_id"
                            :aria-invalid="hasError"
                            :aria-disabled="isLinkedTransfer"
                            :aria-describedby="fieldDescribedBy(errorId, hasError, hintId, isLinkedTransfer)"
                            :model-value="form.account_id"
                            :options="accountOptions"
                            :placeholder="t('transactions.form.account')"
                            :disabled="form.processing || accounts.length === 0 || isLinkedTransfer"
                            @update:model-value="(value) => (form.account_id = value)"
                        >
                            <template #trigger-leading>
                                <span
                                    v-if="selectedAccount"
                                    class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                    :class="
                                        selectedAccount.bank === 'cash'
                                            ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                            : 'bg-muted'
                                    "
                                    aria-hidden="true"
                                >
                                    <img
                                        v-if="selectedAccount.bank_icon_url"
                                        :src="selectedAccount.bank_icon_url"
                                        :alt="selectedAccount.name"
                                        class="h-5 w-5 object-cover"
                                    />
                                    <Coins v-else-if="selectedAccount.bank === 'cash'" class="h-3.5 w-3.5" />
                                    <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                        {{ selectedAccount.name.charAt(0).toUpperCase() }}
                                    </span>
                                </span>
                            </template>
                            <template #option-leading="{ option }">
                                <span
                                    class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                    :class="
                                        accountsById.get(option.value)?.bank === 'cash'
                                            ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                            : 'bg-muted'
                                    "
                                    aria-hidden="true"
                                >
                                    <img
                                        v-if="accountsById.get(option.value)?.bank_icon_url"
                                        :src="accountsById.get(option.value)?.bank_icon_url ?? ''"
                                        :alt="accountsById.get(option.value)?.name ?? ''"
                                        class="h-5 w-5 object-cover"
                                    />
                                    <Coins v-else-if="accountsById.get(option.value)?.bank === 'cash'" class="h-3.5 w-3.5" />
                                    <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                        {{ (accountsById.get(option.value)?.name ?? '?').charAt(0).toUpperCase() }}
                                    </span>
                                </span>
                            </template>
                        </DropdownSelect>
                    </template>
                </FormField>

                <FormField for-id="category_id" :label="t('transactions.fields.category')" :error="form.errors.category_id">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="category_id"
                            :aria-invalid="hasError"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.category_id"
                            :options="categoryOptions"
                            :placeholder="t('transactions.fields.category')"
                            :disabled="form.processing || categoryOptions.length === 0"
                            @update:model-value="(value) => (form.category_id = value)"
                        >
                            <template #trigger-leading>
                                <CategoryBadge
                                    v-if="form.category_id !== null && categoriesById.get(form.category_id)"
                                    :icon="categoriesById.get(form.category_id)!.icon"
                                    :color="categoriesById.get(form.category_id)!.color"
                                    size="sm"
                                    :show-name="false"
                                />
                            </template>
                            <template #option-leading="{ option }">
                                <CategoryBadge
                                    v-if="categoriesById.get(option.value)"
                                    :icon="categoriesById.get(option.value)!.icon"
                                    :color="categoriesById.get(option.value)!.color"
                                    size="sm"
                                    :show-name="false"
                                />
                            </template>
                        </DropdownSelect>
                    </template>
                </FormField>

                <FormField for-id="pocket_id" :label="t('transactions.fields.pocket')" :error="form.errors.pocket_id">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="pocket_id"
                            :aria-invalid="hasError"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.pocket_id"
                            :options="pocketOptions"
                            :placeholder="t('transactions.fields.pocket')"
                            :disabled="form.processing || pocketOptions.length <= 1"
                            @update:model-value="(value) => (form.pocket_id = value)"
                        />
                    </template>
                </FormField>

                <FormField for-id="date" :label="t('transactions.form.date')" :error="form.errors.date">
                    <template #default="{ errorId, hasError }">
                        <DatePickerInput
                            id="date"
                            :aria-invalid="hasError"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.date"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.date = value)"
                        />
                    </template>
                </FormField>

                <FormField
                    for-id="amount"
                    :label="t('transactions.form.amount')"
                    :error="form.errors.amount"
                    :hint="isLinkedTransfer ? t('transactions.edit.transfer.amountHint') : null"
                >
                    <template #default="{ errorId, hintId, hasError }">
                        <Input
                            id="amount"
                            v-model="form.amount"
                            inputmode="decimal"
                            :disabled="form.processing || isLinkedTransfer"
                            :aria-disabled="isLinkedTransfer ? true : undefined"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="fieldDescribedBy(errorId, hasError, hintId, isLinkedTransfer)"
                        />
                    </template>
                </FormField>

                <FormField for-id="subject" :label="t('transactions.form.subject')" :error="form.errors.subject">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="subject"
                            v-model="form.subject"
                            :disabled="form.processing"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <FormField for-id="description" :label="t('transactions.form.description')" :error="form.errors.description">
                    <template #default="{ errorId, hasError }">
                        <textarea
                            id="description"
                            v-model="form.description"
                            :disabled="form.processing"
                            rows="4"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            class="flex min-h-[96px] w-full rounded-xl border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        />
                    </template>
                </FormField>

                <Collapsible class="w-full">
                    <CollapsibleTrigger
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-lg text-left text-sm font-medium outline-none ring-offset-background hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        {{ t('advancedSection.toggle') }}
                        <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <p class="mt-2 text-sm text-muted-foreground">{{ t('transactions.form.advancedDatesHint') }}</p>
                        <FormField class="mt-4" for-id="booked_at" :label="t('transactions.form.booked_at')" :error="form.errors.booked_at">
                            <template #default="{ errorId, hasError }">
                                <DatePickerInput
                                    id="booked_at"
                                    :aria-invalid="hasError"
                                    :aria-describedby="hasError ? errorId : undefined"
                                    :model-value="form.booked_at"
                                    :disabled="form.processing"
                                    @update:model-value="(value) => (form.booked_at = value)"
                                />
                            </template>
                        </FormField>
                    </CollapsibleContent>
                </Collapsible>

                <Collapsible v-if="mode === 'edit' && hasStatement" v-model:open="statementOpen" class="w-full">
                    <CollapsibleTrigger
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-lg text-left text-sm font-medium outline-none ring-offset-background hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        {{ t('transactions.edit.statement.title') }}
                        <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <p class="mt-2 text-sm text-muted-foreground">{{ t('transactions.edit.statement.description') }}</p>
                        <p class="mt-3 whitespace-pre-wrap break-words text-sm text-foreground">
                            {{ transaction?.raw_statement_description }}
                        </p>
                    </CollapsibleContent>
                </Collapsible>

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

    <UnlinkTransferDialog
        v-model:open="unlinkDialogOpen"
        :transfer-id="transaction?.transfer_id ?? null"
        :disabled="unlinkProcessing"
        @processing="(value: boolean) => (unlinkProcessing = value)"
    />
</template>
```

- [ ] **Step 3: Wire `Index.vue`**

Add `pockets` to props:

```ts
    pockets: { id: number; name: string }[];
```

Default to `[]` is not required if the controller always sends it.

Imports:

```ts
import TransactionFormDialog from '@/components/transactions/modals/TransactionFormDialog.vue';
import { useTransactionFormDialog } from '@/composables/useTransactionFormDialog';
import { onMounted, watch } from 'vue';
```

(`watch` / `onMounted` — merge with the existing `vue` import.)

After the delete-dialog state (around `openDeleteDialog`), add:

```ts
const { createRequested } = useTransactionFormDialog();

const formDialogOpen = ref(false);
const formDialogMode = ref<'create' | 'edit'>('create');
const editingTransactionId = ref<number | null>(null);

const editingTransaction = computed(
    () => props.transactions.data.find((tx) => tx.id === editingTransactionId.value) ?? null,
);

function openCreateDialog(): void {
    formDialogMode.value = 'create';
    editingTransactionId.value = null;
    formDialogOpen.value = true;
}

function openEditDialog(tx: Transaction): void {
    formDialogMode.value = 'edit';
    editingTransactionId.value = tx.id;
    formDialogOpen.value = true;
}

function consumeNewQuery(): void {
    const [path, query = ''] = page.url.split('?');
    const params = new URLSearchParams(query);

    if (params.get('new') !== '1') {
        return;
    }

    openCreateDialog();
    params.delete('new');
    const next = params.toString() === '' ? path : `${path}?${params.toString()}`;
    router.replace(next, { preserveState: true, preserveScroll: true });
}

watch(createRequested, (requested) => {
    if (!requested) {
        return;
    }

    openCreateDialog();
    createRequested.value = false;
});

watch(editingTransaction, (tx) => {
    if (formDialogOpen.value && formDialogMode.value === 'edit' && tx === null) {
        formDialogOpen.value = false;
    }
});

onMounted(() => {
    consumeNewQuery();
});

watch(
    () => page.url,
    () => {
        consumeNewQuery();
    },
);
```

`router` is already imported from `@inertiajs/vue3`.

Header menu item: replace the create `Link` with a select handler (same pattern as Import):

```vue
                        <DropdownMenuItem @select="openCreateDialog">
                            <Plus />
                            {{ t('transactions.index.addTransaction') }}
                        </DropdownMenuItem>
```

Empty-state buttons (filtered + first-use): replace `Button as-child` + `Link` with:

```vue
                                <Button type="button" @click="openCreateDialog">{{ t('transactions.index.addTransaction') }}</Button>
```

Desktop and mobile pencil: replace `Button as-child` + `Link` with:

```vue
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            type="button"
                                                            :aria-label="t('transactions.index.a11y.edit', { description: tx.description })"
                                                            @click="openEditDialog(tx)"
                                                        >
                                                            <Pencil class="h-4 w-4" aria-hidden="true" />
                                                        </Button>
```

Keep the `v-if="tx.account"` gate. Transfer and Import menu items stay `Link` / `@select`.

Before `DeleteTransactionDialog`, render:

```vue
        <TransactionFormDialog
            v-model:open="formDialogOpen"
            :mode="formDialogMode"
            :transaction="editingTransaction"
            :accounts="accounts"
            :categories="categories"
            :pockets="pockets"
            :account-id-filter="filters.account_id"
        />
```

If `categories` type on Index lacks `sort_order`, pass it anyway (JSON already has it). Widen the Index `Category` type with `sort_order: number` if TypeScript complains.

- [ ] **Step 4: Prettier**

Run: `npx prettier --write resources/js/composables/useTransactionFormDialog.ts resources/js/components/transactions/modals/TransactionFormDialog.vue resources/js/pages/transactions/Index.vue`

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useTransactionFormDialog.ts resources/js/components/transactions/modals/TransactionFormDialog.vue resources/js/pages/transactions/Index.vue
git commit -m "$(cat <<'EOF'
feat(transactions): add create and edit dialog on the index

EOF
)"
```

---

### Task 4: Sidebar create CTA + unlink preserveState

**Files:**
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/components/transfers/UnlinkTransferDialog.vue`

**Interfaces:**
- Consumes: `usePage().component`, `requestOpenCreateTransaction()`.
- Produces: On `transactions/Index`, sidebar add is a `Button` with no visit. Elsewhere it stays a `Link` to `transactions.create`. Unlink POST uses `preserveState: true` so the edit dialog stays open.

- [ ] **Step 1: Sidebar — local open vs visit**

In `AppSidebar.vue`, import `usePage` from `@inertiajs/vue3` and `requestOpenCreateTransaction` from `@/composables/useTransactionFormDialog`. Replace `createTransactionHref` with:

```ts
const page = usePage();
const isTransactionsIndex = computed(() => page.component === 'transactions/Index');
```

Replace the footer add control:

```vue
            <Button
                v-if="isTransactionsIndex"
                type="button"
                :size="isCollapsed ? 'icon' : 'default'"
                class="w-full group-data-[collapsible=icon]:size-8"
                :title="t('nav.addTransaction')"
                @click="requestOpenCreateTransaction()"
            >
                <Plus class="size-4 shrink-0" :stroke-width="1.7" />
                <span class="group-data-[collapsible=icon]:hidden">{{ t('nav.addTransaction') }}</span>
            </Button>
            <Button v-else as-child :size="isCollapsed ? 'icon' : 'default'" class="w-full group-data-[collapsible=icon]:size-8">
                <Link :href="route('transactions.create')" :title="t('nav.addTransaction')">
                    <Plus class="size-4 shrink-0" :stroke-width="1.7" />
                    <span class="group-data-[collapsible=icon]:hidden">{{ t('nav.addTransaction') }}</span>
                </Link>
            </Button>
```

- [ ] **Step 2: Unlink keeps Index state**

In `UnlinkTransferDialog.vue`, add `preserveState: true` to the `router.post` options (keep `preserveScroll: true`). Do **not** pass `only` — let Inertia refresh all index props (toast, banners) while preserving component state so `formDialogOpen` stays true.

```ts
    router.post(
        route('transfers.unlink', props.transferId),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                processing.value = false;
                emit('update:open', false);
            },
        },
    );
```

After unlink, `editingTransaction.transfer_id` becomes null from updated props; account/amount unlock. If the row vanished, Task 3’s `watch(editingTransaction)` closes the edit dialog.

- [ ] **Step 3: Prettier**

Run: `npx prettier --write resources/js/components/AppSidebar.vue resources/js/components/transfers/UnlinkTransferDialog.vue`

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/AppSidebar.vue resources/js/components/transfers/UnlinkTransferDialog.vue
git commit -m "$(cat <<'EOF'
feat(transactions): open create from the sidebar without leaving the index

EOF
)"
```

---

### Task 5: Delete create/edit pages, registry, unused i18n

**Files:**
- Delete: `resources/js/pages/transactions/Create.vue`
- Delete: `resources/js/pages/transactions/Edit.vue`
- Delete: `app/Http/Resources/Transactions/TransactionEditResource.php`
- Modify: `resources/js/lib/pageRegistry.ts`
- Modify: `resources/js/lib/pageChrome.test.ts`
- Modify: `resources/js/locales/pl.json`, `en.json`

**Interfaces:**
- Consumes: `transfers.create` and `pockets.edit` still in `pageNodes`.
- Produces: No Inertia pages or registry nodes for `transactions.create` / `transactions.edit`.

- [ ] **Step 1: Rewrite chrome tests (fail on missing nodes, then drop nodes)**

In `resources/js/lib/pageChrome.test.ts`, replace the ancestor example:

```ts
    it('links every ancestor and leaves only the leaf unlinked', () => {
        const chrome = resolvePageChrome(at('transfers.create'), {}, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'transactions.index.title', href: '/transactions.index' },
            { title: 'transfers.create.title', href: null },
        ]);
    });
```

Replace the detail override example:

```ts
    it('lets a detail page override the leaf, the title and the document title', () => {
        const chrome = resolvePageChrome(
            at('pockets.edit', { pocket: '12' }),
            { title: 'Wakacje', description: 'pockets.edit.title' },
            deps,
        );

        expect(chrome.crumbs).toEqual([
            { title: 'pockets.index.title', href: '/pockets.index' },
            { title: 'Wakacje', href: null },
        ]);
        expect(chrome.title).toBe('Wakacje');
        expect(chrome.description).toBe('pockets.edit.title');
        expect(chrome.documentTitle).toBe('Wakacje');
    });
```

- [ ] **Step 2: Run Vitest — expect PASS on the retargeted examples**

Run: `npm test -- resources/js/lib/pageChrome.test.ts`

Expected: PASS. `transfers.create` and `pockets.edit` already exist in `pageNodes`. The old `transactions.create` / `transactions.edit` examples are gone, so deleting those nodes in Step 3 will not fail this file.

- [ ] **Step 3: Drop registry nodes, pages, resource, i18n**

In `pageRegistry.ts`, delete:

```ts
    'transactions.create': { titleKey: 'transactions.create.title', parent: 'transactions' },
    'transactions.edit': { titleKey: 'transactions.edit.title', parent: 'transactions' },
```

Keep `transfers.create`.

Delete:

- `resources/js/pages/transactions/Create.vue`
- `resources/js/pages/transactions/Edit.vue`
- `app/Http/Resources/Transactions/TransactionEditResource.php`

In `pl.json` and `en.json`, remove:

- `transactions.create.back`
- `transactions.create.hints` (whole object)
- `transactions.edit.back`
- `transactions.edit.description`
- `transactions.edit.deleteAction`
- `transactions.edit.dangerZone`
- `transactions.edit.hints`

Keep `transactions.create.title`, `transactions.create.description`, `transactions.edit.title`, `transactions.edit.transfer`, `transactions.edit.statement`.

- [ ] **Step 4: Verify**

Run:

```bash
npx prettier --write resources/js/lib/pageRegistry.ts resources/js/lib/pageChrome.test.ts resources/js/locales/pl.json resources/js/locales/en.json
npm test -- resources/js/lib/pageChrome.test.ts resources/js/lib/pageRegistry.test.ts
./vendor/bin/sail artisan test --compact --filter=Transactions
```

Expected: Vitest PASS. Pest Transactions PASS (no remaining `component('transactions/Create|Edit')`).

- [ ] **Step 5: Commit**

```bash
git add -A resources/js/pages/transactions resources/js/lib/pageRegistry.ts resources/js/lib/pageChrome.test.ts resources/js/locales/pl.json resources/js/locales/en.json app/Http/Resources/Transactions/TransactionEditResource.php
git commit -m "$(cat <<'EOF'
refactor(transactions): drop create and edit pages

EOF
)"
```

`git add -A` on those paths is to record the deletions. Do not add unrelated dirty files.

---

### Task 6: Docs

**Files:**
- Modify: `.docs/checklist.md`
- Modify: `.docs/prd.md`

**Interfaces:**
- Consumes: shipped behavior from Tasks 1–5.
- Produces: checklist + PRD match list + P&L modals.

- [ ] **Step 1: Checklist**

Under §4 Dodanie transakcji, add a checked line (keep existing field bullets):

```
  - [x] Dodanie i edycja z listy `/transakcje` (modal; GET create → index?new=1; GET edit → index) **[plan 2026-08-26-transactions-modals]**
```

Change:

```
  - [x] Edycja transakcji typu `transfer` (z ustawionym `transfer_id`) — kwota/konto zablokowane; unlink w Edit **[plan §4, 2026-06-03]**
```

to:

```
  - [x] Edycja transakcji typu `transfer` (z ustawionym `transfer_id`) — kwota/konto zablokowane; unlink w modalu edycji **[plan §4, 2026-06-03; 2026-08-26-transactions-modals]**
```

Change:

```
  - [x] UI: akcja usuwania (Index + Edit, dialog potwierdzenia)
```

to:

```
  - [x] UI: akcja usuwania (Index, dialog potwierdzenia)
```

- [ ] **Step 2: PRD §7**

In `.docs/prd.md` the **Ekrany** sentence, replace `Transakcje (lista, dodaj, edytuj,` with `Transakcje (lista; dodaj/edytuj P&L w modalu,`. Keep the transfer / banner / category clause.

Final fragment:

`Transakcje (lista; dodaj/edytuj P&L w modalu, **transfer bez kategorii** z opcjonalnym/wymaganym celem FR-P3/G4, baner kandydatów FR-I6; **kategoria** wymagana w formularzach P&L od wave 2)`

- [ ] **Step 3: Commit**

```bash
git add .docs/checklist.md .docs/prd.md
git commit -m "$(cat <<'EOF'
docs: record transaction add/edit as index modals

EOF
)"
```

---

## Manual smoke (after Task 5)

- Header menu “Dodaj transakcję” opens the modal; transfer/import unchanged.
- Empty-state CTA opens create.
- Sidebar add while already on `/transakcje`: modal, no full reload.
- Sidebar add from dashboard: lands on list with create open once; URL loses `new`.
- Edit pencil (desktop + mobile) opens the form; deleted-account rows still have no pencil.
- 422 keeps the modal and shows field errors.
- Esc / overlay / X ignored while saving.
- Linked transfer: banner, unlink, fields unlock, toast, modal stays.
- Statement collapsible when `raw_statement_description` is set.
- Delete still on the row.
- `/transakcje/utworz` → list + create modal once.
- `/transakcje/{id}/edytuj` → list, modal **not** open.
- `transfers.create` page unchanged.

---

## Plan self-review

| Spec requirement | Task |
|------------------|------|
| Index `pockets` | 1 |
| GET create → index + remembered filters + `new=1`; `new` not in ALLOWED_KEYS | 2 |
| GET edit → index, no auto-open; 403 other user | 2 |
| Unlink tests `from` index | 2 |
| Form dialog create/edit, no hints column, collapsible `booked_at` + statement, transfer banner | 3 |
| CTAs / pencil → modal; `?new=1` strip; client signal | 3 |
| Telemetry on create open | 3 |
| Prefill account from filter | 3 |
| preserveState 422; dismiss blocked while saving | 3 |
| Sidebar C | 4 |
| Unlink preserveState, modal stays | 4 |
| Delete Create/Edit pages + `TransactionEditResource` | 5 |
| pageRegistry / pageChrome | 5 |
| i18n cleanup | 5 |
| checklist + PRD | 6 |
| No transfer modal, no list restyle | out of scope — no task |
