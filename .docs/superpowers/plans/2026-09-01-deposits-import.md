# Deposits import detection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Detect term deposit capital rows during CSV import (BNP + mBank rules first): auto-create/close when confident, otherwise surface candidates with banner + filter; interest stays normal P&L.

**Architecture:** Inline classification in `CommitImport::buildInsertRow` before `ResolveCategoryForImportRow`. Bank-specific rules via adapter hook + `DepositRowClassifier`. Auto paths call `CreateDepositFromImport` / `CloseDepositFromImport` (link existing import transaction). Candidates inserted with `category_id = null` and `deposit_match_status = manual`. `TransferMatcher` unchanged order, after all rows.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Vue 3, Ziggy, vue-i18n, Pest 4, Tailwind v3, Sail.

**Spec:** `.docs/superpowers/specs/2026-09-01-deposits-import-design.md`  
**Depends on:** FR-D1 deposits (implemented on `feature/deposits` or `develop`)  
**Suggested branch:** `feature/deposits-import`

## Global Constraints

- Variant A: `Actions/Deposits/`, `Support/Deposits/`; no `app/Services/`; Actions never import `Http/Resources`.
- Domain folder **Deposits** (plural). UI label PL **Lokaty**.
- Capital: `category_id = null`, `transfer_id = null`, `pocket_id = null`.
- Interest import rows: normal P&L path (`DepositClassificationIntent::None`).
- Candidate rows: `category_id = null` immediately (decision A).
- Dates in FormRequests: `d-m-Y`. Amounts: `decimal:0,2`.
- mBank `Kategoria` used only for deposit classification, never for `category_id`.
- Code/comments/commits in English. Copy in `pl.json` / `en.json`.
- After PHP: `vendor/bin/pint --dirty --format agent`, then `./vendor/bin/sail artisan test --compact` on the task’s test file(s).
- After Vue/TS: `npx prettier --write` on touched files.

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `database/migrations/2026_09_01_100000_add_deposit_match_columns_to_transactions.php` | `deposit_match_status`, `deposit_candidate_kind` |
| Create | `app/Enums/DepositMatchStatus.php` | `none` / `manual` / `auto` / `rejected` |
| Modify | `app/Models/Transaction.php` | casts, `scopePendingDepositCandidate()` |
| Create | `app/Support/Deposits/DepositClassification.php` | intent + confidence value object |
| Create | `app/Support/Deposits/DepositRowClassifier.php` | orchestrator |
| Create | `app/Support/Deposits/BnpDepositRules.php` | BNP signals |
| Create | `app/Support/Deposits/MBankDepositRules.php` | mBank `Kategoria` signals |
| Create | `app/Support/Deposits/DepositCloser.php` | match active deposits by principal |
| Modify | `app/Imports/BankAdapters/BankImportAdapter.php` | optional `classifyDepositRow()` |
| Modify | `app/Imports/BankAdapters/AbstractBankImportAdapter.php` | default `none` |
| Modify | `app/Imports/BankAdapters/BnpParibasImportAdapter.php` | delegate to `BnpDepositRules` |
| Modify | `app/Imports/BankAdapters/MBankImportAdapter.php` | delegate to `MBankDepositRules` |
| Create | `app/Actions/Deposits/CreateDepositFromImport.php` | auto open during import |
| Create | `app/Actions/Deposits/CloseDepositFromImport.php` | auto close — link import tx |
| Create | `app/Actions/Deposits/ConfirmDepositCandidate.php` | banner confirm |
| Create | `app/Actions/Deposits/RejectDepositCandidate.php` | banner reject |
| Modify | `app/Imports/Workflow/CommitImport.php` | inline classification hook |
| Create | `app/Http/Controllers/Deposits/DepositCandidateController.php` | confirm/reject |
| Create | `app/Http/Requests/Deposits/ConfirmDepositCandidateRequest.php` | `deposit_id` when close |
| Create | `app/Http/Resources/Deposits/DepositCandidateResource.php` | banner shape |
| Modify | `app/Policies/TransactionPolicy.php` | confirm/reject deposit candidate |
| Modify | `routes/deposits.php`, `config/routes.php` | candidate routes |
| Modify | `app/Actions/Transactions/ListTransactions.php` | pending candidates + filter |
| Modify | `app/Http/Requests/Transactions/TransactionIndexRequest.php` | `deposit_candidates` filter |
| Modify | `app/Http/Controllers/Transactions/TransactionController.php` | Inertia prop |
| Modify | `app/Http/Resources/Transactions/TransactionResource.php` | candidate badge fields |
| Create | `resources/js/components/deposits/DepositCandidatesBanner.vue` | banner UI |
| Modify | `resources/js/pages/transactions/Index.vue` | banner + filter chip |
| Modify | `resources/js/locales/pl.json`, `en.json` | copy |
| Create | `tests/Fixtures/import/bnp-deposit-open.csv`, `bnp-deposit-close.csv` | BNP fixtures |
| Create | `tests/Fixtures/import/mbank-deposit-open.csv` | mBank open fixture |
| Modify | `.docs/prd.md`, `.docs/checklist.md` | FR-D2 |

