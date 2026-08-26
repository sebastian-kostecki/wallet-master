# Transaction Scope and Pocket Catalog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make each transaction XOR-labelled (external → category, internal → optional pocket + Odłożenie/Wypłata), count envelope totals once per transfer pair without using account type, merge Kategorie/Kieszenie in the sidebar, and replace pocket archive with delete-that-clears-FKs.

**Architecture:** Scope is derived from `transfer_id`, not a new column. Persist `transactions.pocket_movement` (`deposit` | `withdrawal`) on both legs when `pocket_id` is set. `PocketMovementTotal` groups by `transfer_id` and takes `MAX(ABS(amount))`. Category and Pocket tables stay separate; `pageRegistry` tab group `catalog` spans `categories.index` and `pockets.index`.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Vue 3, Ziggy, vue-i18n, Vitest, Pest 4, Tailwind v3, Sail.

**Spec:** `.docs/superpowers/specs/2026-08-26-transaction-scope-pocket-catalog-design.md`  
**Suggested branch:** `improvement/transaction-scope-pocket-catalog`

## Global Constraints

- Variant A: Actions in `app/Actions/{Domain}/`, no `app/Services/`. Pocket math stays in `app/Support/Pockets/`.
- No stored `scope` column. Do not merge `categories` and `pockets` tables. Do not add a “zakres” dropdown.
- UI copy in `pl.json` / `en.json`. Code, comments, commits in English. Enum case names TitleCase (`Deposit`, `Withdrawal`); stored values `deposit`, `withdrawal`.
- After PHP: `vendor/bin/pint --dirty --format agent`, then `./vendor/bin/sail artisan test --compact` on the touched test file. After Vue/TS: `npx prettier --write` on touched files; `npm test` for the Vitest file.
- Tests via Sail. Artisan via `./vendor/bin/sail artisan … --no-interaction`.
- Preserve named routes and URIs (`categories.*`, `pockets.*`, `transfers.*`).
- Do not turn transfer create into a modal. Do not add a pocket filter on the transaction list. Do not implement same-account virtual allocation.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `app/Enums/PocketMovement.php` | `Deposit` / `Withdrawal` |
| Create | `database/migrations/2026_08_26_*_add_pocket_movement_and_drop_pocket_is_archived.php` | Column, backfill, drop archive |
| Create | `app/Support/Pockets/PocketMovementTotal.php` | Sum once per `transfer_id` |
| Create | `tests/Unit/Support/Pockets/PocketMovementTotalTest.php` | Pair counted once; ROR↔ROR |
| Modify | `app/Models/Transaction.php` | Cast + fillable `pocket_movement` |
| Modify | `app/Models/Pocket.php` | Drop `is_archived`, `scopeActive`, `hasLinkedTransactions` |
| Modify | `app/Support/Pockets/PocketBalance.php` | Use `PocketMovementTotal` |
| Modify | `app/Support/Pockets/PocketTransactionMetrics.php` | Same; drop `linked_expenses` |
| Modify | `tests/Unit/Support/Pockets/PocketBalanceTest.php` | Set `pocket_movement`; ROR↔ROR case |
| Modify | `app/Http/Requests/Concerns/ValidatesPocketId.php` | Optional pocket on every transfer; prohibit on P&L |
| Modify | `app/Http/Requests/Transfers/StoreTransferRequest.php` | `pocket_movement` required_with pocket |
| Modify | `app/Actions/Transfers/CreateTransfer.php` | Persist movement on both legs |
| Modify | `tests/Feature/Transfers/TransferPocketTest.php` | Reverse FR-P3 / ROR prohibition |
| Modify | `app/Http/Requests/Transactions/StoreTransactionRequest.php` | Prohibit pocket |
| Modify | `app/Http/Requests/Transactions/UpdateTransactionRequest.php` | P&L prohibit; linked transfer pocket+movement |
| Modify | `app/Actions/Transactions/StoreTransaction.php` | Stop writing pocket on P&L |
| Modify | `app/Actions/Transactions/UpdateTransaction.php` | Sync both legs; no pocket on P&L |
| Modify | `tests/Feature/Transactions/TransactionPocketTest.php` | P&L 422; linked edit both legs |
| Modify | `app/Actions/Transfers/UnlinkTransfer.php` | Clear `pocket_movement` |
| Modify | `app/Actions/Pockets/DeletePocket.php` | Null FKs then delete |
| Modify | `app/Policies/PocketPolicy.php` | Delete = owner only |
| Modify | `tests/Feature/Pockets/PocketCrudTest.php` | Delete with history succeeds |
| Modify | `app/Actions/Pockets/ListPockets.php` | No filter |
| Modify | `app/Http/Controllers/Pockets/PocketController.php` | Drop `?filter=` and archive-only redirect |
| Modify | `app/Actions/Pockets/UpdatePocket.php` / `UpdatePocketRequest` | Drop `is_archived` |
| Delete | `resources/js/components/pockets/modals/PocketArchiveDialog.vue` | Archive UI gone |
| Delete | `tests/Feature/Pockets/PocketArchiveTest.php` | Replaced by “no archive” |
| Modify | `resources/js/pages/transfers/Create.vue` | Always-optional pocket + movement |
| Modify | `resources/js/components/transactions/modals/TransactionFormDialog.vue` | XOR fields |
| Modify | `app/Http/Resources/Transactions/TransactionResource.php` | `pocket_movement` |
| Modify | `resources/js/lib/pageRegistry.ts` | `catalog` tab group |
| Modify | `resources/js/components/AppSidebar.vue` + `NavMain.vue` | One nav item, active on both paths |
| Modify | `resources/js/pages/pockets/Index.vue` / `Edit.vue` | No filters, no archive |
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` | All pockets, not `active()` |
| Modify | `resources/js/locales/pl.json` / `en.json` | Tabs, movement copy |
| Modify | `.docs/prd.md` / `.docs/checklist.md` | FR-P3/P4/P1, catalog |

Unchanged on purpose: transfer matcher (still nulls category, leaves pocket null), category CRUD pages (content), yearly budget (no pocket section).

---

### Task 1: `PocketMovement` enum, migration, model

**Files:**
- Create: `app/Enums/PocketMovement.php`
- Create: `database/migrations/2026_08_26_120000_add_pocket_movement_and_drop_pocket_is_archived.php` (use the timestamp artisan generates)
- Modify: `app/Models/Transaction.php`
- Modify: `database/factories/TransactionFactory.php` — `'pocket_movement' => null`

**Interfaces:**
- Consumes: existing `transactions.pocket_id`, `pockets.is_archived`.
- Produces: `PocketMovement::Deposit` value `'deposit'`; `PocketMovement::Withdrawal` value `'withdrawal'`; nullable `transactions.pocket_movement`; pockets with no `is_archived`.

- [ ] **Step 1: Add the enum**

```bash
./vendor/bin/sail artisan make:enum PocketMovement --no-interaction --string
```

Replace the generated enum with:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum PocketMovement: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
}
```

