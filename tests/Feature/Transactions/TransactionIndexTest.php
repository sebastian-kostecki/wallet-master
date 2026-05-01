<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
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
        ->where('summary.total_income', '100.00')
        ->where('summary.total_expense', '25.00')
    );

    CarbonImmutable::setTestNow();
});

test('users can filter transactions by import id', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'committed',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'import_id' => $import->id,
        'date' => '2026-04-10',
        'amount' => 10,
        'type' => 'income',
        'description' => 'Imported',
        'subject' => null,
        'raw_statement_description' => 'Imported',
        'normalized_description' => 'imported',
        'dedupe_hash' => md5('2026-04-10|10.00|imported', true),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => '2026-04-10',
        'amount' => 20,
        'type' => 'income',
        'description' => 'Not imported',
        'subject' => null,
        'normalized_description' => 'not imported',
        'dedupe_hash' => md5('2026-04-10|20.00|not imported', true),
    ]);

    $response = $this->actingAs($user)->get('/transactions?import_id='.$import->id.'&all_time=1');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->where('filters.import_id', $import->id)
        ->has('transactions.data', 1)
        ->where('transactions.data.0.description', 'Imported')
    );
});

test('date range is validated', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get('/transactions?from=20-04-2026&to=10-04-2026')
        ->assertSessionHasErrors('from');
});

test('defaults to this month when date range is missing', function () {
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
        ->where('filters.from', '01-04-2026')
        ->where('filters.to', '25-04-2026')
        ->where('filters.per_page', 15)
        ->has('transactions.data', 1)
        ->where('summary.total_income', '50.00')
        ->where('summary.total_expense', '0.00')
    );

    CarbonImmutable::setTestNow();
});

test('can request all time transactions when clearing date range', function () {
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
        'amount' => 50,
        'type' => 'income',
        'description' => 'April income',
        'subject' => null,
        'normalized_description' => 'april income',
        'dedupe_hash' => md5('2026-04-10|50.00|april income', true),
    ]);

    $response = $this->actingAs($user)->get('/transactions?all_time=1');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->where('filters.all_time', true)
        ->where('filters.from', null)
        ->where('filters.to', null)
        ->where('filters.per_page', 15)
        ->has('transactions.data', 2)
        ->where('summary.total_income', '150.00')
        ->where('summary.total_expense', '0.00')
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
            'amount' => $i,
            'type' => 'income',
            'description' => 'Tx '.$i,
            'subject' => null,
            'normalized_description' => 'tx '.$i,
            'dedupe_hash' => md5('2026-04-10|'.$i.'.00|tx '.$i, true),
        ]);
    }

    $response = $this->actingAs($user)->get('/transactions?per_page=25&all_time=1');

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