---

### Task 1: Schema, enum, Transaction model

**Files:**
- Create: `database/migrations/2026_09_01_100000_add_deposit_match_columns_to_transactions.php`
- Create: `app/Enums/DepositMatchStatus.php`
- Modify: `app/Models/Transaction.php`
- Test: `tests/Feature/Deposits/DepositMatchStatusTest.php`

**Interfaces:**
- Consumes: existing `transactions` table.
- Produces: `DepositMatchStatus` enum; `Transaction::$deposit_match_status`, `$deposit_candidate_kind`; `Transaction::scopePendingDepositCandidate()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Enums\DepositMatchStatus;
use App\Models\Transaction;
use App\Models\User;

test('transaction persists deposit match status and candidate kind', function () {
    $user = User::factory()->create();

    $transaction = Transaction::factory()->for($user)->create([
        'deposit_match_status' => DepositMatchStatus::Manual,
        'deposit_candidate_kind' => 'open',
        'category_id' => null,
    ]);

    expect($transaction->fresh()->deposit_match_status)->toBe(DepositMatchStatus::Manual);
    expect($transaction->fresh()->deposit_candidate_kind)->toBe('open');
});

test('pending deposit candidate scope returns manual rows without movement', function () {
    $user = User::factory()->create();

    $candidate = Transaction::factory()->for($user)->create([
        'deposit_match_status' => DepositMatchStatus::Manual,
        'deposit_candidate_kind' => 'close_principal',
        'category_id' => null,
    ]);

    Transaction::factory()->for($user)->create([
        'deposit_match_status' => DepositMatchStatus::None,
    ]);

    $ids = Transaction::query()
        ->whereBelongsTo($user)
        ->pendingDepositCandidate()
        ->pluck('id');

    expect($ids)->toContain($candidate->id);
    expect($ids)->toHaveCount(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Deposits/DepositMatchStatusTest.php`  
Expected: FAIL — unknown column / enum / scope.

