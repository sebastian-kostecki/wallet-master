# Transfer Edit Guards + Unlink on Edit — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zablokować edycję kwoty i konta na połączonych nogach transferu oraz dodać przycisk „Rozłącz transfer” na `transactions/Edit`, zgodnie z PRD FR-I6 i checklistą §4.

**Architecture:** Reguły w `UpdateTransaction` (warstwa domeny); komunikaty walidacji w `UpdateTransactionRequest::messages()`; UI — disabled pola + `UnlinkTransferDialog` wywołujący istniejący `TransferController::unlink`. Bez nowych endpointów.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, vue-i18n.

**Spec:** `.docs/superpowers/specs/2026-06-03-transfer-edit-unlink-design.md`

---

## File map

| Action | Path |
|--------|------|
| Modify | `app/Actions/Transactions/UpdateTransaction.php` |
| Modify | `app/Http/Requests/Transactions/UpdateTransactionRequest.php` |
| Create | `resources/js/components/transfers/UnlinkTransferDialog.vue` |
| Modify | `resources/js/pages/transactions/Edit.vue` |
| Modify | `resources/js/locales/pl.json` |
| Modify | `resources/js/locales/en.json` |
| Create | `tests/Feature/Transactions/TransactionUpdateTransferGuardsTest.php` |
| Create | `tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php` |
| Modify | `.docs/checklist.md` §4, §6.6 |

---

### Task 1: Backend — guard kwoty i konta (TDD)

**Files:**
- Create: `tests/Feature/Transactions/TransactionUpdateTransferGuardsTest.php`
- Modify: `app/Actions/Transactions/UpdateTransaction.php`
- Modify: `app/Http/Requests/Transactions/UpdateTransactionRequest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Enums\TransferMatchStatus;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function createLinkedTransferPair(User $user, int $plnId, string $transferId): array
{
    $from = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'From',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => '-50.00',
    ]);

    $to = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'To',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => '50.00',
    ]);

    $withdrawal = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $from->id,
        'currency_id' => $plnId,
        'date' => '2026-04-01',
        'booked_at' => '2026-04-01',
        'amount' => '-50.00',
        'type' => TransactionType::Transfer,
        'description' => 'Transfer out',
        'subject' => null,
        'normalized_description' => 'transfer out',
        'dedupe_hash' => md5('guard-out', true),
        'transfer_id' => $transferId,
        'transfer_match_status' => TransferMatchStatus::Auto,
    ]);

    $deposit = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $to->id,
        'currency_id' => $plnId,
        'date' => '2026-04-01',
        'booked_at' => '2026-04-01',
        'amount' => '50.00',
        'type' => TransactionType::Transfer,
        'description' => 'Transfer in',
        'subject' => null,
        'normalized_description' => 'transfer in',
        'dedupe_hash' => md5('guard-in', true),
        'transfer_id' => $transferId,
        'transfer_match_status' => TransferMatchStatus::Auto,
    ]);

    return [$withdrawal, $deposit, $from, $to];
}

test('cannot change amount on a linked transfer leg', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $transferId = (string) Str::uuid();

    [$withdrawal, , $from] = createLinkedTransferPair($user, $plnId, $transferId);

    $this->actingAs($user)
        ->put("/transactions/{$withdrawal->id}", [
            'account_id' => $from->id,
            'date' => '01-04-2026',
            'amount' => -99,
            'description' => 'Transfer out',
            'subject' => null,
        ])
        ->assertSessionHasErrors(['amount']);

    $withdrawal->refresh();
    expect($withdrawal->amount)->toBe('-50.00');
    expect($from->refresh()->current_balance)->toBe('-50.00');
});

test('cannot change account on a linked transfer leg', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $transferId = (string) Str::uuid();

    [$withdrawal, , $from, $to] = createLinkedTransferPair($user, $plnId, $transferId);

    $this->actingAs($user)
        ->put("/transactions/{$withdrawal->id}", [
            'account_id' => $to->id,
            'date' => '01-04-2026',
            'amount' => -50,
            'description' => 'Transfer out',
            'subject' => null,
        ])
        ->assertSessionHasErrors(['account_id']);

    expect($withdrawal->refresh()->account_id)->toBe($from->id);
});

test('can update description on a linked transfer leg', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $transferId = (string) Str::uuid();

    [$withdrawal, , $from] = createLinkedTransferPair($user, $plnId, $transferId);

    $this->actingAs($user)
        ->put("/transactions/{$withdrawal->id}", [
            'account_id' => $from->id,
            'date' => '01-04-2026',
            'amount' => -50,
            'description' => 'Updated label',
            'subject' => 'Bank',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('transactions.edit', $withdrawal));

    $withdrawal->refresh();
    expect($withdrawal->description)->toBe('Updated label');
    expect($withdrawal->subject)->toBe('Bank');
    expect($withdrawal->type)->toBe(TransactionType::Transfer);
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionUpdateTransferGuardsTest.php
```

