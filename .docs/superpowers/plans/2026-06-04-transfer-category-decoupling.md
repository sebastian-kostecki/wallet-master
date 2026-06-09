# Transfer category decoupling — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the system „Oszczędności” P&L category and stop requiring `category_id` on internal transfers; savings tracking stays on `goal_id` only.

**Architecture:** Data migration nulls `category_id` on transfer legs, removes legacy „Oszczędności” categories, and makes the column nullable at DB level. Backend enforces: P&L transactions require category; transfer legs must have `category_id = null`. UI drops category from transfer form; transaction list shows goal (optional) instead of category for transfers. Unlink assigns fallback category via `DefaultCategoryId`.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Sail for tests/migrations.

**Spec:** `.docs/superpowers/specs/2026-06-04-transfer-category-decoupling-design.md`  
**PRD:** already updated (2026-06-04)

**Suggested branch:** `improvement/transfer-category-decoupling` (from `develop`)

---

## File map

| Action | Path |
|--------|------|
| Create | `database/migrations/2026_06_04_120000_decouple_transfer_categories.php` |
| Modify | `app/Support/Categories/CategoryDefaults.php` — remove „Oszczędności” row |
| Modify | `app/Support/Categories/DefaultCategoryId.php` — remove Transfer branch |
| Modify | `tests/TestCase.php` — skip auto-category when `transfer_id` set or `type = transfer` |
| Modify | `app/Http/Requests/Transfers/StoreTransferRequest.php` — reject `category_id` |
| Modify | `app/Actions/Transfers/CreateTransfer.php` — no category; `type = Transfer` |
| Modify | `app/Http/Controllers/Transfers/TransferController.php` — drop categories props |
| Modify | `app/Actions/Transfers/UnlinkTransfer.php` — fallback category |
| Modify | `app/Imports/TransferMatcher.php` — `category_id = null` on auto-link |
| Modify | `app/Actions/Transfers/ConfirmTransferCandidate.php` — `category_id = null` on confirm |
| Modify | `app/Http/Resources/Transactions/TransactionResource.php` — add `goal_id`, `goal` |
| Modify | `app/Actions/Transactions/ListTransactions.php` — eager-load `goal` |
| Modify | `resources/js/pages/transfers/Create.vue` — remove category field |
| Modify | `resources/js/pages/transactions/Index.vue` — transfer column shows goal / transfer label |
| Modify | `resources/js/locales/pl.json`, `en.json` — copy keys |
| Modify | `tests/Unit/Support/Categories/CategoryDefaultsTest.php` |
| Modify | `tests/Feature/Categories/EnsureUserCategoriesTest.php` |
| Modify | `tests/Feature/Categories/CategoryCrudTest.php` — remove system-delete test |
| Modify | `tests/Feature/Transfers/CreateTransferTest.php` |
| Modify | `tests/Feature/Transfers/TransferGoalTest.php` |
| Modify | `tests/Feature/Transfers/TransfersUnlinkTest.php` |
| Modify | `tests/Feature/Imports/TransferMatcherAutoTest.php` |
| Modify | `tests/Feature/Budgets/MonthlyBudgetTest.php` |
| Create | `tests/Feature/Transfers/TransferCategoryDecouplingTest.php` — focused regression |
| Modify | `.docs/checklist.md` — note decoupling shipped |

---

### Task 1: Data migration — nullable `category_id` + remove „Oszczędności”

**Files:**
- Create: `database/migrations/2026_06_04_120000_decouple_transfer_categories.php`

- [ ] **Step 1: Create migration**

```php
<?php

declare(strict_types=1);

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Transaction::query()
            ->where(fn ($q) => $q->where('type', TransactionType::Transfer)->orWhereNotNull('transfer_id'))
            ->update(['category_id' => null]);

        Category::query()
            ->where('is_system', true)
            ->where('name', 'Oszczędności')
            ->orderBy('id')
            ->each(function (Category $savingsCategory): void {
                $userId = $savingsCategory->user_id;

                $firstExpenseId = Category::query()
                    ->where('user_id', $userId)
                    ->where('type', CategoryType::Expense)
                    ->whereKeyNot($savingsCategory->id)
                    ->ordered()
                    ->value('id');

                $firstIncomeId = Category::query()
                    ->where('user_id', $userId)
                    ->where('type', CategoryType::Income)
                    ->ordered()
                    ->value('id');

                if ($firstExpenseId === null || $firstIncomeId === null) {
                    return;
                }

                Transaction::query()
                    ->where('category_id', $savingsCategory->id)
                    ->whereNull('transfer_id')
                    ->whereNotIn('type', [TransactionType::Transfer])
                    ->where('amount', '<', 0)
                    ->update(['category_id' => $firstExpenseId]);

                Transaction::query()
                    ->where('category_id', $savingsCategory->id)
                    ->whereNull('transfer_id')
                    ->whereNotIn('type', [TransactionType::Transfer])
                    ->where('amount', '>=', 0)
                    ->update(['category_id' => $firstIncomeId]);

                CategoryMonthlyEstimate::query()->where('category_id', $savingsCategory->id)->delete();
                CategoryAnnualEstimate::query()->where('category_id', $savingsCategory->id)->delete();
                $savingsCategory->delete();
            });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `./vendor/bin/sail artisan migrate`

Expected: migration completes; transfer rows have `category_id = null`; no „Oszczędności” categories remain.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_04_120000_decouple_transfer_categories.php
git commit -m "refactor: migrate transfer category_id to null and remove Oszczędności category"
```