- [ ] **Step 3: Add migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('deposit_match_status', 20)->default('none')->after('transfer_candidate_for_id');
            $table->string('deposit_candidate_kind', 32)->nullable()->after('deposit_match_status');
            $table->index(['user_id', 'deposit_match_status']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'deposit_match_status']);
            $table->dropColumn(['deposit_match_status', 'deposit_candidate_kind']);
        });
    }
};
```

- [ ] **Step 4: Add enum + model cast + scope on `Transaction`**

`DepositMatchStatus`: cases `None`, `Auto`, `Manual`, `Rejected` (string values mirror `TransferMatchStatus`).

Add to `$fillable`: `deposit_match_status`, `deposit_candidate_kind`.  
Cast `deposit_match_status` to enum.  
Scope `pendingDepositCandidate`: `deposit_match_status = Manual`.

- [ ] **Step 5: Run migration + tests**

Run: `./vendor/bin/sail artisan migrate`  
Run: `./vendor/bin/sail artisan test --compact tests/Feature/Deposits/DepositMatchStatusTest.php`  
Expected: PASS

- [ ] **Step 6: Pint**

Run: `vendor/bin/pint --dirty --format agent`

---

### Task 2: DepositRowClassifier + bank rules + adapter hook

**Files:**
- Create: `app/Support/Deposits/DepositClassification.php`
- Create: `app/Support/Deposits/BnpDepositRules.php`
- Create: `app/Support/Deposits/MBankDepositRules.php`
- Create: `app/Support/Deposits/DepositRowClassifier.php`
- Modify: `app/Imports/BankAdapters/BankImportAdapter.php`
- Modify: `app/Imports/BankAdapters/AbstractBankImportAdapter.php`
- Modify: `app/Imports/BankAdapters/BnpParibasImportAdapter.php`
- Modify: `app/Imports/BankAdapters/MBankImportAdapter.php`
- Test: `tests/Unit/Support/Deposits/DepositRowClassifierTest.php`

**Interfaces:**
- Consumes: `ParsedImportRow`, raw CSV `$row`, `Bank`.
- Produces:
  - `DepositClassification::none(): self`
  - `DepositClassification::with(DepositClassificationIntent $intent, DepositClassificationConfidence $confidence): self`
  - Properties: `intent` (`None|Open|ClosePrincipal`), `confidence` (`Auto|Candidate`), helpers `isAutoOpen()`, `isAutoClose()`, `isCandidateOpen()`, `isCandidateClose()`
  - `DepositRowClassifier::classify(BankImportAdapter $adapter, array $row, array $mapping, ParsedImportRow $parsed, User $user): DepositClassification`
  - `BankImportAdapter::classifyDepositRow(array $row, array $mapping, ParsedImportRow $parsed): DepositClassification`

- [ ] **Step 1: Write failing unit tests**

```php
<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Imports\BankAdapters\BnpParibasImportAdapter;
use App\Imports\BankAdapters\MBankImportAdapter;
use App\Imports\ParsedImportRow;
use App\Support\Deposits\DepositClassification;
use App\Support\Deposits\DepositClassificationIntent;
use App\Support\Deposits\DepositRowClassifier;

it('classifies BNP OTWARCIE LOKATY as auto open', function () {
    $adapter = new BnpParibasImportAdapter;
    $row = [
        'Data transakcji' => '01-08-2026',
        'Kwota' => '-5000.00',
        'Opis' => '',
        'Typ transakcji' => 'OTWARCIE LOKATY',
    ];
    $mapping = ['date' => 'Data transakcji', 'amount' => 'Kwota', 'description' => 'Opis'];
    $parsed = $adapter->normalizeRow($row, $mapping);

    $result = $adapter->classifyDepositRow($row, $mapping, $parsed);

    expect($result->intent)->toBe(DepositClassificationIntent::Open);
    expect($result->confidence)->toBe(DepositClassificationConfidence::Auto);
});

it('classifies mBank Lokaty category with negative amount as auto open', function () {
    $adapter = new MBankImportAdapter;
    $row = [
        'Data operacji' => '2026-08-01',
        'Opis operacji' => 'Zakładanie lokaty',
        'Kategoria' => 'Lokaty i konto oszcz.',
        'Kwota' => '-3000,00 PLN',
    ];
    $mapping = ['date' => 'Data operacji', 'amount' => 'Kwota', 'description' => 'Opis operacji'];
    $parsed = $adapter->normalizeRow($row, $mapping);

    $result = $adapter->classifyDepositRow($row, $mapping, $parsed);

    expect($result->intent)->toBe(DepositClassificationIntent::Open);
    expect($result->confidence)->toBe(DepositClassificationConfidence::Auto);
});

