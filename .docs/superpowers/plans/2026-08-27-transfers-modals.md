# Transfer Index Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Open transfer create as a dialog on `/transakcje`, with `GET transfers.create` redirecting to the index plus one-shot `transfer=1`.

**Architecture:** Reuse index `accounts` and `pockets`. `TransferController::create` becomes a redirect (same pattern as `TransactionController::create` with `new=1`). A new `TransferFormDialog` posts to existing `transfers.store`. No client composable — the only in-page CTA is the header menu. P&L still uses `?new=1`.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Vue 3, Ziggy, vue-i18n, Pest 4, Vitest, Tailwind v3, shadcn-vue Dialog (radix-vue), Sail.

**Spec:** `.docs/superpowers/specs/2026-08-27-transfers-modals-design.md`  
**Suggested branch:** `improvement/transfers-index-modals`

## Global Constraints

- Variant A: no `app/Services/`, no `Data/*Result`. Do not change `CreateTransfer` or `StoreTransferRequest`.
- Overlay state is Vue-only on `transactions.index`, except one-shot `transfer=1` which Index strips and must never enter `TransactionsIndexQuery::ALLOWED_KEYS`.
- `new=1` stays P&L-only. If both query flags are present, open transfer and strip both.
- Named route `transfers.create` and localized URI stay (redirect + guest auth).
- No `useTransferFormDialog` / `requestOpenCreateTransfer()`. No sidebar or empty-state transfer CTA.
- No transfer-pair edit. Legs stay in `TransactionFormDialog`.
- Code, comments, commits in English. User-facing copy in `pl.json` / `en.json`.
- After PHP: `vendor/bin/pint --dirty --format agent` then `./vendor/bin/sail artisan test --compact` on the touched test file. After Vue/TS: `npx prettier --write` on touched files; `npm test` for Vitest files.
- UI: `.cursor/rules/wallet-ui-design.mdc` and skill `openship-inspired-ui`. Dialog chrome is already shipped — do not restyle `ui/dialog`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `app/Http/Controllers/Transfers/TransferController.php` | `create()` redirect + `transfer=1` |
| Create | `resources/js/components/transfers/modals/TransferFormDialog.vue` | Create overlay (port from `Create.vue`, then delete the page) |
| Delete | `resources/js/pages/transfers/Create.vue` | After the dialog exists |
| Modify | `resources/js/pages/transactions/Index.vue` | Dialog state, menu button, `?transfer=1`, mutual exclusion |
| Modify | `resources/js/lib/pageRegistry.ts` | Drop `transfers.create` |
| Modify | `resources/js/lib/pageRegistry.test.ts` | Assert node is gone |
| Modify | `resources/js/lib/pageChrome.test.ts` | Ancestor example → `settings.profile` |
| Modify | `resources/js/locales/pl.json`, `en.json` | Remove `transfers.form.hint` |
| Modify | `tests/Feature/Transfers/TransferCreatePageTest.php` | GET create → redirect + `transfer=1` |
| Modify | `tests/Feature/Transfers/TransferPocketTest.php` | Drop create-page pockets assertion |
| Modify | `.docs/checklist.md`, `.docs/prd.md` | Record transfer create as index modal |

Unchanged on purpose: `CreateTransfer`, `StoreTransferRequest`, `TransactionsIndexQuery::ALLOWED_KEYS`, store/unlink/candidates, `TransactionFormDialog`, Import, `DeleteTransactionDialog`, index props.

---

### Task 1: GET create redirects to the index

**Files:**
- Modify: `tests/Feature/Transfers/TransferCreatePageTest.php`
- Modify: `tests/Feature/Transfers/TransferPocketTest.php`
- Modify: `app/Http/Controllers/Transfers/TransferController.php`

**Interfaces:**
- Consumes: `TransactionsIndexQuery::params()`, named route `transfers.create`.
- Produces: Auth `GET transfers.create` → `302` to `transactions.index` with remembered filters plus `transfer=1`. Guest still → login. `create()` no longer renders Inertia. Leave `Create.vue` on disk until Task 3 ports it.

