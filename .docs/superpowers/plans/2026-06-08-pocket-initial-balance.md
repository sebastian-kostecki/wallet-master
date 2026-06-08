# Pocket initial balance — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow users to set an optional initial balance when creating a pocket so cumulative metrics reflect money already saved, without creating transfers or changing account balances.

**Architecture:** Add `pockets.initial_balance` (default `0`), writable only in `StorePocket`. Extend `PocketBalance::cumulative()` to add initial to transfer net. Expose field in `PocketResource`; Create form accepts it; Edit shows read-only when `> 0`. Monthly budget movement columns unchanged.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v2, Vue 3, Sail for tests/migrations.

**Spec:** `.docs/superpowers/specs/2026-06-08-pocket-initial-balance-design.md`

**Suggested branch:** `improvement/pocket-initial-balance`

---

## File map

| Action | Path |
|--------|------|
| Create | `database/migrations/2026_06_08_100000_add_initial_balance_to_pockets_table.php` |
| Modify | `app/Models/Pocket.php` |
| Modify | `database/factories/PocketFactory.php` |
| Modify | `app/Support/Pockets/PocketBalance.php` |
| Modify | `app/Http/Requests/Pockets/StorePocketRequest.php` |
| Modify | `app/Http/Requests/Pockets/UpdatePocketRequest.php` |
| Modify | `app/Actions/Pockets/StorePocket.php` |
| Modify | `app/Http/Resources/Pockets/PocketResource.php` |
| Modify | `resources/js/pages/pockets/Create.vue` |
| Modify | `resources/js/pages/pockets/Edit.vue` |
| Modify | `resources/js/locales/pl.json`, `en.json` |
| Modify | `tests/Feature/Pockets/PocketCrudTest.php` |
| Modify | `tests/Unit/Support/Pockets/PocketBalanceTest.php` |
| Modify | `.docs/checklist.md` |

---

### Task 1: Migration and model

**Files:**
- Create: `database/migrations/2026_06_08_100000_add_initial_balance_to_pockets_table.php`
- Modify: `app/Models/Pocket.php`
- Modify: `database/factories/PocketFactory.php`

- [ ] **Step 1: Create migration**

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
        Schema::table('pockets', function (Blueprint $table) {
            $table->decimal('initial_balance', 12, 2)->default(0)->after('currency_id');
        });
    }

    public function down(): void
    {
        Schema::table('pockets', function (Blueprint $table) {
            $table->dropColumn('initial_balance');
        });
    }
};
```

- [ ] **Step 2: Update Pocket model**

In `app/Models/Pocket.php`:

Add to `@property` docblock: `@property string $initial_balance`

Add to `$fillable`: `'initial_balance'`

Add to `casts()`: `'initial_balance' => 'decimal:2'`

- [ ] **Step 3: Update PocketFactory**

In `database/factories/PocketFactory.php` definition array, add:

```php
'initial_balance' => '0.00',
```

- [ ] **Step 4: Run migration**

Run: `./vendor/bin/sail artisan migrate --no-interaction`

Expected: migration applied without errors.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_08_100000_add_initial_balance_to_pockets_table.php app/Models/Pocket.php database/factories/PocketFactory.php
git commit -m "feat(pockets): add initial_balance column"
```

---

### Task 2: PocketBalance — include initial in cumulative balance

**Files:**
- Modify: `app/Support/Pockets/PocketBalance.php`
- Modify: `tests/Unit/Support/Pockets/PocketBalanceTest.php`

- [ ] **Step 1: Write failing unit test**

Append to `tests/Unit/Support/Pockets/PocketBalanceTest.php`:

```php
test('cumulative balance includes initial_balance without transfers', function () {
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'initial_balance' => '500.00',
    ]);

    $result = PocketBalance::cumulative($user, $pocket);

    expect($result)->toBe([
        'saved_total' => '0.00',
        'released_total' => '0.00',
        'balance' => '500.00',
    ]);
});

test('cumulative balance adds initial_balance to transfer net', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'initial_balance' => '500.00',
    ]);
    $savings = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Savings',
        'bank' => Bank::Cash,
        'type' => AccountType::Savings,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savings->id,
        'currency_id' => $plnId,
        'category_id' => null,
        'pocket_id' => $pocket->id,
        'date' => '2026-01-15',
        'booked_at' => '2026-01-15',
        'amount' => '200.00',
        'type' => TransactionType::Transfer,
        'description' => 'Save',
        'normalized_description' => 'save',
        'dedupe_hash' => md5('save-with-initial', true),
        'transfer_id' => (string) Str::uuid(),
    ]);

    $result = PocketBalance::cumulative($user, $pocket);

    expect($result['saved_total'])->toBe('200.00');
    expect($result['released_total'])->toBe('0.00');
    expect($result['balance'])->toBe('700.00');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Pockets/PocketBalanceTest.php --filter=initial`

