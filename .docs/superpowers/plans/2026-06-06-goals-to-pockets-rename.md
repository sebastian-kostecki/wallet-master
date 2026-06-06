# Goals → Pockets rename — plan implementacji

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pełny rename domeny Goals → Pockets (DB, PHP, Vue, i18n, PRD) bez zmiany logiki kopert oszczędnościowych; UI PL **Kieszenie**, EN **Envelopes**.

**Architecture:** Jedna migracja rename (`goals` → `pockets`, `goal_id` → `pocket_id`); mechaniczny rename plików i namespace’ów Variant A (`Pockets`); brak redirectów i aliasów (pre-prod). Logika z speców `2026-06-04` / `2026-06-05` — bez zmian semantycznych.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Sail (`./vendor/bin/sail artisan test`).

**Spec:** `.docs/superpowers/specs/2026-06-06-goals-to-pockets-rename-design.md`

**Branch:** `improvement/goals-to-pockets` (od `develop`)

---

## Mapa plików

### Usunąć (po przeniesieniu)

| Path |
|------|
| `routes/goals.php` |
| `app/Models/Goal.php` |
| `app/Enums/GoalPlanningMode.php` |
| `app/Policies/GoalPolicy.php` |
| `app/Http/Controllers/Goals/*` |
| `app/Actions/Goals/*` |
| `app/Http/Requests/Goals/*` |
| `app/Http/Resources/Goals/*` |
| `app/Data/Goals/*` |
| `app/Support/Goals/*` |
| `app/Http/Requests/Concerns/ValidatesGoalId.php` |
| `database/factories/GoalFactory.php` |
| `resources/js/pages/goals/*` |
| `resources/js/components/goals/*` |
| `tests/Feature/Goals/*` |
| `tests/Feature/Transfers/TransferGoalTest.php` |
| `tests/Feature/Transactions/TransactionGoalTest.php` |
| `tests/Unit/Support/Goals/*` |

### Utworzyć (rename 1:1)

| Było | Będzie |
|------|--------|
| `Goal` | `app/Models/Pocket.php` |
| `GoalPlanningMode` | `app/Enums/PocketPlanningMode.php` |
| `GoalPolicy` | `app/Policies/PocketPolicy.php` |
| `GoalController` | `app/Http/Controllers/Pockets/PocketController.php` |
| `ListGoals`, `StoreGoal`, … | `app/Actions/Pockets/ListPockets.php`, … |
| `StoreGoalRequest`, … | `app/Http/Requests/Pockets/*` |
| `GoalResource` | `app/Http/Resources/Pockets/PocketResource.php` |
| `GoalFormOptions` | `app/Data/Pockets/PocketFormOptions.php` |
| `GoalBalance`, … | `app/Support/Pockets/PocketBalance.php`, … |
| `ValidatesGoalId` | `app/Http/Requests/Concerns/ValidatesPocketId.php` |
| `GoalFactory` | `database/factories/PocketFactory.php` |
| `routes/goals.php` | `routes/pockets.php` |
| Vue pages/components | `resources/js/pages/pockets/*`, `components/pockets/*` |
| Feature tests | `tests/Feature/Pockets/*`, `TransferPocketTest.php`, … |

### Zmodyfikować (referencje)

| Path | Zmiana |
|------|--------|
| `routes/web.php` | `require goals.php` → `pockets.php` |
| `app/Models/Transaction.php` | `goal_id` → `pocket_id`, relacja `pocket()` |
| `app/Actions/Transfers/CreateTransfer.php` | `pocket_id` |
| `app/Actions/Transfers/UnlinkTransfer.php` | `pocket_id` |
| `app/Actions/Transactions/StoreTransaction.php` | `pocket_id` |
| `app/Actions/Transactions/UpdateTransaction.php` | `pocket_id` |
| `app/Http/Requests/Transfers/StoreTransferRequest.php` | trait `ValidatesPocketId` |
| `app/Http/Requests/Transactions/*` | trait `ValidatesPocketId` |
| `app/Http/Resources/Transactions/*` | `pocket_id` |
| `app/Http/Controllers/Transfers/TransferController.php` | prop `pockets` |
| `app/Http/Controllers/Transactions/TransactionController.php` | prop `pockets` |
| `app/Actions/Budgets/ListMonthlyBudget.php` | `Pocket`, `pocket_rows`, `getPocketRows()` |
| `app/Http/Controllers/Budgets/BudgetController.php` | `pocket_rows` |
| `database/factories/TransactionFactory.php` | `pocket_id`, state `forPocket()` |
| `resources/js/pages/budget/Monthly.vue` | `pocket_rows`, `PocketBadge` |
| `resources/js/pages/transfers/Create.vue` | `pockets`, `pocket_id` |
| `resources/js/pages/transactions/*.vue` | `pockets`, `pocket_id` |
| `resources/js/components/AppSidebar.vue` | link `pockets.index`, label i18n |
| `resources/js/pages/Dashboard.vue`, `Welcome.vue` | copy |
| `resources/js/locales/pl.json`, `en.json` | sekcja `pockets` (usuń `goals`) |
| `.docs/prd.md` | FR-P*, słownik, telemetria |
| `.docs/checklist.md` | §19 Pockets |

