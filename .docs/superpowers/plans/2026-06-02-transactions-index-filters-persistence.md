# Transactions Index Filters Persistence — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Preserve transactions index filters across Edit/Create navigation, sidebar return, and backend redirects after create/delete/transfer/import.

**Architecture:** `TransactionsIndexQuery` stores a whitelisted query array in Laravel session on every index visit; redirects and Inertia shared prop read from session (with request query override on mutations). Vue composable unifies `currentSearch` from URL with shared `transactionsIndexSearch` for breadcrumbs, sidebar, and form URLs.

**Tech Stack:** Laravel 13, Inertia v2, Vue 3, Pest 4, TypeScript composables.

**Spec:** `docs/superpowers/specs/2026-06-02-transactions-index-filters-persistence-design.md`

---

## File map

| File | Action |
|---|---|
| `app/Support/Transactions/TransactionsIndexQuery.php` | Create — session remember/redirect/query string |
| `app/Http/Controllers/Transactions/TransactionController.php` | Modify — `remember()` in index; `redirect()` in store/destroy |
| `app/Http/Controllers/Transfers/TransferController.php` | Modify — `redirect()` in store |
| `app/Http/Controllers/Imports/ImportController.php` | Modify — 3× `redirect()` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Modify — share `transactionsIndexSearch` |
| `tests/Unit/Support/Transactions/TransactionsIndexQueryTest.php` | Create — pure helper tests |
| `tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php` | Create — HTTP/session/redirect integration |
| `resources/js/composables/useTransactionsIndexSearch.ts` | Create |
| `resources/js/types/index.ts` | Modify — `SharedData.transactionsIndexSearch` |
| `resources/js/components/AppSidebar.vue` | Modify — dynamic transactions href |
| `resources/js/pages/transactions/Edit.vue` | Modify — composable + breadcrumb + put/delete URLs |
| `resources/js/pages/transactions/Create.vue` | Modify — composable + breadcrumb + post URL |
| `resources/js/pages/transfers/Create.vue` | Modify — composable + breadcrumb |
| `resources/js/pages/transactions/Index.vue` | Modify — composable + delete dialog prop |
| `resources/js/components/transactions/modals/DeleteTransactionDialog.vue` | Modify — `returnSearch` prop |

---

### Task 1: `TransactionsIndexQuery` helper (TDD)

**Files:**
- Create: `tests/Unit/Support/Transactions/TransactionsIndexQueryTest.php`
- Create: `app/Support/Transactions/TransactionsIndexQuery.php`

- [ ] **Step 1: Write the failing unit tests**

```php
<?php

use App\Support\Transactions\TransactionsIndexQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/transactions', fn () => 'ok')->name('transactions.index');
});

test('remember stores only whitelisted non-empty query keys', function () {
    $request = Request::create('/transactions', 'GET', [
        'account_id' => 5,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
        'sort' => 'amount',
        'direction' => 'asc',
        'per_page' => 25,
        'page' => 3,
        'evil' => 'drop-me',
    ]);

    TransactionsIndexQuery::remember($request);

    expect(session(TransactionsIndexQuery::sessionKey()))->toBe([
        'account_id' => 5,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
        'sort' => 'amount',
        'direction' => 'asc',
        'per_page' => 25,
    ]);
});

test('remember stores cleared state with only sort and direction', function () {
    $request = Request::create('/transactions', 'GET', [
        'sort' => 'date',
        'direction' => 'desc',
    ]);

    TransactionsIndexQuery::remember($request);

    expect(session(TransactionsIndexQuery::sessionKey()))->toBe([
        'sort' => 'date',
        'direction' => 'desc',
    ]);
});

test('toQueryString builds query prefix or empty string', function () {
    session([TransactionsIndexQuery::sessionKey() => [
        'from' => '01-04-2026',
        'account_id' => 2,
    ]]);

    expect(TransactionsIndexQuery::toQueryString())->toBe('?from=01-04-2026&account_id=2');
});

test('redirect prefers request query over session', function () {
    session([TransactionsIndexQuery::sessionKey() => ['from' => '01-01-2026']]);

    $request = Request::create('/transactions', 'POST', ['from' => '15-06-2026']);
    $response = TransactionsIndexQuery::redirect($request);

    $response->assertRedirect(route('transactions.index', ['from' => '15-06-2026']));
});

test('redirect falls back to session when request has no whitelisted keys', function () {
    session([TransactionsIndexQuery::sessionKey() => [
        'account_id' => 9,
        'sort' => 'date',
        'direction' => 'desc',
    ]]);

    $response = TransactionsIndexQuery::redirect(Request::create('/transactions', 'DELETE'));

    $response->assertRedirect(route('transactions.index', [
        'account_id' => 9,
        'sort' => 'date',
        'direction' => 'desc',
    ]));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Support/Transactions/TransactionsIndexQueryTest.php`  