- [ ] **Step 2: Add the migration**

```bash
./vendor/bin/sail artisan make:migration add_pocket_movement_and_drop_pocket_is_archived --table=transactions --no-interaction
```

Migration `up()` (MySQL + SQLite-safe):

1. `Schema::table('transactions', …)` add `pocket_movement` nullable string 20 after `pocket_id`.
2. `DB::table('transactions')->whereNull('transfer_id')->update(['pocket_id' => null]);`
3. Backfill pairs that still have `pocket_id`:
   - Load distinct `transfer_id` where `pocket_id` is not null.
   - For each pair: if any Savings-account leg (`accounts.type = savings`) has `amount > 0` → `deposit`; else if any Savings leg has `amount < 0` → `withdrawal`; else → `deposit`.
   - Update **both** legs: `pocket_movement` = that value.
4. `DB::table('pockets')->update(['is_archived' => false]);`
5. `Schema::table('pockets', fn ($t) => $t->dropColumn('is_archived'));`

`down()`: add `is_archived` boolean default false on pockets; drop `transactions.pocket_movement`.

- [ ] **Step 3: Cast on Transaction**

In `app/Models/Transaction.php`: add `'pocket_movement'` to `$fillable`; in `casts()` add `'pocket_movement' => PocketMovement::class`; PHPDoc `@property PocketMovement|null $pocket_movement`.

Factory: `'pocket_movement' => null`.

- [ ] **Step 4: Migrate in test env**

```bash
./vendor/bin/sail artisan migrate --no-interaction
./vendor/bin/sail artisan test --compact tests/Unit/Support/Pockets/PocketBalanceTest.php
```

Expected: existing balance tests still **pass or fail** depending on whether they set `pocket_movement` (they do not yet — they may now count **0** for saved/released because movement is null). That failure is the Task 2 starting point. If they still pass because SQL ignores unknown filters, proceed — Task 2 rewrites them.