Expected: failures (amount/account not blocked yet).

- [ ] **Step 3: Implement guards in `UpdateTransaction`**

Na początku `handle()`, po parsowaniu `$newAmount` i przed `DB::transaction`:

```php
$transferId = $transaction->transfer_id;
if ($transferId !== null && $transferId !== '') {
    $oldAmount = TransactionDedupe::amountToDecimalString((string) $transaction->amount);
    if ($newAmount !== $oldAmount) {
        throw ValidationException::withMessages([
            'amount' => 'Kwoty połączonego transferu nie można zmienić. Najpierw rozłącz transfer.',
        ]);
    }

    $newAccountId = (int) $validated['account_id'];
    if ($newAccountId !== (int) $transaction->account_id) {
        throw ValidationException::withMessages([
            'account_id' => 'Konta połączonego transferu nie można zmienić. Najpierw rozłącz transfer.',
        ]);
    }
}
```

(Użyj istniejącego importu `ValidationException`.)

- [ ] **Step 4: Add `messages()` on `UpdateTransactionRequest`**

```php
public function messages(): array
{
    return [
        'amount' => 'Kwoty połączonego transferu nie można zmienić. Najpierw rozłącz transfer.',
        'account_id' => 'Konta połączonego transferu nie można zmienić. Najpierw rozłącz transfer.',
    ];
}
```

Uwaga: Laravel mapuje błędy z Action na te same klucze — komunikaty muszą być spójne.

- [ ] **Step 5: Run tests — expect PASS**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionUpdateTransferGuardsTest.php
```

- [ ] **Step 6: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Actions/Transactions/UpdateTransaction.php app/Http/Requests/Transactions/UpdateTransactionRequest.php tests/Feature/Transactions/TransactionUpdateTransferGuardsTest.php
git commit -m "fix(transactions): block amount and account edits on linked transfers"
```

---

### Task 2: Vue — `UnlinkTransferDialog` + integracja Edit

**Files:**
- Create: `resources/js/components/transfers/UnlinkTransferDialog.vue`
- Modify: `resources/js/pages/transactions/Edit.vue`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add i18n keys**

W `transactions.edit` (pl + en):

```json
"transfer": {
    "bannerTitle": "Połączony transfer",
    "bannerDescription": "Kwoty i konta są zablokowane. Aby je zmienić, najpierw rozłącz transfer.",
    "unlinkAction": "Rozłącz transfer"
},
"unlink": {
    "title": "Rozłącz transfer?",
    "description": "Obie powiązane transakcje staną się zwykłym przychodem i wydatkiem. Salda kont pozostaną bez zmian.",
    "confirm": "Rozłącz"
}
```

(en: mirror meaning, e.g. "Unlink transfer", "Linked transfer", etc.)

- [ ] **Step 2: Create `UnlinkTransferDialog.vue`**

Wzoruj na `DeleteTransactionDialog.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        open: boolean;
        disabled?: boolean;
        transferId: string | null;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{
    'update:open': [open: boolean];
    processing: [processing: boolean];
}>();

const { t } = useI18n();
const processing = ref(false);

watch(processing, (value) => emit('processing', value), { immediate: true });

function unlink() {
    if (!props.transferId) {
        return;
    }

    processing.value = true;
    router.post(
        route('transfers.unlink', props.transferId),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                emit('update:open', false);
            },
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('transactions.unlink.title') }}</DialogTitle>
                <DialogDescription>{{ t('transactions.unlink.description') }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button type="button" variant="secondary">{{ t('actions.cancel') }}</Button>
                </DialogClose>
                <Button type="button" :disabled="disabled || processing" @click="unlink">
                    {{ t('transactions.unlink.confirm') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
```