Expected: FAIL — class not found

- [ ] **Step 3: Implement helper**

```php
<?php

declare(strict_types=1);

namespace App\Support\Transactions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TransactionsIndexQuery
{
    private const SESSION_KEY = 'transactions.index.query';

    /** @var list<string> */
    private const ALLOWED_KEYS = [
        'account_id',
        'from',
        'to',
        'sort',
        'direction',
        'per_page',
    ];

    public static function sessionKey(): string
    {
        return self::SESSION_KEY;
    }

    public static function remember(Request $request): void
    {
        session([self::SESSION_KEY => self::extract($request)]);
    }

    /**
     * @return array<string, int|string>
     */
    public static function params(): array
    {
        /** @var array<string, int|string>|null $stored */
        $stored = session(self::SESSION_KEY);

        return $stored ?? [];
    }

    public static function toQueryString(): string
    {
        $params = self::params();

        if ($params === []) {
            return '';
        }

        return '?'.http_build_query($params);
    }

    public static function redirect(Request $request): RedirectResponse
    {
        $fromRequest = self::extract($request);
        $params = $fromRequest !== [] ? $fromRequest : self::params();

        return to_route('transactions.index', $params);
    }

    /**
     * @return array<string, int|string>
     */
    private static function extract(Request $request): array
    {
        $filtered = [];

        foreach (self::ALLOWED_KEYS as $key) {
            $value = $request->query($key);

            if ($value === null || $value === '') {
                continue;
            }

            $filtered[$key] = $key === 'account_id' || $key === 'per_page'
                ? (int) $value
                : (string) $value;
        }

        return $filtered;
    }
}
```

- [ ] **Step 4: Run unit tests**

Run: `php artisan test --compact tests/Unit/Support/Transactions/TransactionsIndexQueryTest.php`  
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Support/Transactions/TransactionsIndexQueryTest.php app/Support/Transactions/TransactionsIndexQuery.php
git commit -m "feat(transactions): add TransactionsIndexQuery session helper"
```

---

### Task 2: Remember on index + controller redirects

**Files:**
- Modify: `app/Http/Controllers/Transactions/TransactionController.php`
- Modify: `app/Http/Controllers/Transfers/TransferController.php`
- Modify: `app/Http/Controllers/Imports/ImportController.php`

- [ ] **Step 1: Write failing feature tests**

Create `tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php`:

```php
<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionsIndexQuery;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('index visit remembers filters in session', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['currency_id' => $plnId]);

    $this->actingAs($user)->get(route('transactions.index', [
        'account_id' => $account->id,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
        'sort' => 'amount',
        'direction' => 'asc',
    ]))->assertOk();

    expect(session(TransactionsIndexQuery::sessionKey()))->toMatchArray([
        'account_id' => $account->id,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
        'sort' => 'amount',
        'direction' => 'asc',
    ]);
});

test('store redirects to index with remembered filters', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $this->actingAs($user)->get(route('transactions.index', [
        'account_id' => $account->id,
        'from' => '01-04-2026',
        'to' => '30-04-2026',
    ]))->assertOk();

    $this->actingAs($user)
        ->post('/transactions', [
            'account_id' => $account->id,
            'date' => '10-04-2026',
            'amount' => -10,
            'description' => 'Test',
        ])
        ->assertRedirect(route('transactions.index', [
            'account_id' => $account->id,
            'from' => '01-04-2026',
            'to' => '30-04-2026',
        ]));
});

test('destroy redirects to index with remembered filters', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 80,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-10',
        'booked_at' => '2026-04-10',
        'amount' => -20,
        'type' => 'expense',
        'description' => 'Expense',
        'subject' => null,
        'normalized_description' => 'expense',
        'dedupe_hash' => md5('x', true),
    ]);

    $this->actingAs($user)->get(route('transactions.index', [
        'from' => '01-04-2026',
        'to' => '30-04-2026',
    ]))->assertOk();

    $this->actingAs($user)
        ->delete("/transactions/{$transaction->id}")
        ->assertRedirect(route('transactions.index', [
            'from' => '01-04-2026',
            'to' => '30-04-2026',
        ]));
});