- [ ] **Step 5: Commit**

```bash
git add app/Enums/PocketMovement.php database/migrations/*pocket_movement* app/Models/Transaction.php database/factories/TransactionFactory.php
git commit -m "feat(transactions): add pocket_movement and drop pocket archive column"
```

---

### Task 2: Envelope totals from `pocket_movement`

**Files:**
- Create: `app/Support/Pockets/PocketMovementTotal.php`
- Create: `tests/Unit/Support/Pockets/PocketMovementTotalTest.php`
- Modify: `app/Support/Pockets/PocketBalance.php`
- Modify: `app/Support/Pockets/PocketTransactionMetrics.php`
- Modify: `tests/Unit/Support/Pockets/PocketBalanceTest.php`

**Interfaces:**
- Consumes: `PocketMovement`, `BudgetTransactionQuery`, `BudgetPeriod`.
- Produces: `PocketMovementTotal::sum(User $user, Pocket $pocket, PocketMovement $movement, ?BudgetPeriod $period = null): string` — one `|amount|` per `transfer_id`, only rows with that movement, `transactions.currency_id = pocket.currency_id`. `PocketBalance::cumulative` still returns `array{saved_total: string, released_total: string, balance: string}`. `PocketTransactionMetrics::forMonth` returns `array{saved: string, released: string, balance: string}` (**no** `linked_expenses`).

- [ ] **Step 1: Write the failing total test**

`tests/Unit/Support/Pockets/PocketMovementTotalTest.php` (Pest, `uses(TestCase::class, RefreshDatabase::class)`, seed `CurrencySeeder`):

```php
test('counts a checking-to-checking deposit once not twice', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'currency_id' => $plnId]);
    $from = Account::query()->create([
        'user_id' => $user->id, 'currency_id' => $plnId, 'name' => 'A',
        'bank' => Bank::Cash, 'type' => AccountType::Checking,
        'opening_balance' => 200, 'current_balance' => 200,
    ]);
    $to = Account::query()->create([
        'user_id' => $user->id, 'currency_id' => $plnId, 'name' => 'B',
        'bank' => Bank::Cash, 'type' => AccountType::Checking,
        'opening_balance' => 0, 'current_balance' => 0,
    ]);
    $transferId = (string) Str::uuid();
    foreach ([[$from, '-200.00'], [$to, '200.00']] as [$account, $amount]) {
        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'currency_id' => $plnId,
            'category_id' => null,
            'pocket_id' => $pocket->id,
            'pocket_movement' => PocketMovement::Deposit,
            'date' => '2026-04-01',
            'booked_at' => '2026-04-01',
            'amount' => $amount,
            'type' => TransactionType::Transfer,
            'description' => 'Allocate',
            'normalized_description' => 'allocate',
            'dedupe_hash' => md5($transferId.$amount, true),
            'transfer_id' => $transferId,
        ]);
    }

    expect(PocketMovementTotal::sum($user, $pocket, PocketMovement::Deposit))->toBe('200.00');
    expect(PocketMovementTotal::sum($user, $pocket, PocketMovement::Withdrawal))->toBe('0.00');
});
```