- [ ] **Step 1: Rewrite `TransferCreatePageTest` to expect the redirect**

Replace the file contents with:

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

test('guest is redirected to login', function () {
    $this->get(route('transfers.create', absolute: false))->assertRedirect(route('login', absolute: false));
});

test('create page redirects to the index with transfer=1', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('transfers.create', absolute: false))
        ->assertRedirect(route('transactions.index', ['transfer' => 1], absolute: false));
});

test('create page redirects to the index with remembered filters and transfer=1', function () {
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
        ->get(route('transfers.create', absolute: false))
        ->assertRedirect(route('transactions.index', [
            'account_id' => $account->id,
            'from' => '01-04-2026',
            'to' => '30-04-2026',
            'transfer' => 1,
        ], absolute: false));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transfers/TransferCreatePageTest.php`

Expected: FAIL — owner GET create is `200` / Inertia `transfers/Create`, not a redirect with `transfer=1`.

- [ ] **Step 3: Redirect `create()`**

Replace `TransferController::create` and drop unused imports (`ListPockets`, `AccountResource`, `Account`, `Inertia`, `Response`). Keep `store` and `unlink` unchanged. Do **not** delete `Create.vue` yet.

```php
public function create(): RedirectResponse
{
    return to_route('transactions.index', [
        ...TransactionsIndexQuery::params(),
        'transfer' => 1,
    ]);
}
```

In `tests/Feature/Transfers/TransferPocketTest.php`, delete the test `transfer create page includes pockets list` and the unused `use Inertia\Testing\AssertableInertia as Assert;`. Pockets on the index stay covered by `TransactionPocketTest`.

- [ ] **Step 4: Run tests and Pint**

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact --filter=Transfers
```

Expected: PASS. Guest → login. Owner GET create → index + `transfer=1` (and remembered filters). Store / pocket / unlink tests unchanged.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Transfers/TransferCreatePageTest.php tests/Feature/Transfers/TransferPocketTest.php app/Http/Controllers/Transfers/TransferController.php
git commit -m "feat(transfers): redirect create to the transactions index"
```

---

### Task 2: Drop `transfers.create` from page chrome

**Files:**
- Modify: `resources/js/lib/pageRegistry.test.ts`
- Modify: `resources/js/lib/pageRegistry.ts`
- Modify: `resources/js/lib/pageChrome.test.ts`
- Modify: `resources/js/locales/pl.json`, `en.json`

**Interfaces:**
- Consumes: `pageNodes.settings.profile` (`matchRoutes: ['profile.edit']`, parent `settings`).
- Produces: No registry node for `transfers.create`. Ancestor chrome example uses `profile.edit`.

- [ ] **Step 1: Write the failing registry assertion**

Append to `describe('pageRegistry')` in `resources/js/lib/pageRegistry.test.ts`:

```ts
    it('does not register transfer create as a page', () => {
        expect(pageNodes['transfers.create']).toBeUndefined();
    });
```

- [ ] **Step 2: Run Vitest to verify it fails**

Run: `npm test -- resources/js/lib/pageRegistry.test.ts`

Expected: FAIL — `transfers.create` is still in `pageNodes`.

- [ ] **Step 3: Drop the node, retarget chrome, remove unused i18n**

In `resources/js/lib/pageRegistry.ts`, delete:

```ts
    'transfers.create': { titleKey: 'transfers.create.title', parent: 'transactions' },
```

In `resources/js/lib/pageChrome.test.ts`, replace the ancestor example:

```ts
    it('links every ancestor and leaves only the leaf unlinked', () => {
        const chrome = resolvePageChrome(at('profile.edit'), {}, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'settings.title', href: '/profile.edit' },
            { title: 'settings.nav.profile', href: null },
        ]);
    });