it('returns none for unsupported bank adapter default', function () {
    // Use a bank adapter without override — AbstractBankImportAdapter default
    expect(DepositClassification::none()->intent)->toBe(DepositClassificationIntent::None);
});
```

Add cases: BNP `SPŁATA DEPOZYTU` + positive → `ClosePrincipal`/`Auto`; mBank positive same category → `ClosePrincipal`/`Auto` (classifier returns close intent; closers decides auto vs candidate in Task 3).

- [ ] **Step 2: Run tests — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Deposits/DepositRowClassifierTest.php`

- [ ] **Step 3: Implement `DepositClassification`, rules, classifier, adapter hook**

BNP rules (case-insensitive trim on `Typ transakcji`, fallback read from row keys like adapter `resolveDescription`):
- `OTWARCIE LOKATY` + amount < 0 → Open / Auto
- `SPŁATA DEPOZYTU` + amount > 0 → ClosePrincipal / Auto (confidence may downgrade in Task 3 via `DepositCloser`)

mBank rules on `Kategoria === 'Lokaty i konto oszcz.'`:
- amount < 0 → Open / Auto
- amount > 0 → ClosePrincipal / Auto

`DepositRowClassifier::classify` calls `$adapter->classifyDepositRow(...)`.

- [ ] **Step 4: Run tests — expect PASS**

- [ ] **Step 5: Pint**

---

### Task 3: DepositCloser + import Actions

**Files:**
- Create: `app/Support/Deposits/DepositCloser.php`
- Create: `app/Actions/Deposits/CreateDepositFromImport.php`
- Create: `app/Actions/Deposits/CloseDepositFromImport.php`
- Test: `tests/Unit/Support/Deposits/DepositCloserTest.php`
- Test: `tests/Feature/Deposits/DepositImportActionsTest.php`

**Interfaces:**
- Consumes: `Deposit`, `Transaction`, `User`, `Import`.
- Produces:
  - `DepositCloser::matchingActiveDeposits(User $user, string $principal, int $currencyId): Collection<int, Deposit>`
  - `CreateDepositFromImport::handle(User $user, Import $import, Account $account, ParsedImportRow $parsed, array $insertMetadata): Deposit` — creates deposit + transaction with `import_id`, `import_fingerprint`, `deposit_match_status = Auto`, movement `open`, updates `current_balance`
  - `CloseDepositFromImport::handle(User $user, Deposit $deposit, Transaction $transaction): Deposit` — links existing import transaction: `category_id = null`, movement `close_principal`, `status = closed`, `deposit_match_status = Auto`

- [ ] **Step 1: Write failing DepositCloser test**

```php
test('deposit closer finds active deposits by principal and currency', function () {
    // seed two deposits same principal → count 2
    // one deposit different principal → not included
});
```

- [ ] **Step 2: Write failing CreateDepositFromImport test**

Create import metadata array (fingerprint fields) and assert: deposit active, movement open, transaction has `import_id`, `category_id` null, `deposit_match_status` Auto, account balance updated.

- [ ] **Step 3: Write failing CloseDepositFromImport test**

Pre-create active deposit principal 5000. Create transaction simulating import row +5000. Call action. Assert deposit closed, movement close_principal, transaction linked, `deposit_match_status` Auto.

- [ ] **Step 4: Implement classes**

`CreateDepositFromImport`: mirror `CreateDeposit` but accept pre-built transaction fields from `CommitImport` (fingerprint, dedupe, enriched description, `import_id`). Do **not** double-update balance if `CommitImport` already sums `importedAmountSum` — action updates balance OR CommitImport includes amount in sum once (pick one; prefer action updates balance and CommitImport adds to `importedAmountSum` without duplicate balance flush).

