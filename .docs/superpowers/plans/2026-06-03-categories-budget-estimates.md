# Categories and budget estimates — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]` / `- [x]`) syntax for tracking.

**Goal:** Add user categories with annual/monthly estimates (szacunki), required `category_id` on transactions, category memory on import, and monthly/yearly budget views — per spec.

**Architecture:** New domains `Categories` and `Budgets` (Variant A). Extend `DescriptionMemoryRepository` with optional `category_id`. Reuse `ListTransactions` aggregation rules (`booked_at`, `transfer_id IS NULL`) in `ListYearlyBudget` / category actuals in `ListMonthlyBudget`. Pre-release migration backfills all transactions.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Typesense (optional), Sail for tests.

**Spec:** `.docs/superpowers/specs/2026-06-03-categories-budget-estimates-design.md`

**Suggested branch:** `feature/categories-budget-estimates` (from `develop`)

---

## File map (overview)

| Action | Path |
|--------|------|
| Modify | `.docs/prd.md` — §1, §3.2, §5, §6 (FR-C1–C8), §7, §10, index |
| Create | `database/migrations/..._create_categories_tables.php` |
| Create | `database/migrations/..._add_category_id_to_transactions.php` |
| Create | `app/Enums/CategoryType.php` |
| Create | `app/Models/Category.php`, `CategoryAnnualEstimate.php`, `CategoryMonthlyEstimate.php` |
| Create | `database/factories/CategoryFactory.php` (+ estimates factories if needed) |
| Create | `app/Policies/CategoryPolicy.php` |
| Create | `app/Actions/Categories/*`, `app/Actions/Budgets/*` |
| Create | `app/Support/Budgets/BudgetPeriod.php` (date bounds helper) |
| Create | `app/Support/Categories/CategoryDefaults.php` (starter list) |
| Create | `app/Http/Controllers/Categories/CategoryController.php` |
| Create | `app/Http/Controllers/Budgets/BudgetController.php` |
| Create | `app/Http/Requests/Categories/*`, `app/Http/Requests/Budgets/*` |
| Create | `app/Http/Resources/Categories/*`, `app/Http/Resources/Budgets/*` |
| Create | `routes/categories.php`, `routes/budgets.php` |
| Modify | `bootstrap/app.php` or `routes/web.php` — register route files |
| Modify | `app/Models/Transaction.php`, factories, Resources |
| Modify | `app/Actions/Transactions/StoreTransaction.php`, `UpdateTransaction.php` |
| Modify | `app/Actions/Transfers/CreateTransfer.php` |
| Modify | `app/Http/Requests/Transactions/StoreTransactionRequest.php`, `UpdateTransactionRequest.php` |
| Modify | `app/Http/Requests/Transfers/StoreTransferRequest.php` |
| Modify | `app/Imports/Workflow/CommitImport.php`, `EnrichImportRowDescription.php` |
| Modify | `app/Integrations/DescriptionMemory/*` (interface + Typesense + Null + SuggestedFields) |
| Modify | `app/Console/Commands/Typesense/Setup.php` — add `learned_category_id` to schema |
| Create | `resources/js/pages/budget/Monthly.vue`, `Yearly.vue` |
| Create | `resources/js/pages/categories/Index.vue` (or modal from Budget) |
| Modify | `resources/js/pages/transactions/Create.vue`, `Edit.vue` |
| Modify | `resources/js/pages/transfers/Create.vue` |
| Modify | `resources/js/components/AppSidebar.vue` |
| Modify | `resources/js/locales/pl.json`, `en.json` |
| Create | `tests/Feature/Categories/*`, `tests/Feature/Budgets/*` |
| Modify | `tests/Feature/Transactions/*`, `tests/Feature/Imports/*` |
| Modify | `.docs/checklist.md` — new section post-MVP |

---

## Starter categories (product constant)

Seed per user on first `EnsureUserCategories` (Action or listener on register):

**Expense (`sort_order` 10–80):** Jedzenie (10), Transport (20), Mieszkanie (30), Zdrowie (40), Rozrywka (50), **Oszczędności** (60, `is_system=true`), Inne (90)

**Income:** Pensja (10), Inne przychody (90)

Import fallback: first row where `type` matches transaction sign, ordered by `sort_order`.

---

### Task 0: Update PRD (documentation)

**Files:**
- Modify: `.docs/prd.md`

- [x] **Step 1:** Remove categorization/budgeting from §3.2; add dictionary entries §1; extend §5 models; add §6.7 with FR-C1–C8 (copy from spec); update §7 navigation; §10 post-MVP; FR index table.

- [x] **Step 2:** Commit (optional, user-requested only)