Add a second test: same fixture with `Withdrawal` → withdrawal sum `200.00`, deposit `0.00`.

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Pockets/PocketMovementTotalTest.php
```

Expected: FAIL (class not found).

- [ ] **Step 3: Implement `PocketMovementTotal`**

```php
final class PocketMovementTotal
{
    public static function sum(User $user, Pocket $pocket, PocketMovement $movement, ?BudgetPeriod $period = null): string
    {
        $query = BudgetTransactionQuery::forUser($user)
            ->where('pocket_id', $pocket->id)
            ->where('pocket_movement', $movement)
            ->whereNotNull('transfer_id')
            ->where('currency_id', $pocket->currency_id);

        if ($period !== null) {
            BudgetTransactionQuery::inPeriod($query, $period);
        }

        $amounts = $query
            ->groupBy('transfer_id')
            ->selectRaw('MAX(ABS(amount)) as amt')
            ->pluck('amt');

        $total = '0.00';
        foreach ($amounts as $amt) {
            $total = bcadd($total, TransactionDedupe::amountToDecimalString((string) $amt), 2);
        }

        return $total;
    }
}
```

Rewrite `PocketBalance::cumulative` saved/released via this helper (drop `AccountType::Savings` `whereHas`). `monthlyNetMap`: for each distinct `Y-m` of deposit/withdrawal pairs, net = deposit − withdrawal (group by `transfer_id` then by month of `booked_at`; use one row per pair — `MAX(booked_at)` is fine, both legs share the date).

`PocketTransactionMetrics::forMonth`: `saved` / `released` from `PocketMovementTotal::sum(..., $period)`; `balance` = saved − released; **delete** the `linked_expenses` query and array key. Update any test that asserts `linked_expenses`.

Update existing `PocketBalanceTest` rows: set `'pocket_movement' => PocketMovement::Deposit` on the January pair and `Withdrawal` on the March pair. Keep the EUR-ignore test by giving the EUR row `Deposit` — it must stay excluded via `currency_id`.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Pockets/PocketMovementTotalTest.php tests/Unit/Support/Pockets/PocketBalanceTest.php
```

Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Pockets tests/Unit/Support/Pockets
git commit -m "feat(pockets): count envelope totals from pocket_movement"
```

---

### Task 3: Transfer validation — optional pocket + required movement

**Files:**
- Modify: `app/Http/Requests/Concerns/ValidatesPocketId.php`
- Modify: `app/Http/Requests/Transfers/StoreTransferRequest.php`
- Modify: `app/Actions/Transfers/CreateTransfer.php`
- Modify: `tests/Feature/Transfers/TransferPocketTest.php`

**Interfaces:**
- Consumes: `PocketMovement`.
- Produces: `pocketIdRulesForTransfer()` always optional `pocket_id` (exists for user) + currency vs **both** account ids. `pocket_movement` => `['nullable', 'required_with:pocket_id', Rule::enum(PocketMovement::class), 'required_without' is wrong — use `prohibited_unless:pocket_id`]`. CreateTransfer writes the same `pocket_id` and `pocket_movement` (enum or null) on both legs.

- [ ] **Step 1: Rewrite TransferPocketTest (failing)**

Replace the three tests `transfer to savings account requires pocket_id`, `transfer from savings account requires pocket_id`, `transfer between checking accounts prohibits pocket_id` with:

1. `savings transfer without pocket succeeds` — same checking→savings fixture, **no** `pocket_id` → `assertSessionHasNoErrors()`; both legs `pocket_id` null, `pocket_movement` null.
2. `checking-to-checking transfer with pocket and deposit persists both legs` — former prohibited case + `'pocket_movement' => 'deposit'` →  both legs same pocket and `PocketMovement::Deposit`.
3. `transfer with pocket_id without pocket_movement fails` — checking→savings with pocket, no movement → `assertSessionHasErrors('pocket_movement')`.
4. Keep `transfer persists same pocket_id on both legs` but add `'pocket_movement' => 'deposit'` to the POST and assert movement on both legs.
5. Keep currency mismatch test; add `'pocket_movement' => 'deposit'` so the failure stays on `pocket_id`.

- [ ] **Step 2: Run to verify fail**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transfers/TransferPocketTest.php
```

Expected: FAIL (savings still required / ROR still prohibited / movement ignored).

- [ ] **Step 3: Implement rules + persist**

`ValidatesPocketId::pocketIdRulesForTransfer`: **always** the optional exists+currency closure (the current Savings branch without `'required'`). Delete the `prohibited` ROR branch. Keep `transferInvolvesSavingsAccount` only if still used; if unused, delete it.

`StoreTransferRequest::rules` add:

```php
'pocket_movement' => [
    Rule::requiredIf(fn () => $this->filled('pocket_id')),
    Rule::prohibitedIf(fn () => ! $this->filled('pocket_id')),
    Rule::enum(PocketMovement::class),
],
```

When `pocket_id` is empty, do not send/store movement (null on both legs).

Polish messages on the request:

```php
public function messages(): array
{
    return [
        'pocket_movement.required_with' => 'Wybierz odłożenie albo wypłatę.',
        'pocket_id.prohibited' => 'Kieszeń dotyczy tylko przelewów wewnętrznych.',
    ];
}
```

(The P&L `prohibited` message is used in Task 4; you can put shared strings in the trait.)

`CreateTransfer`: read `$movement = isset($validated['pocket_movement']) ? PocketMovement::from($validated['pocket_movement']) : null;` and set `'pocket_movement' => $movement` on both `create()` arrays.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transfers/TransferPocketTest.php tests/Feature/Transfers/CreateTransferTest.php tests/Feature/Transfers/TransferCategoryDecouplingTest.php
```

Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git commit -m "feat(transfers): optional pocket with explicit deposit or withdrawal"
```

---