`CloseDepositFromImport`: mirror `AssignTransactionToDeposit::assignClosePrincipal` without creating a new transaction.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Deposits/DepositCloserTest.php tests/Feature/Deposits/DepositImportActionsTest.php`

- [ ] **Step 6: Pint**

---

### Task 4: CommitImport integration

**Files:**
- Modify: `app/Imports/Workflow/CommitImport.php`
- Test: `tests/Feature/Imports/DepositImportBnpTest.php`
- Test: `tests/Feature/Imports/DepositImportMBankTest.php`
- Create: `tests/Fixtures/import/bnp-deposit-open.csv`
- Create: `tests/Fixtures/import/bnp-deposit-close.csv`
- Create: `tests/Fixtures/import/mbank-deposit-open.csv`

**Interfaces:**
- Consumes: Task 2 `DepositRowClassifier`, Task 3 Actions + `DepositCloser`.
- Produces: import commit behavior for auto/candidate/none paths; telemetry events from spec.

- [ ] **Step 1: Write failing BNP auto-open feature test**

```php
test('BNP import auto opens deposit for OTWARCIE LOKATY row', function () {
    $user = User::factory()->create();
    $account = /* BNP checking account */;
    $import = createImportWithFixture(
        $user,
        $account,
        'tests/Fixtures/import/bnp-deposit-open.csv',
        ['date' => 'Data transakcji', 'amount' => 'Kwota', 'description' => 'Opis'],
        ['Data transakcji', 'Kwota', 'Opis', 'Typ transakcji'],
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $import->id)->sole();
    expect($transaction->deposit_match_status)->toBe(DepositMatchStatus::Auto);
    expect($transaction->category_id)->toBeNull();
    expect($transaction->depositMovement?->kind)->toBe(DepositMovementKind::Open);
    expect(Deposit::query()->where('user_id', $user->id)->count())->toBe(1);
});
```

Fixture `bnp-deposit-open.csv`:

```
Data transakcji;Kwota;Opis;Typ transakcji
01-08-2026;-5000.00;;OTWARCIE LOKATY
```

- [ ] **Step 2: Write failing BNP auto-close + ambiguous close tests**

Auto-close: pre-seed active deposit principal 5000, import `SPŁATA DEPOZYTU` +5000 → deposit closed.  
Ambiguous: two active deposits 5000 → candidate (`deposit_match_status = Manual`, `deposit_candidate_kind = close_principal`), no deposit closed.

- [ ] **Step 3: Write failing mBank open test** using `mbank-deposit-open.csv` with `#Kategoria` column.

- [ ] **Step 4: Implement `CommitImport` hook**

In `buildInsertRow`, after successful parse, before `ResolveCategoryForImportRow`:

```php
$classification = $this->depositRowClassifier->classify(
    $adapter, $row, $mapping, $parsedRow, $lockedImport->user,
);

if ($classification->isAutoOpen()) {
    $this->createDepositFromImport->handle(/* ... */);
    $counters->rowsImported++;
    $counters->importedAmountSum = bcadd($counters->importedAmountSum, $parsedRow->amount, 2);
    Event::record('deposit_import_open_auto', ['import_id' => $lockedImport->id], $lockedImport->user_id);
    return null; // skip bulk insert
}

if ($classification->intent === DepositClassificationIntent::ClosePrincipal) {
    $principal = ltrim($parsedRow->amount, '-');
    $matches = $this->depositCloser->matchingActiveDeposits($lockedImport->user, $principal, $account->currency_id);
    if ($matches->count() === 1 && $classification->confidence === DepositClassificationConfidence::Auto) {
        // insert row with null category + auto close via CloseDepositFromImport after insert OR inline create
        // prefer: build insert row with deposit fields, flush, then close in same transaction chunk callback
    }
    // 0 or 2+ matches → candidate insert with deposit_match_status manual
}

if ($classification->isCandidateOpen() || $classification->isCandidateClose()) {
    return [/* insert row */, 'category_id' => null, 'deposit_match_status' => 'manual', 'deposit_candidate_kind' => ...];
}
```

Inject dependencies in constructor: `DepositRowClassifier`, `CreateDepositFromImport`, `CloseDepositFromImport`, `DepositCloser`.

**Close auto implementation note:** insert transaction row first (null category), then in `flushChunk` post-insert hook OR immediate DB transaction call `CloseDepositFromImport` with created model. Keep balance accounting consistent.