```

Keep the existing override test that uses `at('profile.edit', …)` with a custom title — it is a different case (overrides).

In `resources/js/locales/pl.json` and `en.json`, delete `transfers.form.hint` (the create-page hint column). Keep `transfers.create.title`, `description`, `empty`, and the rest of `transfers.form.*`. Do not delete `deletedNotice` / `deletedSuffix`.

- [ ] **Step 4: Run Vitest and Prettier**

```bash
npx prettier --write resources/js/lib/pageRegistry.ts resources/js/lib/pageRegistry.test.ts resources/js/lib/pageChrome.test.ts resources/js/locales/pl.json resources/js/locales/en.json
npm test -- resources/js/lib/pageChrome.test.ts resources/js/lib/pageRegistry.test.ts
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/pageRegistry.ts resources/js/lib/pageRegistry.test.ts resources/js/lib/pageChrome.test.ts resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "chore(transfers): drop create page from chrome registry"
```

---

### Task 3: `TransferFormDialog`

**Files:**
- Create: `resources/js/components/transfers/modals/TransferFormDialog.vue`
- Delete: `resources/js/pages/transfers/Create.vue`

**Interfaces:**
- Consumes: Index props `accounts`, `pockets`, `filters.account_id`; `useTransactionsIndexSearch().transactionsIndexSearch`; `route('transfers.store')`; `suggestedPocketMovement`; existing `transfers.*` / `actions.cancel` / `accounts.index.addAccount` keys.
- Produces: Dialog with `open` / `update:open`, optional `formResetKey`. Posts `transfers.store` with `preserveState` / `preserveScroll`. Closes on success. Ignores dismiss while `form.processing`.

- [ ] **Step 1: Create the dialog component**

Create `resources/js/components/transfers/modals/TransferFormDialog.vue` by porting `resources/js/pages/transfers/Create.vue` (still on disk). Wrap in the same Dialog chrome as `TransactionFormDialog` (`Dialog`, `DialogContent`, `DialogHeader`, `DialogFooter`, `onOpenChange` that blocks close while processing). Do not import `AppLayout`. Cancel must call `onOpenChange(false)`, not `Link` to the index.

```vue
<script setup lang="ts">
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import { normalizeAmount } from '@/lib/money';
import { suggestedPocketMovement } from '@/lib/suggestedPocketMovement';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type Account = {
    id: number;
    name: string;
    bank?: string;
    type?: string;
    is_deleted?: boolean;
    bank_icon_url: string | null;
};