---

### Task 2: Remove „Oszczędności” from starter seed (TDD)

**Files:**
- Modify: `app/Support/Categories/CategoryDefaults.php`
- Modify: `tests/Unit/Support/Categories/CategoryDefaultsTest.php`
- Modify: `tests/Feature/Categories/EnsureUserCategoriesTest.php`

- [ ] **Step 1: Update failing unit test**

In `tests/Unit/Support/Categories/CategoryDefaultsTest.php`, replace the „Oszczędności” assertion with:

```php
test('starter rows do not include system savings category', function () {
    $rows = CategoryDefaults::starterRows();

    $savings = collect($rows)->first(fn ($r) => ($r['name'] ?? '') === 'Oszczędności');

    expect($savings)->toBeNull();
    expect(collect($rows)->where('is_system', true))->toBeEmpty();
});
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Categories/CategoryDefaultsTest.php`

- [ ] **Step 3: Remove row from `CategoryDefaults::starterRows()`**

Delete the line:

```php
['name' => 'Oszczędności', 'type' => CategoryType::Expense, 'icon' => 'piggy-bank', 'color' => '#10b981', 'sort_order' => 25, 'is_system' => true],
```

- [ ] **Step 4: Fix feature test**

In `tests/Feature/Categories/EnsureUserCategoriesTest.php`:

```php
expect(Category::where('user_id', $user->id)->where('name', 'Oszczędności')->exists())->toBeFalse();
```

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Categories/CategoryDefaultsTest.php tests/Feature/Categories/EnsureUserCategoriesTest.php`

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Support/Categories/CategoryDefaults.php tests/Unit/Support/Categories/CategoryDefaultsTest.php tests/Feature/Categories/EnsureUserCategoriesTest.php
git commit -m "refactor: remove Oszczędności from category starter set"
```

---

### Task 3: TestCase auto-category hook — skip transfers

**Files:**
- Modify: `tests/TestCase.php`
- Modify: `app/Support/Categories/DefaultCategoryId.php`

- [ ] **Step 1: Update `TestCase::setUp()` creating hook**

```php
Transaction::creating(function (Transaction $transaction): void {
    if ($transaction->category_id !== null || $transaction->user_id === null) {
        return;
    }

    if ($transaction->transfer_id !== null) {
        return;
    }

    $type = $transaction->type;
    if (! $type instanceof TransactionType) {
        $type = TransactionType::tryFrom((string) $type) ?? TransactionType::Expense;
    }

    if ($type === TransactionType::Transfer) {
        return;
    }

    // ... existing DefaultCategoryId lookup
});
```

- [ ] **Step 2: Remove Transfer branch from `DefaultCategoryId::for()`**

Delete the block that resolves system „Oszczędności” for `TransactionType::Transfer`; fall through to expense first-id only when needed for adjustment fallback.

- [ ] **Step 3: Commit**

```bash
git add tests/TestCase.php app/Support/Categories/DefaultCategoryId.php
git commit -m "refactor: stop auto-assigning category on transfer transactions"
```

---

### Task 4: Transfer create API — no `category_id` (TDD)