```bash
git add .docs/prd.md
git commit -m "docs: add categories and budget estimates to PRD"
```

---

### Task 1: Migrations and models (Categories)

**Files:**
- Create: `app/Enums/CategoryType.php`
- Create: `database/migrations/2026_06_03_100000_create_categories_tables.php`
- Create: `app/Models/Category.php`, `CategoryAnnualEstimate.php`, `CategoryMonthlyEstimate.php`
- Create: `database/factories/CategoryFactory.php`

- [x] **Step 1: Write migration**

```php
// categories: id, user_id, name, type (string), sort_order (int), is_system (bool), timestamps
// unique(user_id, name) optional
// category_annual_estimates: category_id, year (smallint), amount (decimal 12,2 nullable)
// unique(category_id, year)
// category_monthly_estimates: category_id, year, month (tinyint 1-12), amount (decimal nullable)
// unique(category_id, year, month)
```

- [x] **Step 2: Create enum and models with relationships**

`Category` belongsTo User; hasMany estimates; scope `forUser`, `ordered`.

- [x] **Step 3: Run migration**

```bash
./vendor/bin/sail artisan migrate --no-interaction
```

- [x] **Step 4: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 2: Category policy + seed Action (TDD)

**Files:**
- Create: `app/Policies/CategoryPolicy.php`
- Create: `app/Support/Categories/CategoryDefaults.php`
- Create: `app/Actions/Categories/EnsureUserCategories.php`
- Create: `tests/Feature/Categories/EnsureUserCategoriesTest.php`

- [x] **Step 1: Failing test**

```php
test('new user receives starter categories on ensure', function () {
    $user = User::factory()->create();
    app(EnsureUserCategories::class)->handle($user);
    expect(Category::where('user_id', $user->id)->count())->toBeGreaterThan(5);
    expect(Category::where('user_id', $user->id)->where('is_system', true)->where('name', 'Oszczędności')->exists())->toBeTrue();
});
```

- [x] **Step 2: Run test — FAIL**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Categories/EnsureUserCategoriesTest.php
```

- [x] **Step 3: Implement `EnsureUserCategories`** — idempotent insert starter rows.

- [x] **Step 4: Register** — call from `RegisteredUserController` after user create (or first login middleware — pick one, document in code).

- [x] **Step 5: PASS test**

- [x] **Step 6: Commit**

```bash
git add app/Actions/Categories app/Support/Categories app/Policies/CategoryPolicy.php tests/Feature/Categories database/
git commit -m "feat: seed starter categories for new users"
```

---

### Task 3: Categories CRUD backend (TDD)

**Files:**
- Create: `app/Actions/Categories/ListCategories.php`, `StoreCategory.php`, `UpdateCategory.php`, `DeleteCategory.php`
- Create: `app/Http/Controllers/Categories/CategoryController.php`
- Create: `app/Http/Requests/Categories/StoreCategoryRequest.php`, `UpdateCategoryRequest.php`
- Create: `app/Http/Resources/Categories/CategoryResource.php`
- Create: `routes/categories.php`
- Create: `tests/Feature/Categories/CategoryCrudTest.php`

- [x] **Step 1: Tests** — list isolated per user; cannot delete category with transactions; cannot delete system „Oszczędności”; cannot change type when txs exist.

- [x] **Step 2: Implement Actions + controller** — `index`, `store`, `update`, `destroy`; authorize via Policy.

- [x] **Step 3: Wire routes** in `bootstrap/app.php` (match `routes/accounts.php` pattern).

- [x] **Step 4: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Categories/
```

- [x] **Step 5: Commit**

---

### Task 4: Annual and monthly estimates API (TDD)

**Files:**
- Create: `app/Actions/Categories/SaveAnnualEstimate.php`, `SaveMonthlyEstimate.php`
- Create: `app/Http/Requests/Categories/SaveAnnualEstimateRequest.php`, `SaveMonthlyEstimateRequest.php`
- Extend: `CategoryController` — `saveAnnualEstimate`, `saveMonthlyEstimate`
- Create: `tests/Feature/Categories/CategoryEstimatesTest.php`

- [x] **Step 1: Tests** — save annual 12000; save monthly override 1500 for month 3; amounts >= 0 validation.

- [x] **Step 2: Implement upsert** on estimate tables scoped to user's category.

- [x] **Step 3: PASS + commit**

---

### Task 5: Add `category_id` to transactions (migration + backfill)

**Files:**
- Create: `database/migrations/2026_06_03_110000_add_category_id_to_transactions.php`
- Modify: `app/Models/Transaction.php`, `database/factories/TransactionFactory.php`

