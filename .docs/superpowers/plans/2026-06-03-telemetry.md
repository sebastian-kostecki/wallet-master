# Telemetry — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Centralize PRD §3.4 product events via `App\Telemetry\Event::record`, HTTP ingest for frontend list metrics, and migrate scattered `Log::` calls to the `telemetry` channel.

**Architecture:** Stateless `Event` helper writes JSON lines to existing `telemetry` log channel. Backend mutations call `Event::record` from Actions/controllers. Frontend uses `resources/js/lib/telemetry.ts` → `POST /telemetry/event` with allowlisted names and `throttle:api`. Existing Laravel domain events (`TransferCreated`, enrichment) keep firing; listeners delegate to `Event::record` instead of `Log::info`.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3 + TypeScript.

**Spec:** `.docs/superpowers/specs/2026-06-03-mvp-release-readiness-design.md` (Phase 1)

**Branch:** `improvement/telemetry`

---

## File map

| Action | Path |
|--------|------|
| Create | `app/Telemetry/Event.php` |
| Create | `config/telemetry.php` |
| Create | `app/Http/Controllers/Telemetry/TelemetryEventController.php` |
| Create | `app/Http/Requests/Telemetry/StoreTelemetryEventRequest.php` |
| Create | `routes/telemetry.php` |
| Create | `resources/js/lib/telemetry.ts` |
| Create | `tests/Feature/Telemetry/EventRecordTest.php` |
| Create | `tests/Feature/Telemetry/TelemetryEventControllerTest.php` |
| Modify | `app/Providers/AppServiceProvider.php` |
| Modify | `routes/web.php` |
| Modify | Listeners: `LogTransferCreated`, `LogTransferFailedValidation`, `LogImportEnrichmentTypesenseHit`, `LogImportEnrichmentTypesenseMiss` |
| Modify | `app/Imports/TransferMatcher.php`, `app/Actions/Transfers/UnlinkTransfer.php`, `app/Actions/Transfers/ConfirmTransferCandidate.php`, `app/Imports/Workflow/CommitImport.php` |
| Modify | Actions: `StoreAccount`, `UpdateAccountDetails`, `AdjustAccountBalance`, `StoreTransaction`, `UpdateTransaction`, `DeleteTransaction`, `CreateTransfer` |
| Modify | Controllers: `RegisteredUserController`, `AuthenticatedSessionController`, `LoginRequest`, `PasswordResetLinkController`, `NewPasswordController`, `AccountController` |
| Modify | `ImportController` (upload/commit hooks) |
| Modify | Vue: `transactions/Index.vue`, `transactions/Create.vue`, `resources/js/composables/useTransactionsIndexSearch.ts` (if filters live there) |
| Modify | `.docs/checklist.md` §8, §13 |

---

### Task 1: `Event::record` + unit/feature test

**Files:**
- Create: `app/Telemetry/Event.php`
- Create: `tests/Feature/Telemetry/EventRecordTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

use App\Telemetry\Event;
use Illuminate\Support\Facades\Log;

test('event record writes to telemetry channel with envelope', function () {
    Log::fake();

    Event::record('transaction_created', ['transaction_id' => 42], userId: 7);

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) {
        return $message === 'transaction_created'
            && $context['event'] === 'transaction_created'
            && $context['user_id'] === 7
            && $context['transaction_id'] === 42
            && isset($context['recorded_at']);
    });
});
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Telemetry/EventRecordTest.php`  
Expected: class `App\Telemetry\Event` not found

- [ ] **Step 3: Implement `Event`**

```php
<?php

declare(strict_types=1);

namespace App\Telemetry;

use Illuminate\Support\Facades\Log;

final class Event
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(string $name, array $payload = [], ?int $userId = null): void
    {
        Log::channel('telemetry')->info($name, array_merge([
            'event' => $name,
            'recorded_at' => now()->toIso8601String(),
            'user_id' => $userId ?? auth()->id(),
        ], $payload));
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Telemetry/EventRecordTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Telemetry/Event.php tests/Feature/Telemetry/EventRecordTest.php
git commit -m "feat(telemetry): add Event::record helper"
```

---

### Task 2: Client allowlist config + API route + rate limiter

**Files:**
- Create: `config/telemetry.php`
- Create: `app/Http/Requests/Telemetry/StoreTelemetryEventRequest.php`
- Create: `app/Http/Controllers/Telemetry/TelemetryEventController.php`
- Create: `routes/telemetry.php`
- Create: `tests/Feature/Telemetry/TelemetryEventControllerTest.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Add config**

```php
<?php

return [
    'client_events' => [
        'transaction_create_opened',
        'transactions_filtered',
        'transactions_sorted',
        'transactions_page_changed',
    ],
    'max_payload_keys' => 20,
];
```

- [ ] **Step 2: Write failing controller tests**

```php
<?php

use App\Models\User;

