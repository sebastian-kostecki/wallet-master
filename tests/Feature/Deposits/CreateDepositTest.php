<?php

declare(strict_types=1);

use App\Actions\Deposits\CreateDeposit;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositStatus;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('create deposit posts capital out of the source account', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => '5000.00',
        'current_balance' => '5000.00',
    ]);

    $deposit = app(CreateDeposit::class)->handle($user, [
        'source_account_id' => $account->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
        'name' => '3M',
        'matures_at' => '01-11-2026',
    ]);

    expect($deposit->status)->toBe(DepositStatus::Active);
    expect($deposit->principal)->toBe('1000.00');
    expect($deposit->source_account_id)->toBe($account->id);
    expect($account->fresh()->current_balance)->toBe('4000.00');

    $tx = $deposit->movements()->firstOrFail()->transaction;
    expect($tx->amount)->toBe('-1000.00');
    expect($tx->category_id)->toBeNull();
    expect($tx->transfer_id)->toBeNull();
    expect($tx->pocket_id)->toBeNull();
});

test('create deposit rejects a cash wallet source', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $cash = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Gotówka',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => '5000.00',
        'current_balance' => '5000.00',
    ]);

    expect(fn () => app(CreateDeposit::class)->handle($user, [
        'source_account_id' => $cash->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
    ]))->toThrow(ValidationException::class);
});