### Task 4: P&L XOR + linked-transfer edit syncs both legs

**Files:**
- Modify: `app/Http/Requests/Concerns/ValidatesPocketId.php` — add `prohibitedPocketRules(): array` returning `pocket_id` + `pocket_movement` `prohibited`
- Modify: `app/Http/Requests/Transactions/StoreTransactionRequest.php`
- Modify: `app/Http/Requests/Transactions/UpdateTransactionRequest.php`
- Modify: `app/Actions/Transactions/StoreTransaction.php`
- Modify: `app/Actions/Transactions/UpdateTransaction.php`
- Modify: `app/Http/Resources/Transactions/TransactionResource.php`
- Modify: `tests/Feature/Transactions/TransactionPocketTest.php`

**Interfaces:**
- Consumes: Task 3 pocket rules.
- Produces: P&L store/update reject `pocket_id` / `pocket_movement`. Linked update accepts optional pocket + movement and writes both legs. Resource includes `'pocket_movement' => $this->pocket_movement?->value`.

- [ ] **Step 1: Rewrite TransactionPocketTest**

- Rename `expense can optionally reference a pocket` → `expense rejects pocket_id` (same POST, `assertSessionHasErrors('pocket_id')`).
- `pocket_id must belong to user` on **P&L** is redundant with prohibited — change to transfer store with another user’s pocket + `pocket_movement=deposit` → errors `pocket_id` (can live in TransferPocketTest; delete this P&L test).
- `user can update transaction pocket_id` → `expense update rejects pocket_id` (`assertSessionHasErrors('pocket_id')`).
- Keep `transaction index includes pockets list`.
- Add `linked transfer update writes pocket and movement on both legs`: create a checking↔checking transfer **without** pocket (Task 3), PATCH one leg with `pocket_id`, `pocket_movement=withdrawal`, locked amount/account/date/description; assert **both** rows have that pocket and `Withdrawal`.
- Add `linked transfer update clearing pocket nulls both legs`.

- [ ] **Step 2: Run to verify fail**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionPocketTest.php
```

Expected: FAIL (expense still accepts pocket; linked update only one leg).

- [ ] **Step 3: Implement**

`StoreTransactionRequest::rules`: replace `optionalPocketIdRules()` with `prohibitedPocketRules()`. Remove `validateOptionalPocketCurrency` from `withValidator`.

`StoreTransaction`: do not set `pocket_id` from input (leave null / omit).

`UpdateTransactionRequest::rules`: if `$isTransferLeg`, merge transfer pocket rules (optional pocket + movement) and validate currency against **both** legs’ `account_id`s (load sibling in `withValidator`). Else `prohibitedPocketRules()` + existing category rules.

`UpdateTransaction::handle`: after saving the current row, if `$transaction->transfer_id` is non-empty, `Transaction::query()->where('transfer_id', $transaction->transfer_id)->where('id', '!=', $transaction->id)->update(['pocket_id' => $pocketId, 'pocket_movement' => $movement])`. For P&L force `$pocketId = null` and movement null even if the array key is absent.

`TransactionResource`: add `'pocket_movement' => $this->pocket_movement?->value`.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionPocketTest.php tests/Feature/Transactions --filter=Store tests/Feature/Transactions --filter=Update
```

If the filter is noisy, run `tests/Feature/Transactions/TransactionStoreTest.php` and `TransactionUpdateTest.php` plus `TransactionPocketTest.php`.

Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git commit -m "feat(transactions): XOR category and pocket; sync transfer legs"
```

---

### Task 5: Unlink clears movement; delete pocket clears FKs

**Files:**
- Modify: `app/Actions/Transfers/UnlinkTransfer.php`
- Modify: `app/Actions/Pockets/DeletePocket.php`
- Modify: `app/Policies/PocketPolicy.php`
- Modify: `tests/Feature/Pockets/PocketCrudTest.php`
- Modify: `tests/Feature/Transfers/TransfersUnlinkTest.php` (assert `pocket_movement` null if a pocket was set)

**Interfaces:**
- Consumes: `DeletePocket::handle(Pocket $pocket): void`.
- Produces: Unlink sets `pocket_movement => null`. Delete: `Transaction::query()->where('pocket_id', $pocket->id)->update(['pocket_id' => null, 'pocket_movement' => null])` then `$pocket->delete()`. Policy `delete`: `$pocket->user_id === $user->id` only. Remove `hasLinkedTransactions()` from `Pocket` if unused.

- [ ] **Step 1: Failing tests**

In `PocketCrudTest`, replace `cannot delete pocket with linked transactions` with `deleting a pocket clears pocket_id on transfers and removes the pocket`:

- Create checking→checking transfer with pocket + deposit (via `transfers.store`).
- `delete(route('pockets.destroy', $pocket))->assertRedirect()`.
- `expect(Pocket::find($pocket->id))->toBeNull()`.
- Both transfer rows: `pocket_id` null, `pocket_movement` null, `transfer_id` still set.

Add unlink assertion in `TransfersUnlinkTest` (or a new example): unlink a pocketted pair → fallback categories, `pocket_id` and `pocket_movement` null.

- [ ] **Step 2: Run to verify fail**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketCrudTest.php --filter="deleting a pocket"
```