**Files:**
- Create: `tests/Feature/Transfers/TransferCategoryDecouplingTest.php`
- Modify: `app/Http/Requests/Transfers/StoreTransferRequest.php`
- Modify: `app/Actions/Transfers/CreateTransfer.php`
- Modify: `app/Http/Controllers/Transfers/TransferController.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(fn () => $this->seed(CurrencySeeder::class));

test('transfer create persists null category_id on both legs', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $from = Account::factory()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'type' => AccountType::Checking,
        'bank' => Bank::Cash,
        'current_balance' => 100,
    ]);
    $to = Account::factory()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'type' => AccountType::Checking,
        'bank' => Bank::Cash,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->post('/transfers', [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'date' => '04-06-2026',
        'amount' => 10,
    ])->assertSessionHasNoErrors();

    $legs = Transaction::query()->where('user_id', $user->id)->whereNotNull('transfer_id')->get();
    expect($legs)->toHaveCount(2);
    expect($legs->every(fn ($t) => $t->category_id === null))->toBeTrue();
    expect($legs->every(fn ($t) => $t->type === TransactionType::Transfer))->toBeTrue();
});

test('transfer create rejects category_id in payload', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $categoryId = defaultCategoryId($user);

    $from = Account::factory()->create(['user_id' => $user->id, 'currency_id' => $plnId, 'type' => AccountType::Checking, 'bank' => Bank::Cash]);
    $to = Account::factory()->create(['user_id' => $user->id, 'currency_id' => $plnId, 'type' => AccountType::Checking, 'bank' => Bank::Cash]);

    $this->actingAs($user)->post('/transfers', [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'date' => '04-06-2026',
        'amount' => 10,
        'category_id' => $categoryId,
    ])->assertSessionHasErrors('category_id');
});
```

- [ ] **Step 2: Run — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transfers/TransferCategoryDecouplingTest.php`

- [ ] **Step 3: Update `StoreTransferRequest`**

Remove `use ValidatesCategoryId` and `...$this->categoryIdRules()`. Add rule:

```php
'category_id' => ['prohibited'],
```

Update `@return` PHPDoc — remove `category_id` from validated array.

- [ ] **Step 4: Update `CreateTransfer`**

Remove `category_id` from validated array type and from both `Transaction::create()` calls. Set `'type' => TransactionType::Transfer` on both legs (replace `Expense` / `Income`).

- [ ] **Step 5: Update `TransferController::create()`**

Remove `ListCategories`, `CategoryResource`, `default_category_id`, and `categories` prop. Keep accounts + goals only.

- [ ] **Step 6: Update existing transfer tests**

In `tests/Feature/Transfers/CreateTransferTest.php` and `TransferGoalTest.php`: remove `'category_id' => …` from POST payloads; assert `category_id === null` on legs where relevant.

- [ ] **Step 7: Run transfer tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transfers/`

Expected: PASS

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Transfers/StoreTransferRequest.php app/Actions/Transfers/CreateTransfer.php app/Http/Controllers/Transfers/TransferController.php tests/Feature/Transfers/
git commit -m "refactor: drop category_id from transfer create flow"
```

---

### Task 5: Unlink transfer — fallback category (TDD)

**Files:**
- Modify: `app/Actions/Transfers/UnlinkTransfer.php`
- Modify: `tests/Feature/Transfers/TransfersUnlinkTest.php`

- [ ] **Step 1: Add assertion to unlink test**

After unlink, expect both legs have non-null `category_id` matching expense/income type from amount sign.

- [ ] **Step 2: Implement in `UnlinkTransfer`**

```php
use App\Support\Categories\DefaultCategoryId;

// inside foreach ($transactions as $transaction):
$newType = TransactionType::fromAmount((string) $transaction->amount);
$fallbackCategoryId = DefaultCategoryId::for($user, $newType);

$transaction->update([
    'transfer_id' => null,
    'type' => $newType,
    'transfer_match_status' => TransferMatchStatus::Rejected,
    'transfer_candidate_for_id' => null,
    'category_id' => $fallbackCategoryId,
    'goal_id' => null,
]);
```

- [ ] **Step 3: Run**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transfers/TransfersUnlinkTest.php tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php`

- [ ] **Step 4: Commit**

```bash
git commit -am "fix: assign fallback P&L category when unlinking transfer"
```

---

### Task 6: Import matcher + manual confirm — clear category (TDD)

**Files:**
- Modify: `app/Imports/TransferMatcher.php`
- Modify: `app/Actions/Transfers/ConfirmTransferCandidate.php`
- Modify: `tests/Feature/Imports/TransferMatcherAutoTest.php`

- [ ] **Step 1: Extend matcher test**

After auto-link assertions, add:

```php
expect($imported->category_id)->toBeNull();
expect($existing->category_id)->toBeNull();
```

- [ ] **Step 2: Update `TransferMatcher::autoLink()`**

```php
$transaction->update([
    'transfer_id' => $transferId,
    'type' => TransactionType::Transfer,
    'transfer_match_status' => TransferMatchStatus::Auto,
    'transfer_candidate_for_id' => null,
    'category_id' => null,
]);
```

- [ ] **Step 3: Update `ConfirmTransferCandidate`**

Same `'category_id' => null` in the `update()` array inside the foreach.

