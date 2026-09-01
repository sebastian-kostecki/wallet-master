<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositStatus;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\User;
use App\Support\Deposits\DepositCloser;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('deposit closer finds active deposits by principal and currency', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => '10000.00',
        'current_balance' => '10000.00',
    ]);

    Deposit::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'source_account_id' => $account->id,
        'name' => 'Lokata A',
        'principal' => '5000.00',
        'opened_at' => '2026-08-01',
        'status' => DepositStatus::Active,
    ]);

    Deposit::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'source_account_id' => $account->id,
        'name' => 'Lokata B',
        'principal' => '5000.00',
        'opened_at' => '2026-08-15',
        'status' => DepositStatus::Active,
    ]);

    Deposit::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'source_account_id' => $account->id,
        'name' => 'Lokata C',
        'principal' => '3000.00',
        'opened_at' => '2026-08-01',
        'status' => DepositStatus::Active,
    ]);

    Deposit::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'source_account_id' => $account->id,
        'name' => 'Closed',
        'principal' => '5000.00',
        'opened_at' => '2026-01-01',
        'status' => DepositStatus::Closed,
        'closed_at' => '2026-06-01',
    ]);

    $matches = app(DepositCloser::class)->matchingActiveDeposits($user, '5000.00', $plnId);

    expect($matches)->toHaveCount(2);
    expect($matches->pluck('principal')->unique()->all())->toBe(['5000.00']);
});