Expected: FAIL (403 / validation “Cannot delete pocket…”).

- [ ] **Step 3: Implement**

`DeletePocket`:

```php
public function handle(Pocket $pocket): void
{
    DB::transaction(function () use ($pocket): void {
        Transaction::query()
            ->where('user_id', $pocket->user_id)
            ->where('pocket_id', $pocket->id)
            ->update([
                'pocket_id' => null,
                'pocket_movement' => null,
            ]);

        $pocket->delete();
    });
}
```

`PocketPolicy::delete`: owner only.

`UnlinkTransfer` update array: `'pocket_movement' => null`.

Delete `Pocket::hasLinkedTransactions` and `Schema` import if unused.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketCrudTest.php tests/Feature/Transfers/TransfersUnlinkTest.php
```

Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git commit -m "feat(pockets): delete clears assignments; unlink clears movement"
```

---

### Task 6: Remove pocket archive and list filters

**Files:**
- Modify: `app/Models/Pocket.php` — drop `is_archived` fillable/cast/PHPDoc/`scopeActive`
- Modify: `app/Actions/Pockets/ListPockets.php` — `handle(User $user): void` only; `ordered()` all pockets
- Modify: `app/Http/Controllers/Pockets/PocketController.php` — no `filter` query; drop archive-only `back()` branch
- Modify: `app/Actions/Pockets/UpdatePocket.php`, `StorePocket.php`, `UpdatePocketRequest.php`
- Modify: `app/Http/Resources/Pockets/PocketResource.php`
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php` — `forUser()->ordered()` **without** `active()`
- Modify: `database/factories/PocketFactory.php`
- Delete: `tests/Feature/Pockets/PocketArchiveTest.php`
- Delete: `resources/js/components/pockets/modals/PocketArchiveDialog.vue`
- Modify: `resources/js/pages/pockets/Index.vue`, `Edit.vue`
- Grep `is_archived` / `pockets.filters` / `PocketArchive` and clear leftovers (`MigrateLegacySavingsEstimateTest` should stop asserting `is_archived`).

**Interfaces:**
- Consumes: Task 1 dropped the column.
- Produces: `ListPockets::handle(User $user): void`. Index Inertia props: `{ pockets }` only (no `filter`).

- [ ] **Step 1: Failing / rewrite tests**

Update any index test that GETs `pockets.index?filter=archived` to expect the full list (or ignore query). Delete `PocketArchiveTest.php`. If `ListMonthlyBudget` tests rely on archived pockets being hidden, invert: archived-column is gone so every pocket appears.

- [ ] **Step 2: Run pockets + monthly budget tests (expect fail on archive APIs)**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Pockets tests/Feature/Budgets/MonthlyBudgetTest.php
```

- [ ] **Step 3: Implement backend + Vue**

`ListPockets::handle(User $user): void` — no match/filter.

`PocketController::index`: `$listPockets->handle($request->user());` props `pockets` only.

`UpdatePocketRequest`: remove `is_archived`. `UpdatePocket`: remove archive telemetry. `StorePocket`: stop setting `is_archived`.

`Index.vue`: remove filter tabs/chips, archive buttons, `PocketArchiveDialog`. Keep reorder, edit, delete. `Edit.vue`: remove archive checkbox.

`ListMonthlyBudget::buildPocketRows`: drop `->active()`.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Pockets tests/Feature/Budgets/MonthlyBudgetTest.php
```

Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
npx prettier --write resources/js/pages/pockets/Index.vue resources/js/pages/pockets/Edit.vue
git commit -m "feat(pockets): drop archive and list filters"
```

---

### Task 7: Transfer and transaction form UI