- [x] **Step 1: Migration**

Add nullable `category_id` FK → `categories.id` first; backfill in same migration using DB::table:

```php
// For each user: EnsureUserCategories if needed
// expense txs (amount < 0 or type expense): first expense category by sort_order
// income txs: first income category
// transfer/adjustment: use Oszczędności or Inne per type rules in spec
```

Then `category_id` NOT NULL.

- [x] **Step 2: Update factory** — always set `category_id` via Category::factory().

- [x] **Step 3: Migrate**

```bash
./vendor/bin/sail artisan migrate --no-interaction
```

- [x] **Step 4: Commit**

---

### Task 6: Require category on manual transactions (TDD)

**Files:**
- Modify: `app/Http/Requests/Transactions/StoreTransactionRequest.php`, `UpdateTransactionRequest.php`
- Modify: `app/Actions/Transactions/StoreTransaction.php`, `UpdateTransaction.php`
- Modify: `app/Http/Resources/Transactions/TransactionResource.php`, `TransactionEditResource.php`
- Modify: `tests/Feature/Transactions/TransactionStoreTest.php` (and update/create siblings)

- [x] **Step 1: Add validation rules**

```php
'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', $this->user()->id)],
```

Add custom rule or `withValidator` hook: category `type` must match transaction economic type from amount.

- [x] **Step 2: Persist `category_id` in Store/Update actions.**

- [x] **Step 3: Extend failing tests** — 422 without category; 422 when expense uses income category.

- [x] **Step 4: Fix all Transaction feature tests** — pass `category_id` in payloads (use factory).

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Transactions/
```

- [x] **Step 5: Commit**

---

### Task 7: Category on transfers (TDD)

**Files:**
- Modify: `app/Http/Requests/Transfers/StoreTransferRequest.php`
- Modify: `app/Actions/Transfers/CreateTransfer.php`
- Modify: `tests/Feature/Transfers/TransferStoreTest.php`

- [x] **Step 1: Test** — both legs share `category_id` from request; default allowed in form later.

- [x] **Step 2: Add `category_id` to StoreTransferRequest** (required).

- [x] **Step 3: Set same `category_id` on both inserted transactions.**

- [x] **Step 4: PASS tests + commit**

---

### Task 8: Category memory (Typesense + remember on edit)

**Files:**
- Modify: `app/Integrations/DescriptionMemory/DescriptionMemoryRepository.php`
- Modify: `SuggestedFields.php` — add `public ?int $categoryId`
- Modify: `TypesenseDescriptionMemoryRepository.php`, `NullDescriptionMemoryRepository.php`
- Modify: `app/Console/Commands/Typesense/Setup.php` — field `learned_category_id` (int32, optional)
- Modify: `app/Actions/Transactions/UpdateTransaction.php` — pass `category_id` to `remember()`
- Create: `tests/Feature/Imports/CategoryMemoryTest.php`

- [x] **Step 1: Extend interface**

```php
public function remember(..., ?int $categoryId = null): void;
// suggest returns categoryId in SuggestedFields when document has learned_category_id
```

- [x] **Step 2: Test remember + suggest round-trip** (mock Typesense or use existing isolation test patterns).

- [x] **Step 3: Implement upsert/search fields.**

- [x] **Step 4: Run Typesense setup in dev docs** — `./vendor/bin/sail artisan typesense:setup` (may need collection recreate in dev).

- [x] **Step 5: Commit**

---

### Task 9: Import category assignment (TDD)

**Files:**
- Create: `app/Actions/Categories/ResolveCategoryForImportRow.php`
- Modify: `app/Imports/Workflow/CommitImport.php`
- Modify: `tests/Feature/Imports/CommitImportJobTest.php`

- [x] **Step 1: Implement resolver**

```php
// 1) suggest() from memory → category_id if valid for user+type
// 2) else first category by type sort_order (EnsureUserCategories must have run)
```

- [x] **Step 2: Test** — memory hit assigns category; miss assigns first expense category; mBank Kategoria column still not mapped.

- [x] **Step 3: Set `category_id` on bulk insert array** in CommitImport.

- [x] **Step 4: Emit telemetry** `category_memory_hit` / `category_memory_miss` (optional listeners).

- [x] **Step 5: PASS import tests + commit**

---

### Task 10: ListYearlyBudget Action (TDD)

**Files:**
- Create: `app/Support/Budgets/BudgetPeriod.php`
- Create: `app/Actions/Budgets/ListYearlyBudget.php`
- Create: `app/Http/Requests/Budgets/YearlyBudgetRequest.php`
- Create: `tests/Feature/Budgets/YearlyBudgetTest.php`

- [x] **Step 1: Test fixtures** — user, categories, txs in year with/without `transfer_id`.

- [x] **Step 2: Assert yearly response shape**

Per category: `category_id`, `name`, `type`, `annual_estimate`, `actual_income`, `actual_expense`, `difference` (based on type).

Actuals query (mirror ListTransactions):

```php
Transaction::query()
    ->whereBelongsTo($user)
    ->whereNull('transfer_id')
    ->whereRaw('YEAR(COALESCE(booked_at, date)) = ?', [$year])
    ->selectRaw('category_id, ...')
    ->groupBy('category_id');