- [ ] **Step 3: Wire `Edit.vue`**

1. Import `UnlinkTransferDialog`.
2. `const isLinkedTransfer = computed(() => Boolean(props.transaction.transfer_id));`
3. Gdy `isLinkedTransfer`:
   - Baner (np. `rounded-xl border border-primary/30 bg-primary/5 p-4`) z `transfer.bannerTitle` / `bannerDescription` + przycisk otwierający dialog.
   - `:disabled="form.processing || isLinkedTransfer"` na `DropdownSelect` konta i polu kwoty (`Input` amount).
4. Stan: `unlinkDialogOpen`, `unlinkProcessing`.
5. `<UnlinkTransferDialog v-model:open="unlinkDialogOpen" :transfer-id="transaction.transfer_id" :disabled="unlinkProcessing" @processing="(v) => (unlinkProcessing = v)" />`
6. Opcjonalnie zaktualizuj hint `transactions.edit.hints.amount` gdy transfer — krótki warunek w template lub osobny klucz `hints.amountTransfer`.

- [ ] **Step 4: Lint frontend (jeśli dotyczy)**

```bash
./vendor/bin/sail npm run lint
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/transfers/UnlinkTransferDialog.vue resources/js/pages/transactions/Edit.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "feat(transactions): add unlink transfer action on edit page"
```

---

### Task 3: Feature test — Edit Inertia + unlink flow

**Files:**
- Create: `tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php`

- [ ] **Step 1: Write test**

```php
test('edit page exposes transfer_id for linked leg', function () {
    // setup pair like Task 1 helper
    $response = $this->actingAs($user)->get("/transactions/{$withdrawal->id}/edit");
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('transactions/Edit', false)
        ->where('transaction.transfer_id', $transferId)
    );
});

test('unlink from edit redirects back and clears transfer_id', function () {
    $this->actingAs($user)
        ->from(route('transactions.edit', $withdrawal))
        ->post(route('transfers.unlink', $transferId))
        ->assertRedirect(route('transactions.edit', $withdrawal))
        ->assertSessionHas('toast.message_key', 'transfers.toast.unlinked');

    expect($withdrawal->refresh()->transfer_id)->toBeNull();
    expect($withdrawal->type)->toBe(TransactionType::Expense);
});
```

- [ ] **Step 2: Run**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php
```

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php
git commit -m "test(transactions): cover transfer edit props and unlink redirect"
```

---

### Task 4: Docs + regression

**Files:**
- Modify: `.docs/checklist.md`
- Modify: `.docs/superpowers/specs/2026-06-02-transfer-matcher-banner-design.md` (optional footnote: Edit UI done)

- [ ] **Step 1: Update checklist**

- §4 edycja: `[x] Edycja transakcji typu transfer — kwota/konto zablokowane; unlink w Edit`
- §6.6: usuń „przycisk w Edit — poza tym PR” lub oznacz `[x]`

- [ ] **Step 2: Full regression**

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/ tests/Feature/Transfers/
```

- [ ] **Step 3: Commit docs**

```bash
git add .docs/checklist.md
git commit -m "docs: mark transfer edit guards and unlink UI complete"
```

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| Block amount | Task 1 |
| Block account | Task 1 |
| Allow description/subject/dates | Task 1 test 3 |
| Unlink button + confirm | Task 2 |
| Toast + refresh edit | Task 2 + 3 |
| No new endpoints | — (reuse `transfers.unlink`) |

**Placeholder scan:** none.

---

## Verification checklist (manual, po implementacji)

1. Otwórz edycję nogi transferu (−X) — kwota i konto disabled, baner + „Rozłącz transfer”.
2. Zapisz zmianę opisu — sukces, typ nadal transfer.
3. Rozłącz — obie nogi na liście jako wydatek/przychód; edycja kwoty odblokowana.
4. Transakcja bez `transfer_id` — edycja kwoty działa jak wcześniej.