**Files:**
- Modify: `resources/js/pages/transfers/Create.vue`
- Modify: `resources/js/components/transactions/modals/TransactionFormDialog.vue`
- Modify: `resources/js/locales/pl.json`, `resources/js/locales/en.json`

**Interfaces:**
- Consumes: `pocket_movement` on `TransactionResource`; pockets list already on index and transfer create.
- Produces: Transfer form always shows optional pocket; when `pocket_id` set, segmented **Odłożenie | Wypłata** posts `pocket_movement`. Default `deposit`. If exactly one account `type === 'savings'`, dest savings → `deposit`, source savings → `withdrawal` (watch from/to). P&L modal: no pocket field. Linked-transfer modal: no category; pocket + movement; clearing pocket omits/nulls both.

- [ ] **Step 1: Locale keys**

`transfers.form.pocketMovement`: PL `Kierunek kieszeni` / EN `Envelope direction`  
`transfers.form.pocketMovementDeposit`: PL `Odłożenie` / EN `Set aside`  
`transfers.form.pocketMovementWithdrawal`: PL `Wypłata` / EN `Take out`  
`transfers.form.pocketMovementHint`: PL `Odłożenie zwiększa saldo kieszeni. Wypłata zmniejsza — gdy ściągasz odłożone pieniądze, żeby je wydać.` / EN equivalent.

- [ ] **Step 2: Transfer create**

Remove `involvesSavings` and the watcher that clears pocket. Always render the pocket `Select`. Add `pocket_movement: 'deposit' | 'withdrawal' | null` on `useForm` (null when no pocket). When pocket selected and movement null, set `'deposit'`. Watch from/to: if exactly one is savings, set movement as above (overwrites — acceptable). POST includes `pocket_movement` only when `pocket_id` is set.

- [ ] **Step 3: TransactionFormDialog**

If `isLinkedTransfer`: hide category field; show pocket + movement (same control as transfer create); submit `pocket_id` + `pocket_movement` (or nulls). Else: keep category; **do not** render pocket; do not send `pocket_id`.

Hydrate edit from `props.transaction.pocket_id` and `props.transaction.pocket_movement`.

- [ ] **Step 4: Prettier + Vitest if you add a tiny helper**

If the savings default is extracted to `resources/js/lib/suggestedPocketMovement.ts`:

```ts
export function suggestedPocketMovement(fromType: string | undefined, toType: string | undefined): 'deposit' | 'withdrawal' {
    const fromSav = fromType === 'savings';
    const toSav = toType === 'savings';
    if (fromSav !== toSav) {
        return toSav ? 'deposit' : 'withdrawal';
    }

    return 'deposit';
}
```