const props = defineProps<{
    open: boolean;
    accounts: Account[];
    pockets: { id: number; name: string }[];
    accountIdFilter: number | null;
    formResetKey?: number;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();
const { transactionsIndexSearch } = useTransactionsIndexSearch();

const selectableAccounts = computed(() => props.accounts.filter((account) => !account.is_deleted));
const canTransfer = computed(() => selectableAccounts.value.length >= 2);

const accountOptions = computed<DropdownOption<number>[]>(() =>
    selectableAccounts.value.map((account) => ({ value: account.id, label: account.name })),
);
const accountsById = computed(() => new Map(selectableAccounts.value.map((account) => [account.id, account])));

const pocketOptions = computed<DropdownOption<number | null>[]>(() => [
    { value: null, label: t('transactions.fields.pocketNone') },
    ...props.pockets.map((pocket) => ({ value: pocket.id, label: pocket.name })),
]);

function isCashBank(bank: string | null | undefined): boolean {
    return (bank ?? '').toLowerCase() === 'cash';
}

function accountWellClass(bank: string | null | undefined): string {
    return isCashBank(bank)
        ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
        : 'bg-muted';
}

function todayDdMmYyyy(): string {
    const now = new Date();
    const d = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
    const dd = String(d.getUTCDate()).padStart(2, '0');
    const mm = String(d.getUTCMonth() + 1).padStart(2, '0');

    return `${dd}-${mm}-${d.getUTCFullYear()}`;
}

function defaultFromId(): number | null {
    const filterId = props.accountIdFilter;

    if (filterId !== null && selectableAccounts.value.some((account) => account.id === filterId)) {
        return filterId;
    }

    return selectableAccounts.value[0]?.id ?? null;
}

function defaultToId(fromId: number | null): number | null {
    return selectableAccounts.value.find((account) => account.id !== fromId)?.id ?? null;
}

const form = useForm<{
    from_account_id: number | null;
    to_account_id: number | null;
    pocket_id: number | null;
    pocket_movement: 'deposit' | 'withdrawal' | null;
    date: string;
    amount: string;
    subject: string;
    description: string;
}>({
    from_account_id: null,
    to_account_id: null,
    pocket_id: null,
    pocket_movement: null,
    date: todayDdMmYyyy(),
    amount: '0,00',
    subject: '',
    description: '',
});

const formErrorId = 'transfer-form-error';

const fromAccount = computed(() =>
    form.from_account_id !== null ? (accountsById.value.get(form.from_account_id) ?? null) : null,
);
const toAccount = computed(() =>
    form.to_account_id !== null ? (accountsById.value.get(form.to_account_id) ?? null) : null,
);
const isSameAccount = computed(
    () => form.from_account_id !== null && form.to_account_id !== null && form.from_account_id === form.to_account_id,
);

function resetForm(): void {
    const fromId = defaultFromId();

    form.clearErrors();
    form.from_account_id = fromId;
    form.to_account_id = defaultToId(fromId);
    form.pocket_id = null;
    form.pocket_movement = null;
    form.date = todayDdMmYyyy();
    form.amount = '0,00';
    form.subject = '';
    form.description = '';
}

async function focusFirstControl(): Promise<void> {
    await nextTick();

    if (!canTransfer.value) {
        document.getElementById('transfer-add-account')?.focus();

        return;
    }

    document.getElementById('from_account_id')?.focus();
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            resetForm();
            await focusFirstControl();
        }
    },
);

watch(
    () => props.formResetKey,
    () => {
        if (props.open) {
            resetForm();
        }
    },
);

watch(
    () => form.pocket_id,
    (pocketId) => {
        if (pocketId === null) {
            form.pocket_movement = null;

            return;
        }

        if (form.pocket_movement === null) {
            form.pocket_movement = suggestedPocketMovement(fromAccount.value?.type, toAccount.value?.type);
        }
    },
);

watch([() => form.from_account_id, () => form.to_account_id], () => {
    if (form.pocket_id === null) {
        return;
    }

    form.pocket_movement = suggestedPocketMovement(fromAccount.value?.type, toAccount.value?.type);
});

function onOpenChange(value: boolean): void {
    if (form.processing && !value) {
        return;
    }

    emit('update:open', value);
}