- [ ] **Step 5: Run import tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Imports/DepositImportBnpTest.php tests/Feature/Imports/DepositImportMBankTest.php`

- [ ] **Step 6: Add test — deposit open row not transfer-matched**

Run: extend or new test ensuring `TransferMatcher` skips auto-open import row.

- [ ] **Step 7: Pint**

---

### Task 5: Confirm / reject candidates (HTTP)

**Files:**
- Create: `app/Actions/Deposits/ConfirmDepositCandidate.php`
- Create: `app/Actions/Deposits/RejectDepositCandidate.php`
- Create: `app/Http/Requests/Deposits/ConfirmDepositCandidateRequest.php`
- Create: `app/Http/Controllers/Deposits/DepositCandidateController.php`
- Modify: `app/Policies/TransactionPolicy.php`
- Modify: `routes/deposits.php`
- Modify: `config/routes.php`
- Test: `tests/Feature/Deposits/DepositCandidateConfirmRejectTest.php`

**Interfaces:**
- Consumes: `AssignTransactionToDeposit` invariants (reuse private logic or delegate).
- Produces:
  - `ConfirmDepositCandidate::handle(User $user, Transaction $transaction, array $validated): Deposit`
  - `RejectDepositCandidate::handle(User $user, Transaction $transaction): void`
  - Routes: `deposits.candidates.confirm`, `deposits.candidates.reject`

- [ ] **Step 1: Write failing confirm open test**

Seed candidate transaction (`deposit_match_status = Manual`, `deposit_candidate_kind = open`, amount -3000). POST confirm. Expect deposit created, movement open, status stays Manual (user confirmed).

- [ ] **Step 2: Write failing confirm close test with `deposit_id`**

Two matching active deposits; candidate close +5000. POST with chosen `deposit_id`. Expect that deposit closed.

- [ ] **Step 3: Write failing reject test**

Reject → `deposit_match_status = Rejected`, `deposit_candidate_kind = null`, `category_id` still null.

- [ ] **Step 4: Implement actions, request, controller, policy, routes**

Add to `config/routes.php`:

```php
'deposits.candidates.confirm' => 'lokaty/kandydaci/{transaction}/potwierdz',
'deposits.candidates.reject' => 'lokaty/kandydaci/{transaction}/odrzuc',
```

Mirror `TransferCandidateController` structure.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Deposits/DepositCandidateConfirmRejectTest.php`

- [ ] **Step 6: Pint**

---

### Task 6: ListTransactions — pending candidates + filter

**Files:**
- Modify: `app/Actions/Transactions/ListTransactions.php`
- Modify: `app/Http/Requests/Transactions/TransactionIndexRequest.php`
- Create: `app/Http/Resources/Deposits/DepositCandidateResource.php`
- Modify: `app/Http/Controllers/Transactions/TransactionController.php`
- Modify: `app/Http/Resources/Transactions/TransactionResource.php`
- Test: `tests/Feature/Transactions/TransactionIndexDepositCandidatesTest.php`

**Interfaces:**
- Produces: `ListTransactions::getPendingDepositCandidates(): Collection<int, Transaction>`; filter key `deposit_candidates` in `getFilters()`; Inertia prop `pending_deposit_candidates`.

- [ ] **Step 1: Write failing index test**

Seed one deposit candidate. GET `/transakcje`. Assert Inertia `pending_deposit_candidates` has 1 entry with `kind`, `transaction_id`, `matching_deposits` for close.

GET `/transakcje?deposit_candidates=1` → only manual deposit candidates in paginator.

- [ ] **Step 2: Implement backend**

`handlePendingDepositCandidates()` — load transactions with `pendingDepositCandidate()`, eager `account`, for close kind attach active deposits with same principal via `DepositCloser`.

`TransactionIndexRequest`: validate `deposit_candidates` as optional boolean/in flag; include in `getFilters()`.

`applyFilters`: when `deposit_candidates` true, `where('deposit_match_status', Manual)`.

`DepositCandidateResource` shape:

```php
[
    'transaction_id' => $transaction->id,
    'kind' => $transaction->deposit_candidate_kind,
    'amount' => (string) $transaction->amount,
    'date' => /* display date */,
    'description' => $transaction->description,
    'account' => AccountResource::make($transaction->account),
    'matching_deposits' => DepositResource::collection($matches), // close only
]
```

Add `deposit_candidate` fields to `TransactionResource` for row badge.

- [ ] **Step 3: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionIndexDepositCandidatesTest.php`

- [ ] **Step 4: Pint**

---

### Task 7: Frontend — banner + filter

**Files:**
- Create: `resources/js/components/deposits/DepositCandidatesBanner.vue`
- Modify: `resources/js/pages/transactions/Index.vue`
- Modify: `resources/js/locales/pl.json`, `resources/js/locales/en.json`

**Interfaces:**
- Consumes: `pending_deposit_candidates` prop; routes `deposits.candidates.confirm`, `deposits.candidates.reject`.

- [ ] **Step 1: Add i18n keys**

`deposits.candidates.bannerTitle`, `confirm`, `reject`, `kindOpen`, `kindClose`, filter label `deposits.candidates.filter`.

- [ ] **Step 2: Create `DepositCandidatesBanner.vue`**

Mirror `TransferCandidatesBanner.vue`:
- Collapsible banner below transfer candidates
- For `open`: Confirm / Reject only
- For `close_principal`: deposit `<Select>` from `matching_deposits`, then Confirm
- Partial reload `pending_deposit_candidates` on success

- [ ] **Step 3: Wire into `transactions/Index.vue`**

Import banner; render when `pending_deposit_candidates.length > 0`.  
Add filter chip linking to `?deposit_candidates=1` (preserve other filters).  
Show row badge when `deposit_candidate` prop present.

- [ ] **Step 4: Prettier**

Run: `./vendor/bin/sail npm run format -- resources/js/components/deposits/DepositCandidatesBanner.vue resources/js/pages/transactions/Index.vue`

- [ ] **Step 5: Manual smoke**

Import BNP open fixture → visit `/transakcje` → banner visible → confirm creates deposit tab entry.

---

### Task 8: PRD, checklist, regression

**Files:**
- Modify: `.docs/prd.md` — add FR-D2 under §6.8; telemetry events; update FR-D1 import note
- Modify: `.docs/checklist.md` — checkbox under import / deposits

- [ ] **Step 1: Update PRD FR-D2**

Acceptance criteria from spec: BNP/mBank auto open, auto close when unique match, candidate on ambiguity, banner+filter, interest unchanged.

- [ ] **Step 2: Update checklist**

`- [ ] Import lokat BNP/mBank (FR-D2)`

- [ ] **Step 3: Run domain regression**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Deposits/ tests/Feature/Imports/DepositImportBnpTest.php tests/Feature/Imports/DepositImportMBankTest.php tests/Feature/Transactions/TransactionIndexDepositCandidatesTest.php`

- [ ] **Step 4: Pint + optional PHPStan**

Run: `vendor/bin/pint --dirty --format agent`

---

## Plan self-review

**Spec coverage:**
- [x] Hybrid auto/candidate → Tasks 2–4
- [x] BNP + mBank rules → Task 2 fixtures Task 4
- [x] Hook all adapters → Task 2 default on Abstract
- [x] Interest P&L → none path unchanged Task 4
- [x] category_id null for candidates → Tasks 4–5
- [x] deposit_match_status columns → Task 1
- [x] Confirm/reject → Task 5
- [x] Banner + filter → Tasks 6–7
- [x] TransferMatcher after deposits → Task 4
- [x] Telemetry events → Task 4
- [x] PRD FR-D2 → Task 8

**Placeholder scan:** No TBD/TODO in plan steps.

**Type consistency:** `DepositMatchStatus`, `DepositClassification`, `DepositCloser::matchingActiveDeposits`, `getPendingDepositCandidates` aligned across tasks.