### Migracja DB

| Path |
|------|
| `database/migrations/2026_06_06_100000_rename_goals_to_pockets.php` |

---

### Task 0: Branch

- [ ] **Step 1: Utwórz branch**

```bash
git checkout develop
git pull
git checkout -b improvement/goals-to-pockets
```

---

### Task 1: Migracja DB

**Files:**
- Create: `database/migrations/2026_06_06_100000_rename_goals_to_pockets.php`
- Create: `tests/Feature/Pockets/PocketRenameMigrationTest.php`

- [ ] **Step 1: Test migracji**

```php
<?php

use App\Models\Pocket;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('rename migration uses pockets table and pocket_id on transactions', function () {
    expect(Schema::hasTable('pockets'))->toBeTrue();
    expect(Schema::hasTable('goals'))->toBeFalse();
    expect(Schema::hasColumn('transactions', 'pocket_id'))->toBeTrue();
    expect(Schema::hasColumn('transactions', 'goal_id'))->toBeFalse();
});

test('existing goal rows survive rename as pockets', function () {
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'name' => 'Wakacje']);

    expect(Pocket::query()->where('name', 'Wakacje')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Uruchom test — FAIL**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketRenameMigrationTest.php
```

- [ ] **Step 3: Migracja**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('goals') && ! Schema::hasTable('pockets')) {
            Schema::rename('goals', 'pockets');
        }

        if (Schema::hasColumn('transactions', 'goal_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['goal_id']);
                $table->dropIndex(['user_id', 'goal_id', 'booked_at']);
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('goal_id', 'pocket_id');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('pocket_id')->references('id')->on('pockets')->nullOnDelete();
                $table->index(['user_id', 'pocket_id', 'booked_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'pocket_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['pocket_id']);
                $table->dropIndex(['user_id', 'pocket_id', 'booked_at']);
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('pocket_id', 'goal_id');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('goal_id')->references('id')->on('goals')->nullOnDelete();
                $table->index(['user_id', 'goal_id', 'booked_at']);
            });
        }

        if (Schema::hasTable('pockets') && ! Schema::hasTable('goals')) {
            Schema::rename('pockets', 'goals');
        }
    }
};
```

- [ ] **Step 4: Uruchom migrację i test**

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketRenameMigrationTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_06_100000_rename_goals_to_pockets.php tests/Feature/Pockets/PocketRenameMigrationTest.php
git commit -m "refactor: rename goals table to pockets"
```

---

### Task 2: Model Pocket + enum + factory

**Files:**
- Create: `app/Models/Pocket.php` (z `Goal.php`, rename klasa/pola)
- Create: `app/Enums/PocketPlanningMode.php`
- Create: `database/factories/PocketFactory.php`
- Delete: `app/Models/Goal.php`, `GoalPlanningMode.php`, `GoalFactory.php`

- [ ] **Step 1: `Pocket` model**

Skopiuj `Goal.php` → `Pocket.php`:
- namespace/class `Pocket`
- `hasLinkedTransactions()` sprawdza kolumnę `pocket_id` na `transactions`
- relacja `transactions(): HasMany`
- metody `forUser`, `ordered`, `scope` bez zmian logiki

- [ ] **Step 2: `PocketPlanningMode` enum** — case names bez zmian (`Monthly`, `ByDate`), rename enum class.