Expected: FAIL — balance `200.00` instead of `700.00` on second test; first test balance `0.00`.

- [ ] **Step 3: Update PocketBalance::cumulative**

In `app/Support/Pockets/PocketBalance.php`, replace the balance calculation:

```php
$releasedTotal = TransactionDedupe::amountToDecimalString((string) $releasedSum);
$transferNet = bcsub($savedTotal, $releasedTotal, 2);
$balance = bcadd((string) $pocket->initial_balance, $transferNet, 2);

return [
    'saved_total' => $savedTotal,
    'released_total' => $releasedTotal,
    'balance' => $balance,
];
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Pockets/PocketBalanceTest.php`

Expected: all PASS (existing test still expects `balance => 200.00` with default factory `initial_balance = 0`).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Pockets/PocketBalance.php tests/Unit/Support/Pockets/PocketBalanceTest.php
git commit -m "feat(pockets): include initial_balance in cumulative balance"
```

---

### Task 3: Store request, action, and feature tests

**Files:**
- Modify: `app/Http/Requests/Pockets/StorePocketRequest.php`
- Modify: `app/Actions/Pockets/StorePocket.php`
- Modify: `tests/Feature/Pockets/PocketCrudTest.php`

- [ ] **Step 1: Write failing feature tests**

Append to `tests/Feature/Pockets/PocketCrudTest.php`:

```php
test('user can create pocket with initial balance', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
        'initial_balance' => '1500.50',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $pocket = Pocket::query()->where('user_id', $user->id)->where('name', 'Wakacje')->first();

    expect($pocket)->not->toBeNull();
    expect((string) $pocket->initial_balance)->toBe('1500.50');
});

test('create pocket without initial balance defaults to zero', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Bez kwoty',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $pocket = Pocket::query()->where('user_id', $user->id)->where('name', 'Bez kwoty')->first();

    expect((string) $pocket->initial_balance)->toBe('0.00');
});

test('update pocket cannot change initial balance', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'initial_balance' => '500.00',
    ]);

    $this->actingAs($user)->patch(route('pockets.update', $pocket), [
        'name' => $pocket->name,
        'icon' => $pocket->icon,
        'color' => $pocket->color,
        'initial_balance' => '999.00',
    ])->assertSessionHasErrors('initial_balance');

    expect((string) $pocket->fresh()->initial_balance)->toBe('500.00');
});

test('pockets index balance includes initial balance', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Start',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
        'initial_balance' => '300',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->get(route('pockets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pockets', 1)
            ->where('pockets.0.balance', '300.00')
        );
});
```

- [ ] **Step 2: Run tests to verify failure**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketCrudTest.php --filter=initial`

Expected: FAIL (validation or persistence missing).

- [ ] **Step 3: Update StorePocketRequest**

In `app/Http/Requests/Pockets/StorePocketRequest.php`, extend `prepareForValidation()`:

```php
protected function prepareForValidation(): void
{
    if ($this->input('initial_balance') === '' || $this->input('initial_balance') === null) {
        $this->merge(['initial_balance' => 0]);
    }

    if ($this->input('target_amount') === '' || $this->input('target_amount') === null) {
        $this->merge([
            'target_amount' => null,
            'planning_mode' => null,
            'monthly_contribution' => null,
            'target_date' => null,
        ]);
    }
}
```

Add to `rules()`:

```php
'initial_balance' => ['nullable', 'numeric', 'min:0'],
```

- [ ] **Step 4: Update UpdatePocketRequest**

In `app/Http/Requests/Pockets/UpdatePocketRequest.php` `rules()`, add:

```php
'initial_balance' => ['prohibited'],
```

- [ ] **Step 5: Update StorePocket**

In `app/Actions/Pockets/StorePocket.php` create array, add:

```php
'initial_balance' => $validated['initial_balance'] ?? 0,
```

- [ ] **Step 6: Run feature tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketCrudTest.php --filter=initial`

Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Pockets/StorePocketRequest.php app/Http/Requests/Pockets/UpdatePocketRequest.php app/Actions/Pockets/StorePocket.php tests/Feature/Pockets/PocketCrudTest.php
git commit -m "feat(pockets): persist initial_balance on create only"
```

---

### Task 4: PocketResource