- [ ] **Step 4: Run import/matcher tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Imports/TransferMatcherAutoTest.php tests/Feature/Transfers/TransferCandidateConfirmRejectTest.php`

- [ ] **Step 5: Commit**

```bash
git commit -am "refactor: clear category_id when linking import transfer pairs"
```

---

### Task 7: Transaction list — expose goal on index (TDD)

**Files:**
- Modify: `app/Http/Resources/Transactions/TransactionResource.php`
- Modify: `app/Actions/Transactions/ListTransactions.php`
- Modify: `resources/js/pages/transactions/Index.vue`
- Modify: `resources/js/locales/pl.json`, `en.json`

- [ ] **Step 1: Add to `TransactionResource`**

```php
'goal_id' => $this->goal_id,
'goal' => $this->whenLoaded(
    'goal',
    fn () => $this->goal !== null
        ? ['id' => $this->goal->id, 'name' => $this->goal->name]
        : null,
),
```

- [ ] **Step 2: Eager-load in `ListTransactions::baseQuery()`**

```php
->with(['account.currency', 'currency', 'category', 'goal'])
```

- [ ] **Step 3: Update `Index.vue` category column**

For `tx.transfer_id`: show muted transfer label (`t('transactions.index.table.transfer')`) and optional goal name badge/text; hide `CategoryBadge` when no category.

Extend TS type:

```typescript
goal_id: number | null;
goal: { id: number; name: string } | null;
category_id: number | null;
```

- [ ] **Step 4: Add locale keys**

`pl.json`:

```json
"transactions.index.table.transfer": "Transfer wewnętrzny"
```

`en.json`:

```json
"transactions.index.table.transfer": "Internal transfer"
```

- [ ] **Step 5: Feature test (optional smoke)**

Extend `tests/Feature/Transactions/TransactionIndexTest.php` or create minimal assertion that index returns `goal` key when `goal_id` set.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Resources/Transactions/TransactionResource.php app/Actions/Transactions/ListTransactions.php resources/js/pages/transactions/Index.vue resources/js/locales/
git commit -m "feat: show goal instead of category on transfer rows in transaction index"
```

---

### Task 8: Transfer form UI — remove category picker

**Files:**
- Modify: `resources/js/pages/transfers/Create.vue`

- [ ] **Step 1: Remove category-related props and form fields**

- Drop `categories`, `default_category_id` from props.
- Remove `category_id` from `useForm` payload.
- Delete `FormField` block for `category_id` (lines ~312–340).
- Remove unused imports: `CategoryBadge`, `filterCategoriesByType`, `firstCategoryId`, `categoriesByIdMap` if no longer used.

- [ ] **Step 2: Manual smoke**

Run: `./vendor/bin/sail npm run build` (or verify in dev) — open `/transfers/create`, confirm no category field; ROR↔ROR transfer submits; ROR→Savings shows goal field.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/transfers/Create.vue
git commit -m "refactor: remove category picker from transfer form"
```

---

### Task 9: Category CRUD + budget tests cleanup

**Files:**
- Modify: `tests/Feature/Categories/CategoryCrudTest.php` — remove `cannot delete system savings category` test
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php` — goal metrics test: use `'category_id' => null` on transfer legs instead of savings category lookup

- [ ] **Step 1: Update `MonthlyBudgetTest` goal section**

Replace `$savingsCategory` lookup with `'category_id' => null` on all four transfer leg creates.

- [ ] **Step 2: Remove obsolete CategoryCrudTest**

Delete test `cannot delete system savings category`.

- [ ] **Step 3: Run affected suites**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Categories/ tests/Feature/Budgets/`

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git commit -am "test: align category and budget tests with transfer category decoupling"
```

---

### Task 10: Final verification + checklist

**Files:**
- Modify: `.docs/checklist.md`

- [ ] **Step 1: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 2: Run domain test filters**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transfers/ tests/Feature/Categories/ tests/Feature/Budgets/ tests/Feature/Imports/TransferMatcherAutoTest.php`

- [ ] **Step 3: Update checklist**

Add note under wave 2 UX: transfer category decoupling shipped (spec 2026-06-04).

- [ ] **Step 4: Commit**

```bash
git add .docs/checklist.md
git commit -m "docs: mark transfer category decoupling complete in checklist"
```

---

## Self-review (spec coverage)

| Spec requirement | Task |
|------------------|------|
| `category_id = null` on transfers | 1, 4 |
| Remove „Oszczędności” seed | 2 |
| Migration reassign + delete | 1 |
| Transfer form no category | 4, 8 |
| Unlink fallback category | 5 |
| Matcher clears category | 6 |
| List UI transfer + goal | 7 |
| FR-C2 validation (prohibited on transfer) | 4 |
| Adjustment still requires category | unchanged (existing StoreTransactionRequest) |

No placeholder steps remain.

---

## Execution handoff

**Plan complete and saved to `.docs/superpowers/plans/2026-06-04-transfer-category-decoupling.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — implement tasks in this session with checkpoints

**Which approach?**