- [ ] **Step 3: `PocketFactory`** — `$model = Pocket::class`, `PocketPlanningMode`.

- [ ] **Step 4: Commit**

```bash
git add app/Models/Pocket.php app/Enums/PocketPlanningMode.php database/factories/PocketFactory.php
git rm app/Models/Goal.php app/Enums/GoalPlanningMode.php database/factories/GoalFactory.php
git commit -m "refactor: add Pocket model and factory"
```

---

### Task 3: Support/Pockets

**Files:**
- Create: `app/Support/Pockets/PocketBalance.php`
- Create: `app/Support/Pockets/PocketPlanningProjection.php`
- Create: `app/Support/Pockets/PocketTransactionMetrics.php`
- Create: `tests/Unit/Support/Pockets/PocketBalanceTest.php`
- Create: `tests/Unit/Support/Pockets/PocketPlanningProjectionTest.php`
- Delete: `app/Support/Goals/*`, `tests/Unit/Support/Goals/*`

- [ ] **Step 1: Rename klas** — zamień `Goal` → `Pocket`, `goal_id` → `pocket_id`, namespace `Support\Pockets`.

- [ ] **Step 2: Uruchom testy unit**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/Support/Pockets/
```

- [ ] **Step 3: Commit**

```bash
git commit -m "refactor: move goal balance logic to Support/Pockets"
```

---

### Task 4: Actions + Policy + ValidatesPocketId

**Files:**
- Create: `app/Actions/Pockets/*` (6 plików z Goals)
- Create: `app/Policies/PocketPolicy.php`
- Create: `app/Http/Requests/Concerns/ValidatesPocketId.php`
- Delete: `app/Actions/Goals/*`, `GoalPolicy.php`, `ValidatesGoalId.php`

- [ ] **Step 1: Actions** — rename + telemetria:

| Event było | Event będzie |
|------------|--------------|
| `goal_created` | `pocket_created` |
| `goal_updated` | `pocket_updated` |
| `goal_archived` | `pocket_archived` |
| `goal_unarchived` | `pocket_unarchived` |

Payload: `['pocket_id' => $pocket->id]`.

- [ ] **Step 2: `ValidatesPocketId`** — reguły na `pocket_id`; komunikat: `'Pocket currency must match the account currency.'`; model `Pocket`.

- [ ] **Step 3: `PocketPolicy`** — jak `GoalPolicy`, model `Pocket`.

- [ ] **Step 4: `DeleteGoal` → `DeletePocket`** — klucz błędu walidacji `'pocket'`, message: `'Cannot delete pocket with linked transactions.'`

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor: add Pockets actions and validation concern"
```

---

### Task 5: HTTP layer (Requests, Resources, Data, Controller, Routes)

**Files:**
- Create: `app/Http/Requests/Pockets/*`
- Create: `app/Http/Resources/Pockets/PocketResource.php`
- Create: `app/Data/Pockets/PocketFormOptions.php`
- Create: `app/Http/Controllers/Pockets/PocketController.php`
- Create: `routes/pockets.php`
- Modify: `routes/web.php`
- Delete: odpowiedniki Goals

- [ ] **Step 1: `routes/pockets.php`**

```php
<?php

use App\Http\Controllers\Pockets\PocketController;
use App\Models\Pocket;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('pocket', fn (string $value) => Pocket::query()->findOrFail($value));

    Route::patch('pockets/reorder', [PocketController::class, 'reorder'])->name('pockets.reorder');

    Route::resource('pockets', PocketController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
});
```

- [ ] **Step 2: `PocketController`** — `authorizeResource(Pocket::class, 'pocket')`; Inertia `pockets/Index`, `pockets/Create`, `pockets/Edit`; props `pockets`; redirect `route('pockets.index')`; toast keys `pockets.toast.*`.

- [ ] **Step 3: `PocketResource`** — shape jak GoalResource, klucze bez zmian semantycznych.

- [ ] **Step 4: `routes/web.php`** — `require __DIR__.'/pockets.php';`, usuń goals.

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor: add Pockets HTTP layer and routes"
```

---

### Task 6: Transaction + Transfer integration

**Files:**
- Modify: `app/Models/Transaction.php`
- Modify: `app/Actions/Transfers/CreateTransfer.php`, `UnlinkTransfer.php`
- Modify: `app/Actions/Transactions/StoreTransaction.php`, `UpdateTransaction.php`
- Modify: `app/Http/Requests/Transfers/StoreTransferRequest.php`
- Modify: `app/Http/Requests/Transactions/StoreTransactionRequest.php`, `UpdateTransactionRequest.php`
- Modify: `app/Http/Resources/Transactions/TransactionResource.php`, `TransactionEditResource.php`
- Modify: `app/Http/Controllers/Transfers/TransferController.php`
- Modify: `app/Http/Controllers/Transactions/TransactionController.php`
- Modify: `database/factories/TransactionFactory.php`

- [ ] **Step 1: Transaction model** — `$fillable`/`casts`/`pocket()` BelongsTo `Pocket`.

- [ ] **Step 2: Actions** — wszędzie `pocket_id` zamiast `goal_id`.

- [ ] **Step 3: Controllers** — lista `Pocket::query()->forUser(...)` jako prop `pockets`.

- [ ] **Step 4: Commit**

```bash
git commit -m "refactor: wire pocket_id through transactions and transfers"
```

---

### Task 7: Budget monthly pocket_rows

**Files:**
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php`
- Modify: `app/Http/Controllers/Budgets/BudgetController.php`
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php`

- [ ] **Step 1: `ListMonthlyBudget`** — `Pocket::query()`, `buildPocketRows()`, klucz `pocket_id`, getter `getPocketRows()`.

- [ ] **Step 2: Controller** — prop `pocket_rows`.

- [ ] **Step 3: Test** — assert `pocket_rows`, `pocket_id`.

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php
```

- [ ] **Step 4: Commit**

```bash
git commit -m "refactor: rename goal_rows to pocket_rows in monthly budget"
```

---

### Task 8: Feature tests Pockets

**Files:**
- Create: `tests/Feature/Pockets/*` (rename z Goals)
- Create: `tests/Feature/Transfers/TransferPocketTest.php`
- Create: `tests/Feature/Transactions/TransactionPocketTest.php`
- Delete: stare pliki Goal*

- [ ] **Step 1: Rename testów** — global replace w plikach:
  - `Goal` → `Pocket`
  - `goal` → `pocket` (route params)
  - `goals.` → `pockets.` (routes)
  - `goal_id` → `pocket_id`
  - `goal_rows` → `pocket_rows`
  - nazwy testów opisowe, np. `'transfer to savings account requires pocket_id'`

- [ ] **Step 2: Uruchom suite Pockets**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Pockets/ tests/Feature/Transfers/TransferPocketTest.php tests/Feature/Transactions/TransactionPocketTest.php
```

Expected: PASS

- [ ] **Step 3: Commit**

```bash
git commit -m "test: rename goal feature tests to pockets"
```

---

### Task 9: Frontend Vue

**Files:**
- Create: `resources/js/pages/pockets/Index.vue`, `Create.vue`, `Edit.vue`
- Create: `resources/js/components/pockets/PocketBadge.vue`, `PocketProgressBar.vue`, `modals/PocketArchiveDialog.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/pages/budget/Monthly.vue`
- Modify: `resources/js/pages/transfers/Create.vue`
- Modify: `resources/js/pages/transactions/Index.vue`, `Create.vue`, `Edit.vue`
- Modify: `resources/js/pages/Dashboard.vue`, `Welcome.vue`
- Modify: `resources/js/layouts/auth/AuthSimpleLayout.vue` (jeśli jest copy goals)
- Delete: `resources/js/pages/goals/*`, `components/goals/*`

- [ ] **Step 1: Rename komponentów** — imports `@/components/pockets/*`, props `pockets`, `pocket_id`, `route('pockets.*')`.

- [ ] **Step 2: `Monthly.vue`** — typ `PocketRow`, prop `pocket_rows`, `PocketBadge`.

- [ ] **Step 3: Build frontend (opcjonalnie)**

```bash
./vendor/bin/sail npm run build
```

- [ ] **Step 4: Commit**

```bash
git commit -m "refactor: rename goals UI to pockets pages and components"
```

---

### Task 10: i18n PL / EN

**Files:**
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Sekcja `pockets`** (usuń root `goals`)

Przykładowe klucze PL:

```json
"pockets": {
  "index": {
    "title": "Kieszenie",
    "add": "Dodaj kieszeń",
    "empty": "Nie masz jeszcze żadnych kieszeni.",
    "completed": "Ukończona"
  },
  "toast": {
    "created": "Kieszeń dodana.",
    "updated": "Kieszeń zaktualizowana.",
    "deleted": "Kieszeń usunięta."
  }
}
```

EN:

```json
"pockets": {
  "index": {
    "title": "Envelopes",
    "add": "Add envelope",
    "empty": "No envelopes yet.",
    "completed": "Complete"
  }
}
```

- [ ] **Step 2: Sidebar** — `nav.pockets`: PL „Kieszenie”, EN „Envelopes”.

- [ ] **Step 3: Budget** — `budget.monthly.pockets_section`: PL „Kieszenie oszczędnościowe”, EN „Savings envelopes”; `manage_pockets`: PL „Zarządzaj kieszeniami”, EN „Manage envelopes”.

- [ ] **Step 4: Transfer/transactions** — `pocket` / `pocketPlaceholder`: PL „Kieszeń”, EN „Envelope”.

- [ ] **Step 5: Commit**

```bash
git commit -m "i18n: replace goals copy with pockets (Kieszenie / Envelopes)"
```

---

### Task 11: PRD + checklist

**Files:**
- Modify: `.docs/prd.md`
- Modify: `.docs/checklist.md`

- [ ] **Step 1: PRD** — zamień FR-G1–G5 → FR-P1–P5; słownik „Kieszenie (pocket)”; `pocket_id`; telemetria `pocket_*`; sidebar „Kieszenie”; ścieżki `/pockets`; indeks FR.

- [ ] **Step 2: Checklist §19** — tytuł „Pockets (Kieszenie)”, ścieżki plików, `[plan goals-to-pockets]`.

- [ ] **Step 3: Commit**

```bash
git commit -m "docs: update PRD and checklist for pockets rename"
```

---

### Task 12: Pint, grep gate, pełny test

- [ ] **Step 1: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2: Grep gate** — zero trafień w kodzie aplikacji:

```bash
rg -i '\bgoal' app/ resources/js routes/ tests/ database/factories database/migrations/2026_06_06_100000_rename_goals_to_pockets.php --glob '!*.md'
```

Dozwolone wyjątki: **stare migracje** (`2026_06_04_*`, `2026_06_05_*`) mogą nadal wspominać `goals` w historii `up()`/`down()` — nie edytuj ich, jeśli migracja rename jest ostatnim krokiem.

- [ ] **Step 3: Pełny test (scoped + regresja)**

```bash
./vendor/bin/sail artisan test --compact --filter=Pocket
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/ tests/Feature/Transfers/ tests/Feature/Transactions/
```

- [ ] **Step 4: Commit końcowy (jeśli pint zmienił pliki)**

```bash
git add -A
git commit -m "chore: pint and verify goals rename complete"
```

---

## Self-review (spec coverage)

| Wymaganie spec | Task |
|----------------|------|
| Migracja `pockets` + `pocket_id` | Task 1 |
| Model Pocket | Task 2 |
| Support metrics | Task 3 |
| Actions + telemetria | Task 4 |
| Routes `/pockets` | Task 5 |
| FR-P3/P4 transfer/transaction | Task 6 |
| `pocket_rows` budżet | Task 7 |
| Testy | Task 8 |
| Vue + sidebar | Task 9 |
| i18n Kieszenie/Envelopes | Task 10 |
| PRD + checklist | Task 11 |
| Brak backward compat | brak redirectów w Tasks |
| Grep gate | Task 12 |

---

## Uwagi implementacyjne

1. **Kolejność:** Task 1 → 2 → 3 → 4 → 5 przed testami feature; Task 8 można zacząć równolegle po Task 5 jeśli routes działają.
2. **`MigrateLegacySavingsEstimate`:** przenieś do `Actions/Pockets/MigrateLegacySavingsEstimate.php`, tworzy `Pocket`.
3. **Laravel route model binding:** param `{pocket}` nie `{goal}`.
4. **Inertia page paths:** `'pockets/Index'` — Vite musi widzieć folder `pages/pockets/`.
5. **Policy auto-discovery:** Laravel odkrywa `PocketPolicy` dla `Pocket` — usuń ewentualne ręczne mapowanie Goal w `AuthServiceProvider` jeśli istnieje.