```bash
npx prettier --write resources/js/pages/transfers/Create.vue resources/js/components/transactions/modals/TransactionFormDialog.vue resources/js/lib/suggestedPocketMovement.ts
npm test -- resources/js/lib/suggestedPocketMovement.test.ts
```

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(transfers): pocket optional with set-aside or take-out control"
```

---

### Task 8: Catalog chrome (one sidebar item, two tabs)

**Files:**
- Modify: `resources/js/lib/pageRegistry.ts`
- Modify: `resources/js/lib/pageRegistry.test.ts`
- Modify: `resources/js/lib/pageChrome.test.ts`
- Modify: `resources/js/types/index.ts` — `NavItem.alsoActiveFor?: string[]`
- Modify: `resources/js/components/NavMain.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/locales/pl.json` / `en.json` — `categories.tabs.pnl`, `categories.tabs.pockets`

**Interfaces:**
- Consumes: existing `PageTabGroup`.
- Produces: `pageTabGroups.catalog` with `defaultKey: 'pnl'`, tabs `{ key: 'pnl', labelKey: 'categories.tabs.pnl', route: 'categories.index' }` and `{ key: 'pockets', labelKey: 'categories.tabs.pockets', route: 'pockets.index' }`. `pageNodes.categories.tabGroup = 'catalog'`, `pageNodes.pockets.tabGroup = 'catalog'`. **Delete** `pageTabGroups.pockets`. Sidebar: remove the Kieszenie item; Kategorie `href: route('categories.index')`, `alsoActiveFor: [route('pockets.index')]`.

- [ ] **Step 1: Rewrite chrome tests**

In `pageChrome.test.ts`, pockets index should expose **catalog** tabs (`pnl` / `pockets`), not filter tabs. `documentTitle` for pockets index is `pockets.index.title` (node title) · active tab `categories.tabs.pockets` — follow whatever `resolvePageChrome` already does for budget (node title + tab). Do **not** expect `pockets.filters.archived`.

Add `pageRegistry.test.ts` example like accounts: `catalog` defaultKey `pnl`, two routes.

- [ ] **Step 2: Run Vitest (expect fail)**

```bash
npm test -- resources/js/lib/pageRegistry.test.ts resources/js/lib/pageChrome.test.ts
```

- [ ] **Step 3: Implement registry + sidebar**

`NavMain.isNavItemActive`: also return true if any `item.alsoActiveFor` path matches `current === target || current.startsWith(target + '/')`.

`AppSidebar`: drop Target/pockets item. Kategorie:

```ts
{
    title: t('categories.index.title'),
    href: route('categories.index'),
    alsoActiveFor: [route('pockets.index')],
    icon: Tags,
},
```

Tab labels: PL `Przychody i wydatki` / `Kieszenie`; EN `Income and expenses` / `Envelopes`.

- [ ] **Step 4: Run Vitest**

```bash
npm test -- resources/js/lib/pageRegistry.test.ts resources/js/lib/pageChrome.test.ts
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
npx prettier --write resources/js/lib/pageRegistry.ts resources/js/lib/pageRegistry.test.ts resources/js/lib/pageChrome.test.ts resources/js/components/NavMain.vue resources/js/components/AppSidebar.vue resources/js/types/index.ts
git commit -m "feat(ui): merge categories and pockets into one catalog nav"
```

---

### Task 9: Monthly budget + PRD/checklist

**Files:**
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php` — ROR↔ROR deposit appears in `saved`; no `linked_expenses` in `pocket_rows`; section present for any pocket (no archive).
- Modify: `.docs/prd.md` — apply spec “PRD delta” (FR-P1, P3, drop P4, C5 formula, glossary, screens, telemetry).
- Modify: `.docs/checklist.md` §18/§19 — record XOR, optional pocket, catalog nav, no archive, delete-clears-FKs, `pocket_movement`.

**Interfaces:**
- Consumes: Task 2 metrics shape without `linked_expenses`; Task 6 all pockets listed.

- [ ] **Step 1: Adjust monthly budget tests that still assume Savings-only saved or FR-P4 expenses**

Grep `linked_expenses`, `Savings`, `pocket_id` in `tests/Feature/Budgets`. Add one test: checking↔checking transfer with `pocket_movement=deposit` in the viewed month → that pocket’s `saved` equals the amount.

- [ ] **Step 2: Run**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php
```

Fix `ListMonthlyBudget` / Vue if a leftover key breaks Inertia asserts.

- [ ] **Step 3: PRD + checklist**

Edit `.docs/prd.md` in place: FR-P3 optional + movement; delete FR-P4 section and Should bullet; FR-P1 delete-clears + no archive/filters; FR-C5 metrics from `pocket_movement`; glossary XOR; sidebar one item; telemetry drop `pocket_archived`, `pocket_unarchived`, `pocket_assigned_transaction`.

Checklist §19: mark the old “kieszeń wymagana na Savings / opcjonalna na wydatku” as superseded by this plan; add a checked or new `[plan]` line pointing at this spec.

- [ ] **Step 4: Full domain test pass**

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact --filter=Pocket
./vendor/bin/sail artisan test --compact tests/Feature/Transfers
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionPocketTest.php
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git commit -m "docs: align PRD and checklist with transaction scope catalog"
```

---

## Self-review (spec coverage)

| Spec requirement | Task |
|------------------|------|
| XOR category vs pocket | 3, 4, 7 |
| Derived scope, no column | (none added) |
| `pocket_movement` + once per `transfer_id` | 1, 2 |
| Optional pocket including Savings; ROR/cash allowed | 3, 7 |
| Smart default Savings dest/source | 7 (`suggestedPocketMovement`) |
| Catalog tabs + one sidebar item | 8 |
| No pocket filters / no archive | 1, 6 |
| Delete clears FKs | 5 |
| Linked edit both legs; post-import assign | 4, 7 |
| Unlink clears pocket + movement | 5 |
| Import matcher unchanged (null category, empty pocket) | unchanged |
| Budget formula + no `linked_expenses` | 2, 9 |
| P&L form no pocket; list no movement on row | 7 (list already shows pocket name via resource) |
| Migration backfill + unarchive | 1 |
| PRD delta | 9 |

No TBD. Types: `PocketMovement::Deposit` / `Withdrawal`; `PocketMovementTotal::sum`; `ListPockets::handle(User $user): void`.
