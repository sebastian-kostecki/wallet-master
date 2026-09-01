<?php

declare(strict_types=1);

use App\Actions\Deposits\CreateDeposit;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function depositIndexBankAccount(User $user): Account
{
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    return Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => '5000.00',
        'current_balance' => '5000.00',
    ]);
}

test('index with kind=deposits lists deposits and not the other users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $accountA = depositIndexBankAccount($userA);
    $accountB = depositIndexBankAccount($userB);

    Category::factory()->income()->for($userA)->create(['name' => 'Odsetki']);

    $depositA = app(CreateDeposit::class)->handle($userA, [
        'source_account_id' => $accountA->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
        'name' => 'Lokata A',
    ]);

    app(CreateDeposit::class)->handle($userB, [
        'source_account_id' => $accountB->id,
        'principal' => '2000.00',
        'opened_at' => '02-08-2026',
        'name' => 'Lokata B',
    ]);

    $response = $this->actingAs($userA)->get(route('accounts.index', ['kind' => 'deposits'], absolute: false));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('accounts', [])
        ->has('deposits', 1)
        ->where('deposits.0.id', $depositA->id)
        ->where('deposits.0.name', 'Lokata A')
        ->where('deposits.0.principal', '1000.00')
        ->where('deposits.0.source_account_id', $accountA->id)
        ->has('bank_accounts', 1)
        ->where('bank_accounts.0.id', $accountA->id)
        ->has('income_categories', 1)
        ->where('income_categories.0.name', 'Odsetki')
    );
});

test('index without kind still lists accounts and has deposits empty or absent', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'mBank Account',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => '100.00',
        'current_balance' => '100.00',
    ]);

    $response = $this->actingAs($user)->get(route('accounts.index', absolute: false));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('accounts', 1)
        ->where('accounts.0.id', $account->id)
        ->where('deposits', [])
        ->where('bank_accounts', [])
        ->where('income_categories', [])
    );
});