function submit(): void {
    if (!canTransfer.value) {
        return;
    }

    form.amount = normalizeAmount(form.amount);

    form.transform((data) => {
        const payload = { ...data };

        if (payload.pocket_id === null) {
            delete payload.pocket_movement;
        }

        return payload;
    });

    form.post(route('transfers.store') + transactionsIndexSearch.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    });
}
</script>
```

After `</script>`, put this template in the same file. For the form body, copy `resources/js/pages/transfers/Create.vue` lines 161–419 (from/to grid through the description `FormField`, including the same-account error). Then replace every `:class=" isCashBank(...) ? 'bg-gradient-to-br …' : 'bg-muted' "` with `:class="accountWellClass(…bank…)"`. Do not copy the Cancel `Link` / submit row (lines 421–429); the `DialogFooter` below replaces it. Do not copy the hint-cards column.

```vue
<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('transfers.create.title') }}</DialogTitle>
                <DialogDescription>{{ t('transfers.create.description') }}</DialogDescription>
            </DialogHeader>

            <div v-if="!canTransfer" class="grid gap-6">
                <p class="text-sm text-muted-foreground">{{ t('transfers.create.empty') }}</p>
                <DialogFooter>
                    <Button type="button" variant="secondary" @click="onOpenChange(false)">
                        {{ t('actions.cancel') }}
                    </Button>
                    <Button id="transfer-add-account" as-child>
                        <Link :href="route('accounts.index')">{{ t('accounts.index.addAccount') }}</Link>
                    </Button>
                </DialogFooter>
            </div>

            <form v-else class="grid gap-6" :aria-busy="form.processing ? 'true' : 'false'" @submit.prevent="submit">
                <!-- Create.vue lines 161-419 (fields only; no Cancel Link row) -->
                <DialogFooter>
                    <Button type="button" variant="secondary" :disabled="form.processing" @click="onOpenChange(false)">
                        {{ t('actions.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing || isSameAccount" :aria-busy="form.processing || undefined">
                        {{ form.processing ? t('transfers.form.saving') : t('transfers.form.submit') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
```

After the dialog file is complete, remove the page: `git rm resources/js/pages/transfers/Create.vue`.

- [ ] **Step 2: Format and remove the page**

```bash
npx prettier --write resources/js/components/transfers/modals/TransferFormDialog.vue
git rm resources/js/pages/transfers/Create.vue
```

Expected: dialog file formatted; `Create.vue` gone. No Pest for “dialog opened”.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/transfers/modals/TransferFormDialog.vue
git rm resources/js/pages/transfers/Create.vue
git commit -m "feat(transfers): add create form dialog"
```

---

### Task 4: Wire the dialog on `transactions/Index`

**Files:**
- Modify: `resources/js/pages/transactions/Index.vue`

**Interfaces:**
- Consumes: `TransferFormDialog` (`open`, `accounts`, `pockets`, `accountIdFilter`, `formResetKey`). Existing `openCreateDialog` / `openEditDialog` / `consumeNewQuery`.
- Produces: Header “Dodaj transfer” opens the transfer dialog locally. `?transfer=1` opens it once and is stripped. P&L and transfer dialogs never both open.

- [ ] **Step 1: Import, state, open helpers, query consumer**

Add import:

```ts
import TransferFormDialog from '@/components/transfers/modals/TransferFormDialog.vue';
```

On the Index `Account` type, add `is_deleted?: boolean`.

After the P&L dialog state (`formDialogOpen`, `formDialogMode`, `formResetKey`, `editingTransactionId`), add:

```ts
const transferDialogOpen = ref(false);
const transferFormResetKey = ref(0);

function openTransferDialog(): void {
    const alreadyOpen = transferDialogOpen.value;

    formDialogOpen.value = false;

    if (alreadyOpen) {
        transferFormResetKey.value++;
    }

    transferDialogOpen.value = true;
}
```

At the start of `openCreateDialog` and `openEditDialog`, add `transferDialogOpen.value = false`.

Replace `consumeNewQuery` with:

```ts
function consumeCreateQuery(): void {
    const [path, query = ''] = page.url.split('?');
    const params = new URLSearchParams(query);
    const wantsTransfer = params.get('transfer') === '1';
    const wantsNew = params.get('new') === '1';

    if (!wantsTransfer && !wantsNew) {
        return;
    }

    if (wantsTransfer) {
        openTransferDialog();
    } else {
        openCreateDialog();
    }

    params.delete('transfer');
    params.delete('new');
    const next = params.toString() === '' ? path : `${path}?${params.toString()}`;
    router.replace(next, { preserveState: true, preserveScroll: true });
}
```

Rename both call sites (`onMounted` and `watch(() => page.url)`) from `consumeNewQuery` to `consumeCreateQuery`.

- [ ] **Step 2: Header menu + mount the dialog**

Replace the transfer `DropdownMenuItem as-child` + `Link` with:

```vue
                        <DropdownMenuItem @select="openTransferDialog">
                            <ArrowRightLeft />
                            {{ t('transactions.index.addTransfer') }}
                        </DropdownMenuItem>
```

After `TransactionFormDialog`, add:

```vue
        <TransferFormDialog
            v-model:open="transferDialogOpen"
            :form-reset-key="transferFormResetKey"
            :accounts="accounts"
            :pockets="pockets"
            :account-id-filter="filters.account_id"
        />
```

Do not add transfer buttons to empty states. Do not add a composable.

- [ ] **Step 3: Format**

```bash
npx prettier --write resources/js/pages/transactions/Index.vue
```

Expected: Prettier clean.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/transactions/Index.vue
git commit -m "feat(transactions): open transfer create from the index dialog"
```

---

### Task 5: Docs and verification

**Files:**
- Modify: `.docs/checklist.md`
- Modify: `.docs/prd.md`

**Interfaces:**
- Consumes: Spec success criteria and §7 screens sentence.
- Produces: Checklist §5 and PRD screens match list + P&L **and transfer** modals.

- [ ] **Step 1: Checklist §5**

Under `### 5) Transfer — jedna akcja → 2 transakcje`, change the `UI “Transfer”` parent from `[~]` to `[x]` and add this bullet with the other UI items:

```md
  - [x] Dodanie z listy `/transakcje` (modal; GET create → index?transfer=1) **[plan 2026-08-27-transfers-modals]**
```

- [ ] **Step 2: PRD screens**

In `.docs/prd.md` **Ekrany**, replace the Transakcje clause so transfer create is a modal on the list. Target wording:

`Transakcje (lista; dodaj/edytuj P&L i dodaj transfer w modalach **bez** pola kieszeni na P&L, **transfer bez kategorii** z opcjonalną kieszenią i ruchem FR-P3, baner kandydatów FR-I6; **kategoria** wymagana w formularzach P&L od wave 2)`

- [ ] **Step 3: Full verification**

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact --filter=Transfers
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionCreatePageTest.php tests/Feature/Transactions/TransactionPocketTest.php
npx prettier --write resources/js/components/transfers/modals/TransferFormDialog.vue resources/js/pages/transactions/Index.vue resources/js/lib/pageRegistry.ts resources/js/lib/pageRegistry.test.ts resources/js/lib/pageChrome.test.ts
npm test -- resources/js/lib/pageChrome.test.ts resources/js/lib/pageRegistry.test.ts
```

Expected: PASS. P&L `GET create` still redirects with `new=1` only (no `transfer`). Transfer `GET create` redirects with `transfer=1` (and remembered filters). Registry has no `transfers.create`.

Manual smoke (do before calling the slice done):

1. Header menu “Dodaj transfer” on `/transakcje` opens the dialog; list stays.
2. Visit `/transfery/utworz` → list with transfer modal once; `transfer` gone from the URL.
3. `?new=1` still opens P&L only. Both flags → transfer only, both stripped.
4. 422 keeps the transfer modal and field errors.
5. Esc / X / overlay ignored while saving.
6. Fewer than two active accounts: empty copy + Add account → `/konta`.
7. Account filter prefills from; to is a different active account.
8. Success: toast, modal closes, two linked rows on the list.
9. Import, P&L edit/unlink, row delete unchanged.

- [ ] **Step 4: Commit**

```bash
git add .docs/checklist.md .docs/prd.md
git commit -m "docs: record transfer create as an index modal"
```

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| GET create → index + remembered filters + `transfer=1` | 1 |
| Guest still login | 1 (existing test kept) |
| `create()` no Inertia | 1 |
| Delete `Create.vue` after port | 3 |
| Drop TransferPocketTest create-page pockets (covered on index) | 1 |
| `transfer` not in `ALLOWED_KEYS` | 1 (redirect adds it; remember() ignores unknown keys) |
| Drop `pageRegistry` node; chrome ancestor → `settings.profile` | 2 |
| Remove `transfers.form.hint` | 2 |
| `TransferFormDialog` create-only, empty &lt; 2 accounts, prefill, pocket, dismiss-while-saving | 3 |
| No new telemetry on open | 3 (no `track`) |
| Header button; `?transfer=1`; mutual exclusion; no composable | 4 |
| Both query flags → transfer, strip both | 4 `consumeCreateQuery` |
| Checklist §5 + PRD screens | 5 |
| Out of scope (pair edit, empty-state CTA, sidebar, list restyle) | not tasked |
