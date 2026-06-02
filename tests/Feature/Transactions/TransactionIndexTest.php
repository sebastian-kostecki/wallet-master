<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\ImportFailedRowReason;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\ImportFailedRow;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('guests are redirected to login', function () {
    $this->get('/transactions')->assertRedirect('/login');
});

test('users can filter by account and date range, sort, and see summary', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 12, 12, 0, 0));

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $plnSymbol = Currency::query()->whereKey($plnId)->value('symbol');
    $user = User::factory()->create();

    $accountA = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $accountB = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'B',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'currency_id' => $plnId,
        'date' => '2026-04-10',
        'booked_at' => '2026-04-10',
        'amount' => 100,
        'type' => 'income',
        'description' => 'Salary',
        'subject' => null,
        'normalized_description' => 'salary',
        'dedupe_hash' => md5('2026-04-10|100.00|salary', true),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'currency_id' => $plnId,
        'date' => '2026-04-11',
        'booked_at' => '2026-04-11',
        'amount' => -25,
        'type' => 'expense',
        'description' => 'Groceries',
        'subject' => null,
        'normalized_description' => 'groceries',
        'dedupe_hash' => md5('2026-04-11|-25.00|groceries', true),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountB->id,
        'currency_id' => $plnId,
        'date' => '2026-04-11',
        'booked_at' => '2026-04-11',
        'amount' => -999,
        'type' => 'expense',
        'description' => 'Other account',
        'subject' => null,
        'normalized_description' => 'other account',
        'dedupe_hash' => md5('2026-04-11|-999.00|other account', true),
    ]);

    $response = $this
        ->actingAs($user)
        ->get('/transactions?account_id='.$accountA->id.'&from=10-04-2026&to=11-04-2026&sort=amount&direction=asc');

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->where('filters.account_id', $accountA->id)
        ->where('filters.from', '10-04-2026')
        ->where('filters.to', '11-04-2026')
        ->where('filters.sort', 'amount')
        ->where('filters.direction', 'asc')
        ->where('filters.per_page', 15)
        ->has('transactions.data', 2)
        ->where('transactions.data.0.date_relative', '1 dzień temu')
        ->where('transactions.data.0.account.type_label_key', 'accounts.enums.accountType.checking')
        ->where('transactions.data.0.account.bank_icon_url', fn (mixed $value) => is_string($value) && str_contains($value, '/icons/banks/mbank.jpeg'))
        ->where('transactions.data.0.account.currency.symbol', $plnSymbol)
        ->where('accounts.0.currency.symbol', $plnSymbol)
        ->where('summary.total_income', 100)
        ->where('summary.total_expense', -25)
        ->where('transactions.data.0.amount', '-25.00')
        ->where('transactions.data.1.amount', '100.00')
    );

    CarbonImmutable::setTestNow();
});

test('date_relative is dzisiaj when booked_at is today', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 12, 12, 0, 0));

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-12',
        'booked_at' => '2026-04-12',
        'amount' => -10,
        'type' => 'expense',
        'description' => 'Today booking',
        'subject' => null,
        'normalized_description' => 'today booking',
        'dedupe_hash' => md5('2026-04-12|-10.00|today booking', true),
    ]);

    $response = $this
        ->actingAs($user)
        ->get('/transactions?account_id='.$account->id.'&from=12-04-2026&to=12-04-2026');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->has('transactions.data', 1)
        ->where('transactions.data.0.date_relative', 'dzisiaj')
    );

    CarbonImmutable::setTestNow();
});

test('date range is validated', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get('/transactions?from=20-04-2026&to=10-04-2026')
        ->assertSessionHasErrors('from');
});

test('returns all transactions when date range is missing', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 25, 12, 0, 0));

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-03-31',
        'booked_at' => '2026-03-31',
        'amount' => 100,
        'type' => 'income',
        'description' => 'March income',
        'subject' => null,
        'normalized_description' => 'march income',
        'dedupe_hash' => md5('2026-03-31|100.00|march income', true),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-10',
        'booked_at' => '2026-04-10',
        'amount' => 50,
        'type' => 'income',
        'description' => 'April income',
        'subject' => null,
        'normalized_description' => 'april income',
        'dedupe_hash' => md5('2026-04-10|50.00|april income', true),
    ]);

    $response = $this->actingAs($user)->get('/transactions');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->where('filters.from', null)
        ->where('filters.to', null)
        ->where('filters.per_page', 15)
        ->has('transactions.data', 2)
        ->where('summary.total_income', 150)
        ->where('summary.total_expense', 0)
    );

    CarbonImmutable::setTestNow();
});

test('users can change per-page size', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 25, 12, 0, 0));

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    foreach (range(1, 30) as $i) {
        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'currency_id' => $plnId,
            'date' => '2026-04-10',
            'booked_at' => '2026-04-10',
            'amount' => $i,
            'type' => 'income',
            'description' => 'Tx '.$i,
            'subject' => null,
            'normalized_description' => 'tx '.$i,
            'dedupe_hash' => md5('2026-04-10|'.$i.'.00|tx '.$i, true),
        ]);
    }

    $response = $this->actingAs($user)->get('/transactions?per_page=25');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->where('filters.per_page', 25)
        ->has('transactions.data', 25)
    );

    CarbonImmutable::setTestNow();
});

test('per-page size is validated', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get('/transactions?per_page=9999')
        ->assertSessionHasErrors('per_page');
});

test('index renders when user has no transactions', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/transactions');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->where('transactions.meta.total', 0)
        ->has('transactions.data', 0)
        ->where('summary.total_income', 0)
        ->where('summary.total_expense', 0)
    );
});

test('index renders when filters match no transactions', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 12, 12, 0, 0));

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-10',
        'booked_at' => '2026-04-10',
        'amount' => 50,
        'type' => 'income',
        'description' => 'April income',
        'subject' => null,
        'normalized_description' => 'april income',
        'dedupe_hash' => md5('2026-04-10|50.00|april income', true),
    ]);

    $response = $this
        ->actingAs($user)
        ->get('/transactions?from=01-05-2026&to=31-05-2026');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->where('transactions.meta.total', 0)
        ->has('transactions.data', 0)
        ->where('summary.total_income', 0)
        ->where('summary.total_expense', 0)
    );

    CarbonImmutable::setTestNow();
});

test('transactions index includes unresolved import failed rows and respects account filter', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $accountA = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $accountB = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'B',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'status' => 'committed',
    ]);

    $rowA = ImportFailedRow::query()->create([
        'import_id' => $import->id,
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'row_number' => 1,
        'reason_code' => ImportFailedRowReason::InvalidDate,
        'date_raw' => 'bad',
        'amount_raw' => '-10.00',
        'description_raw' => 'Coffee',
    ]);

    ImportFailedRow::query()->create([
        'import_id' => $import->id,
        'user_id' => $user->id,
        'account_id' => $accountB->id,
        'row_number' => 2,
        'reason_code' => ImportFailedRowReason::InvalidAmount,
        'date_raw' => '24-04-2026',
        'amount_raw' => 'x',
        'description_raw' => 'Shop',
        'dismissed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/transactions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index', false)
            ->has('unresolved_import_failed_rows', 1)
            ->where('unresolved_import_failed_rows.0.id', $rowA->id)
        );

    $this->actingAs($user)
        ->get('/transactions?account_id='.$accountA->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('unresolved_import_failed_rows', 1)
            ->where('unresolved_import_failed_rows.0.account_id', $accountA->id)
        );

    $this->actingAs($user)
        ->get('/transactions?account_id='.$accountB->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('unresolved_import_failed_rows', 0)
        );
});