test('guest cannot post telemetry event', function () {
    $this->postJson(route('telemetry.store'), [
        'event' => 'transactions_filtered',
        'payload' => ['account_id' => 1],
    ])->assertUnauthorized();
});

test('authenticated user can post allowlisted client event', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('telemetry.store'), [
            'event' => 'transactions_filtered',
            'payload' => ['account_id' => 1],
        ])
        ->assertNoContent();

    // Optional: Log::fake() in test + assert Event written — or assert 204 only for speed
});

test('rejects unknown client event name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('telemetry.store'), [
            'event' => 'evil_event',
            'payload' => [],
        ])
        ->assertUnprocessable();
});
```

- [ ] **Step 3: Implement request + controller + route**

`StoreTelemetryEventRequest`: `event` required|string|in:config list; `payload` nullable|array|max:20 keys.

`TelemetryEventController::store`: `Event::record($request->validated('event'), $request->validated('payload', []), $request->user()->id);` return `204`.

`routes/telemetry.php`:

```php
Route::middleware(['auth', 'throttle:api'])->group(function () {
    Route::post('/telemetry/event', [TelemetryEventController::class, 'store'])->name('telemetry.store');
});
```

`routes/web.php`: add `require __DIR__.'/telemetry.php';`

`AppServiceProvider::boot()`:

```php
RateLimiter::for('api', function (Request $request): Limit {
    return Limit::perMinute(60)->by((string) ($request->user()?->id ?? $request->ip()));
});
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Telemetry/TelemetryEventControllerTest.php`

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/telemetry.php app/Http/Requests/Telemetry/ app/Http/Controllers/Telemetry/ routes/telemetry.php routes/web.php app/Providers/AppServiceProvider.php tests/Feature/Telemetry/TelemetryEventControllerTest.php
git commit -m "feat(telemetry): add client event ingest with api rate limit"
```

---

### Task 3: Frontend `telemetry.ts` + wire transactions index

**Files:**
- Create: `resources/js/lib/telemetry.ts`
- Modify: `resources/js/pages/transactions/Index.vue`
- Modify: `resources/js/pages/transactions/Create.vue` (or route that opens create — call on mount)
- Modify: `resources/js/composables/useTransactionsIndexSearch.ts` (if filter/sort/page handlers live here)

- [ ] **Step 1: Implement helper**

```typescript
import { router } from '@inertiajs/vue3';

type TelemetryPayload = Record<string, string | number | boolean | null>;

export function track(event: string, payload: TelemetryPayload = {}): void {
    const csrf =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    void fetch(route('telemetry.store'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ event, payload }),
        credentials: 'same-origin',
    }).catch(() => {
        // Best-effort — never block UX
    });
}
```

Ensure Ziggy exposes `telemetry.store` (run route list if needed).

- [ ] **Step 2: Wire events**

| Event | Where |
|-------|--------|
| `transactions_filtered` | After user applies filters (submit/search handler — once per intent, not every keystroke) |
| `transactions_sorted` | When sort column/direction changes |
| `transactions_page_changed` | `PaginationBar` page change callback |
| `transaction_create_opened` | `transactions/Create.vue` `onMounted` or link click from Index “Add” |

Example payload: `{ account_id: number | null, from?: string, to?: string, sort?: string }` — no row content.

- [ ] **Step 3: Manual smoke**

Run: `./vendor/bin/sail npm run dev` — filter list, confirm `storage/logs/telemetry-*.log` receives events.

- [ ] **Step 4: Commit**

```bash
git add resources/js/lib/telemetry.ts resources/js/pages/transactions/ resources/js/composables/
git commit -m "feat(telemetry): frontend track helper and transactions list events"
```

---

### Task 4: Auth events

**Files:**
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- Modify: `app/Http/Controllers/Auth/NewPasswordController.php`
- Create: `tests/Feature/Telemetry/AuthTelemetryTest.php`

- [ ] **Step 1: Write failing tests**

- `user_registered` after successful registration (Log::fake + assert telemetry channel)
- `user_logged_in` after successful login
- `user_login_failed` when `Auth::attempt` fails (no user id in payload)
- `password_reset_requested` after `Password::sendResetLink` (no email in payload — only `user_id` if resolvable, else omit)
- `password_reset_completed` when `Password::PASSWORD_RESET`

- [ ] **Step 2: Add `Event::record` calls**

`RegisteredUserController::store` after `User::create`: `Event::record('user_registered', ['user_id' => $user->id], $user->id);`

`AuthenticatedSessionController::store` after authenticate: `Event::record('user_logged_in', [], $request->user()->id);`

`LoginRequest::authenticate` in failed branch before throw: `Event::record('user_login_failed', ['ip' => $this->ip()]);` — no email.

`PasswordResetLinkController::store`: `Event::record('password_reset_requested');`

`NewPasswordController::store` on success: resolve user by email if exists, `Event::record('password_reset_completed', ['user_id' => $id], $id);`