```

- [x] **Step 3: Implement Action** — `handle(YearlyBudgetRequest): void` + getters.

- [x] **Step 4: PASS**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/YearlyBudgetTest.php
```

---

### Task 11: ListMonthlyBudget Action + transfers section (TDD)

**Files:**
- Create: `app/Actions/Budgets/ListMonthlyBudget.php`
- Create: `app/Http/Requests/Budgets/MonthlyBudgetRequest.php`
- Create: `tests/Feature/Budgets/MonthlyBudgetTest.php`

- [x] **Step 1: Test category rows** — monthly estimate display: override amount OR `annual/12` when no row.

- [x] **Step 2: Test transfers section**

```php
// plan: monthly estimate for system Oszczędności category
// actual: sum of transfer legs in month where account.type = Savings (credit side)
```

- [x] **Step 3: Implement** — separate getter `getTransfersSummary()`.

- [x] **Step 4: PASS monthly tests**

---

### Task 12: Budget + Categories HTTP + Inertia pages

**Files:**
- Create: `app/Http/Controllers/Budgets/BudgetController.php`
- Create: `app/Http/Resources/Budgets/MonthlyBudgetResource.php`, `YearlyBudgetResource.php`
- Create: `routes/budgets.php`
- Create: `resources/js/pages/budget/Monthly.vue`, `Yearly.vue`
- Create: `resources/js/pages/categories/Index.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/locales/pl.json`

- [x] **Step 1: Controller** — `monthly(MonthlyBudgetRequest)`, `yearly(YearlyBudgetRequest)`; map Resources.

- [x] **Step 2: Vue Monthly** — month/year picker; tables expense/income; transfers section; inline edit monthly estimate (PATCH).

- [x] **Step 3: Vue Yearly** — year picker; table plan vs actual; no transfers.

- [x] **Step 4: Categories index** — CRUD + annual estimate form + reorder (`sort_order`).

- [x] **Step 5: Sidebar** — add „Budżet” → `route('budget.monthly')`.

- [x] **Step 6: Smoke** — login → `/budget/monthly` no console errors.

---

### Task 13: Transaction forms — category select (Vue)

**Files:**
- Modify: `app/Http/Controllers/Transactions/TransactionController.php` — pass `categories` on create/edit
- Modify: `resources/js/pages/transactions/Create.vue`, `Edit.vue`
- Modify: `resources/js/pages/transfers/Create.vue`

- [x] **Step 1: Pass filtered categories** (`CategoryResource`) split by type in controller `create`/`edit`.

- [x] **Step 2: Add required `<Select>`** — filter options by inferred type from amount sign on create.

- [x] **Step 3: Transfer create** — category select, default „Oszczędności”.

- [x] **Step 4: Manual test + optional FR-C8** — show category column on Index (Should).

---

### Task 14: Full regression + docs

- [x] **Step 1: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [x] **Step 2: Full test suite**

```bash
./vendor/bin/sail artisan test --compact
```

- [x] **Step 3: PHPStan** (if types touched heavily)

```bash
./vendor/bin/phpstan analyse
```

- [x] **Step 4: Update `.docs/checklist.md`** — new section for categories/budget FR-C*.

- [x] **Step 5: Final commit on feature branch**

---

## Plan self-review

| Spec requirement | Task |
|------------------|------|
| FR-C1 starter + CRUD | 2, 3 |
| FR-C2 required category | 5, 6, 7, 13 |
| FR-C3 annual estimates | 4 |
| FR-C4 monthly overrides | 4, 11 |
| FR-C5 monthly view + transfers | 11, 12 |
| FR-C6 yearly view | 10, 12 |
| FR-C7 import memory | 8, 9 |
| FR-C8 index filter | 13 (optional) |
| Pre-release backfill | 5 |
| PRD update | 0 |

No TBD placeholders. Typesense schema change called out in Task 8.

---

## Execution handoff

Plan complete and saved to `.docs/superpowers/plans/2026-06-03-categories-budget-estimates.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — implement task-by-task in this session with checkpoints  

Which approach do you prefer?