**Files:**
- Modify: `app/Http/Resources/Pockets/PocketResource.php`

- [ ] **Step 1: Expose initial_balance in API**

In `app/Http/Resources/Pockets/PocketResource.php` return array, after `currency_id`:

```php
'initial_balance' => (string) $this->initial_balance,
```

- [ ] **Step 2: Run pocket tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketCrudTest.php --filter="index balance includes"`

Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add app/Http/Resources/Pockets/PocketResource.php
git commit -m "feat(pockets): expose initial_balance in PocketResource"
```

---

### Task 5: Frontend Create and Edit

**Files:**
- Modify: `resources/js/pages/pockets/Create.vue`
- Modify: `resources/js/pages/pockets/Edit.vue`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add i18n keys**

In `resources/js/locales/pl.json` under `pockets.fields`:

```json
"initialBalance": {
    "label": "Wstępna kwota",
    "hint": "Kwota już odłożona na ten cel. Nie tworzy transferu ani nie zmienia salda kont."
}
```

In `resources/js/locales/en.json` under `pockets.fields`:

```json
"initialBalance": {
    "label": "Initial amount",
    "hint": "Amount already saved toward this goal. Does not create a transfer or change account balances."
}
```

- [ ] **Step 2: Update Create.vue**

Add to `useForm` initial state:

```typescript
initial_balance: '',
```

Add form field **after** currency selector, **before** target amount:

```vue
<FormField
    for-id="initial_balance"
    :label="t('pockets.fields.initialBalance.label')"
    :hint="t('pockets.fields.initialBalance.hint')"
    :error="form.errors.initial_balance"
>
    <template #default="{ errorId, hasError, hintId }">
        <div class="relative">
            <Input
                id="initial_balance"
                v-model="form.initial_balance"
                type="text"
                inputmode="decimal"
                class="pr-10"
                :aria-invalid="hasError ? true : undefined"
                :aria-describedby="[hasError ? errorId : null, hintId].filter(Boolean).join(' ') || undefined"
            />
            <span
                v-if="selectedCurrency?.symbol"
                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
            >
                {{ selectedCurrency.symbol }}
            </span>
        </div>
    </template>
</FormField>
```

- [ ] **Step 3: Update Edit.vue**

Extend `Pocket` type:

```typescript
initial_balance: string;
```

Add read-only block after currency field (only when value > 0):

```vue
<div
    v-if="Number(pocket.initial_balance.replace(',', '.')) > 0"
    class="grid gap-1 rounded-lg border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border"
>
    <p class="text-muted-foreground">
        {{ t('pockets.fields.initialBalance.label') }}:
        <span class="font-medium text-foreground">{{ formatMoney(pocket.initial_balance, pocket.currency) }}</span>
    </p>
    <p class="text-xs text-muted-foreground">{{ t('pockets.fields.initialBalance.hint') }}</p>
</div>
```

- [ ] **Step 4: Manual smoke check**

Run: `./vendor/bin/sail npm run build` (or confirm dev server running).

Visit `/pockets/create` — field visible with hint. Create pocket with initial amount; index shows correct balance. Edit shows read-only initial when > 0.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/pockets/Create.vue resources/js/pages/pockets/Edit.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "feat(pockets): initial balance field on create, read-only on edit"
```

---

### Task 6: Checklist and verification

**Files:**
- Modify: `.docs/checklist.md`

- [ ] **Step 1: Update checklist**

Under section 19 (Pockets), add item:

```markdown
- [x] Wstępna kwota przy tworzeniu kieszeni (`initial_balance`; tylko create; spec `2026-06-08-pocket-initial-balance-design.md`)
```

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 3: Run full pocket test suite**

Run: `./vendor/bin/sail artisan test --compact --filter=Pocket`

Expected: all PASS

- [ ] **Step 4: Commit**

```bash
git add .docs/checklist.md
git commit -m "docs: checklist pocket initial balance"
```

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| `initial_balance` column, default 0 | Task 1 |
| Balance formula includes initial | Task 2 |
| Store validation, create-only write | Task 3 |
| Update prohibited | Task 3 |
| PocketResource exposes field | Task 4 |
| Create UI + hint | Task 5 |
| Edit read-only when > 0 | Task 5 |
| Monthly budget unchanged | No code change (by design) |
| i18n PL/EN | Task 5 |
| Tests | Tasks 2, 3 |
| Checklist | Task 6 |

## Out of scope (confirmed)

- Editing initial balance after create
- Synthetic transactions
- Account balance changes
- Monthly budget movement columns