- [ ] **Step 3: Run tests + commit**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Telemetry/AuthTelemetryTest.php`

---

### Task 5: Account events

**Files:**
- Modify: `app/Actions/Accounts/StoreAccount.php`
- Modify: `app/Actions/Accounts/UpdateAccountDetails.php`
- Modify: `app/Actions/Accounts/AdjustAccountBalance.php`
- Modify: `app/Http/Controllers/Accounts/AccountController.php`
- Create: `tests/Feature/Telemetry/AccountTelemetryTest.php`

- [ ] **Step 1: Tests** for `account_created`, `account_updated`, `account_deleted`, `account_deleted_with_transactions` (count), `account_balance_adjusted` (old/new balance strings, no PII)

- [ ] **Step 2: Implement**

`StoreAccount::handle` return account; controller or action end: `Event::record('account_created', ['account_id' => $account->id], $user->id);`

`UpdateAccountDetails::handle`: `account_updated` with `account_id`.

`AccountController::destroy`: before/after delete — count transactions: `Event::record('account_deleted_with_transactions', ['account_id' => $account->id, 'transaction_count' => $count], auth()->id());` also `account_deleted`.

`AdjustAccountBalance::handle`: `account_balance_adjusted` with `old_balance`, `new_balance`, `account_id`.

- [ ] **Step 3: Run tests + commit**

---

### Task 6: Transaction events

**Files:**
- Modify: `app/Actions/Transactions/StoreTransaction.php`, `UpdateTransaction.php`, `DeleteTransaction.php`
- Create: `tests/Feature/Telemetry/TransactionTelemetryTest.php`

- [ ] **Step 1: Tests** assert telemetry on store/update/delete happy paths (`transaction_id`, `account_id` only).

- [ ] **Step 2: `Event::record` at end of each `handle()`** after DB commit.

- [ ] **Step 3: Run + commit**

---

### Task 7: Transfer + import events (migrate existing logs)

**Files:**
- Modify: `app/Listeners/LogTransferCreated.php`, `LogTransferFailedValidation.php`
- Modify: `app/Listeners/LogImportEnrichmentTypesenseHit.php`, `LogImportEnrichmentTypesenseMiss.php`
- Modify: `app/Imports/TransferMatcher.php`, `UnlinkTransfer.php`, `ConfirmTransferCandidate.php`
- Modify: `app/Imports/Workflow/CommitImport.php`
- Modify: `app/Http/Controllers/Imports/ImportController.php`
- Create: `tests/Feature/Telemetry/ImportTelemetryTest.php` (minimal: import_completed counters, no row bodies)

- [ ] **Step 1: Replace listener bodies**

```php
use App\Telemetry\Event;

Event::record('transfer_created', [
    'transfer_id' => $event->transferId,
    'from_account_id' => $event->fromAccountId,
    'to_account_id' => $event->toAccountId,
    'amount' => $event->amount,
    'date' => $event->date,
], $event->userId);
```

Same pattern for `transfer_failed_validation`, `import_enrichment_typesense_hit|miss`.

- [ ] **Step 2: Replace direct `Log::channel('telemetry')` in TransferMatcher / Unlink / Confirm** with `Event::record(...)`.

- [ ] **Step 3: Import lifecycle in `CommitImport` + controller**

| Event | When |
|-------|------|
| `import_started` | Job begins processing (first line of `handle` after lock, or when status → processing) |
| `import_completed` | Successful commit — `import_id`, `rows_*` counters only |
| `import_failed` | Catch/failure path |
| `import_type_inferred` | Per row or aggregated count — prefer **one event per import** with `{ expense: N, income: M }` to avoid noise |
| `import_rows_skipped_duplicate` | When counter increments (aggregated at end is OK) |
| `import_bank_resolved_from_account` | Once per import with `bank` slug |
| `import_headers_unrecognized` | Upload path when adapter rejects headers |

Keep `import_row_validation_failed` via `Event::record` (replace existing Log line ~312).

- [ ] **Step 4: Run import + transfer tests**

Run: `./vendor/bin/sail artisan test --compact --filter=Telemetry`  
Run: `./vendor/bin/sail artisan test --compact tests/Feature/Imports/CommitImportJobTest.php`

- [ ] **Step 5: Commit**

---

### Task 8: Checklist + verification gate

- [ ] **Step 1: Update `.docs/checklist.md`** — check §8 and §13 items implemented.

- [ ] **Step 2: Full verification**

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact
./vendor/bin/phpstan analyse
```

- [ ] **Step 3: Commit checklist**

```bash
git add .docs/checklist.md
git commit -m "docs: mark telemetry checklist items complete"
```

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| `Event::record` + telemetry channel | Task 1 |
| API 60/min + frontend helper | Tasks 2–3 |
| All PRD §3.4 backend events | Tasks 4–7 |
| Frontend list events | Task 3 |
| Tests | Tasks 1–7 |
| §12.1 api rate limit | Task 2 |

No TBD placeholders. JSON line format uses existing `daily` driver on `telemetry` channel.
