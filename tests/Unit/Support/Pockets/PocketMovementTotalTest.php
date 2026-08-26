<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\PocketMovement;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Pocket;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Pockets\PocketMovementTotal;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('counts a checking-to-checking deposit once not twice', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'currency_id' => $plnId]);
    $from = Account::query()->create([
        'user_id' => $user->id, 'currency_id' => $plnId, 'name' => 'A',
        'bank' => Bank::Cash, 'type' => AccountType::Checking,
        'opening_balance' => 200, 'current_balance' => 200,
    ]);
    $to = Account::query()->create([
        'user_id' => $user->id, 'currency_id' => $plnId, 'name' => 'B',
        'bank' => Bank::Cash, 'type' => AccountType::Checking,
        'opening_balance' => 0, 'current_balance' => 0,
    ]);
    $transferId = (string) Str::uuid();
    foreach ([[$from, '-200.00'], [$to, '200.00']] as [$account, $amount]) {
        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'currency_id' => $plnId,
            'category_id' => null,
            'pocket_id' => $pocket->id,
            'pocket_movement' => PocketMovement::Deposit,
            'date' => '2026-04-01',
            'booked_at' => '2026-04-01',
            'amount' => $amount,
            'type' => TransactionType::Transfer,
            'description' => 'Allocate',
            'normalized_description' => 'allocate',
            'dedupe_hash' => md5($transferId.$amount, true),
            'transfer_id' => $transferId,
        ]);
    }

    expect(PocketMovementTotal::sum($user, $pocket, PocketMovement::Deposit))->toBe('200.00');
    expect(PocketMovementTotal::sum($user, $pocket, PocketMovement::Withdrawal))->toBe('0.00');
});

test('counts a checking-to-checking withdrawal once not twice', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id, 'currency_id' => $plnId]);
    $from = Account::query()->create([
        'user_id' => $user->id, 'currency_id' => $plnId, 'name' => 'A',
        'bank' => Bank::Cash, 'type' => AccountType::Checking,
        'opening_balance' => 200, 'current_balance' => 200,
    ]);
    $to = Account::query()->create([
        'user_id' => $user->id, 'currency_id' => $plnId, 'name' => 'B',
        'bank' => Bank::Cash, 'type' => AccountType::Checking,
        'opening_balance' => 0, 'current_balance' => 0,
    ]);
    $transferId = (string) Str::uuid();
    foreach ([[$from, '-200.00'], [$to, '200.00']] as [$account, $amount]) {
        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'currency_id' => $plnId,
            'category_id' => null,
            'pocket_id' => $pocket->id,
            'pocket_movement' => PocketMovement::Withdrawal,
            'date' => '2026-04-01',
            'booked_at' => '2026-04-01',
            'amount' => $amount,
            'type' => TransactionType::Transfer,
            'description' => 'Release',
            'normalized_description' => 'release',
            'dedupe_hash' => md5($transferId.$amount, true),
            'transfer_id' => $transferId,
        ]);
    }

    expect(PocketMovementTotal::sum($user, $pocket, PocketMovement::Withdrawal))->toBe('200.00');
    expect(PocketMovementTotal::sum($user, $pocket, PocketMovement::Deposit))->toBe('0.00');
});