test('clearing filters updates session to sort-only state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('transactions.index', [
        'from' => '01-04-2026',
        'to' => '30-04-2026',
    ]))->assertOk();

    $this->actingAs($user)->get(route('transactions.index', [
        'sort' => 'date',
        'direction' => 'desc',
    ]))->assertOk();

    expect(session(TransactionsIndexQuery::sessionKey()))->toBe([
        'sort' => 'date',
        'direction' => 'desc',
    ]);
});
```

- [ ] **Step 2: Run feature tests — expect FAIL**

Run: `php artisan test --compact tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php`  
Expected: FAIL on redirects / session

- [ ] **Step 3: Wire controllers**

`TransactionController.php` — add import and calls:

```php
use App\Support\Transactions\TransactionsIndexQuery;
use Illuminate\Http\Request;
```

In `index()` before `return Inertia::render`:

```php
TransactionsIndexQuery::remember($request);
```

Replace `store` return:

```php
return TransactionsIndexQuery::redirect($request)->with('toast', [
    'type' => 'success',
    'message_key' => 'transactions.toast.created',
]);
```

Add `Request $request` to `destroy` signature if missing; replace return:

```php
return TransactionsIndexQuery::redirect($request)->with('toast', [
    'type' => 'success',
    'message_key' => 'transactions.toast.deleted',
]);
```

`TransferController@store`:

```php
return TransactionsIndexQuery::redirect($request)->with('toast', [
```

`ImportController` — replace each `to_route('transactions.index')` with `TransactionsIndexQuery::redirect($request)` (add `Request $request` to methods if not present).

- [ ] **Step 4: Run feature tests — expect PASS**

Run: `php artisan test --compact tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Transactions/TransactionController.php app/Http/Controllers/Transfers/TransferController.php app/Http/Controllers/Imports/ImportController.php tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php
git commit -m "feat(transactions): remember index filters and redirect with session"
```

---

### Task 3: Inertia shared prop

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php` (add one test)

- [ ] **Step 1: Add failing Inertia assertion**

Append to persistence test file:

```php
use Inertia\Testing\AssertableInertia as Assert;

test('inertia shares transactionsIndexSearch from session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('transactions.index', [
        'from' => '01-04-2026',
        'to' => '30-04-2026',
    ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactionsIndexSearch', '?from=01-04-2026&to=30-04-2026')
        );
});
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `php artisan test --compact --filter="inertia shares transactionsIndexSearch"

- [ ] **Step 3: Share prop in middleware**

```php
use App\Support\Transactions\TransactionsIndexQuery;
```

In `share()`:

```php
'transactionsIndexSearch' => fn () => TransactionsIndexQuery::toQueryString(),
```

- [ ] **Step 4: Run test — expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php
git commit -m "feat(transactions): share transactionsIndexSearch via Inertia"
```

---

### Task 4: Vue composable + types

**Files:**
- Create: `resources/js/composables/useTransactionsIndexSearch.ts`
- Modify: `resources/js/types/index.ts`

- [ ] **Step 1: Add composable**

```typescript
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

function formatDdMmYyyy(d: Date): string {
    return `${pad2(d.getDate())}-${pad2(d.getMonth() + 1)}-${d.getFullYear()}`;
}

export function defaultMonthSearch(): string {
    const now = new Date();
    const from = new Date(now.getFullYear(), now.getMonth(), 1);
    const to = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    return `?from=${encodeURIComponent(formatDdMmYyyy(from))}&to=${encodeURIComponent(formatDdMmYyyy(to))}`;
}

export function useTransactionsIndexSearch() {
    const page = usePage<{ transactionsIndexSearch?: string }>();

    const currentSearch = computed(() => {
        const url = page.url;
        const idx = url.indexOf('?');

        return idx >= 0 ? url.slice(idx) : '';
    });

    const transactionsIndexSearch = computed(() => {
        if (currentSearch.value !== '') {
            return currentSearch.value;
        }

        const shared = page.props.transactionsIndexSearch ?? '';

        return shared !== '' ? shared : '';
    });

    const transactionsIndexHref = computed(() => {
        const search = transactionsIndexSearch.value !== '' ? transactionsIndexSearch.value : defaultMonthSearch();

        return route('transactions.index') + search;
    });

    return {
        currentSearch,
        transactionsIndexSearch,
        transactionsIndexHref,
        defaultMonthSearch,
    };
}
```

- [ ] **Step 2: Extend SharedData type**

In `resources/js/types/index.ts` inside `SharedData`:

```typescript
transactionsIndexSearch?: string;
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/composables/useTransactionsIndexSearch.ts resources/js/types/index.ts
git commit -m "feat(transactions): add useTransactionsIndexSearch composable"
```

---

### Task 5: Wire Vue pages and components

**Files:**
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/pages/transactions/Edit.vue`
- Modify: `resources/js/pages/transactions/Create.vue`
- Modify: `resources/js/pages/transfers/Create.vue`
- Modify: `resources/js/pages/transactions/Index.vue`
- Modify: `resources/js/components/transactions/modals/DeleteTransactionDialog.vue`

- [ ] **Step 1: AppSidebar**

Remove local `currentMonthRangeQuery` / `formatDdMmYyyy` / `pad2` if moved to composable.

```typescript
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import { computed } from 'vue';

const { transactionsIndexHref } = useTransactionsIndexSearch();

const mainNavItems = computed<NavItem[]>(() => [
    // Dashboard, Konta unchanged...
    {
        title: 'Transakcje',
        href: transactionsIndexHref.value,
        icon: ArrowLeftRight,
    },
]);
```

Pass `mainNavItems` (computed) to `NavMain`.

- [ ] **Step 2: Edit.vue**

```typescript
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';

const { transactionsIndexSearch, transactionsIndexHref } = useTransactionsIndexSearch();
```

Remove local `currentSearch` computed.

Breadcrumb index item: `href: transactionsIndexHref.value` (inside computed).

Cancel `Link`: `:href="transactionsIndexHref"`.

`form.put(route('transactions.update', props.transaction.id) + transactionsIndexSearch.value, ...)`.

`onDeleted`: `router.visit(transactionsIndexHref.value)`.

- [ ] **Step 3: Create.vue**

Same composable; breadcrumb + cancel use `transactionsIndexHref`; post:

```typescript
form.post(route('transactions.store') + transactionsIndexSearch.value, { ... });
```

- [ ] **Step 4: transfers/Create.vue**

Composable for breadcrumb + cancel only (store redirect is server-side).

- [ ] **Step 5: Index.vue**

```typescript
const { currentSearch, transactionsIndexSearch } = useTransactionsIndexSearch();
```

Keep `currentSearch` for existing `+ currentSearch` links. Pass to dialog:

```vue
<DeleteTransactionDialog
    ...
    :return-search="transactionsIndexSearch"
/>
```

- [ ] **Step 6: DeleteTransactionDialog.vue**

```typescript
const props = withDefaults(
    defineProps<{
        // existing...
        returnSearch?: string;
    }>(),
    { returnSearch: '' },
);

form.delete(route('transactions.destroy', props.transactionId) + (props.returnSearch ?? ''), { ... });
```

- [ ] **Step 7: Manual smoke (optional)**

1. Open `/transactions?from=01-04-2026&to=30-04-2026&account_id=1`
2. Edit → breadcrumb → filters still applied
3. Go Accounts → sidebar Transakcje → same range
4. Create → save → same range

- [ ] **Step 8: Commit**

```bash
git add resources/js/components/AppSidebar.vue resources/js/pages/transactions/*.vue resources/js/pages/transfers/Create.vue resources/js/components/transactions/modals/DeleteTransactionDialog.vue
git commit -m "feat(transactions): persist index filters in UI navigation"
```

---

### Task 6: Format, regression tests, docs checkbox

**Files:**
- Modify: `docs/superpowers/specs/2026-06-02-transactions-index-filters-persistence-design.md` (optional — check acceptance boxes after QA)

- [ ] **Step 1: Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 2: Run transaction-related tests**

Run:

```bash
php artisan test --compact tests/Unit/Support/Transactions/TransactionsIndexQueryTest.php
php artisan test --compact tests/Feature/Transactions/TransactionsIndexFiltersPersistenceTest.php
php artisan test --compact tests/Feature/Transactions/
```

Expected: all PASS

- [ ] **Step 3: Update existing delete test if assertion too strict**

If `TransactionDeleteTest` asserts exact `assertRedirect('/transactions')`, change to:

```php
->assertRedirect(route('transactions.index'));
```

only when session empty; or seed session in that test. Prefer leaving unchanged if destroy without prior index visit still redirects to bare index.

- [ ] **Step 4: Final commit (if pint changed files)**

```bash
git add -A
git commit -m "chore: pint transactions index filters persistence"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|---|---|
| Session remember on index | Task 2 |
| Store/destroy/transfer/import redirects | Task 2 |
| Inertia `transactionsIndexSearch` | Task 3 |
| Composable + sidebar fallback month | Task 4–5 |
| Breadcrumbs Edit/Create/Transfer | Task 5 |
| Mutation URLs with search | Task 5 |
| Delete dialog return search | Task 5 |
| Clear filters updates session | Task 1–2 tests |
| No `page` on redirect | Task 1 `remember` excludes page |
| `update` unchanged | No task (explicit non-goal) |

## Acceptance criteria (manual QA)

- [ ] Index → Edit → breadcrumb → same filters
- [ ] Index → Edit → Cancel → same filters
- [ ] Index → Accounts → sidebar → same filters
- [ ] Create → Save → same filters
- [ ] Edit → Delete → same filters
- [ ] Index row delete → same filters
- [ ] Clear filters → sidebar → unfiltered list
- [ ] Fresh session → sidebar → current month
