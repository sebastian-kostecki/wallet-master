<?php

declare(strict_types=1);

use App\Actions\Deposits\CloseDeposit;
use App\Actions\Deposits\CreateDeposit;
use App\Actions\Deposits\UpdateDepositDetails;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('update name and matures_at on an active deposit', function () {
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
        'name' => 'Old name',
        'matures_at' => '01-11-2026',
    ]);

    $updated = app(UpdateDepositDetails::class)->handle($deposit, [
        'name' => '  Updated name  ',
        'matures_at' => '15-12-2026',
    ]);

    expect($updated->name)->toBe('Updated name');
    expect($updated->matures_at?->toDateString())->toBe('2026-12-15');
});

test('cannot update a closed deposit', function () {
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
    ]);

    app(CloseDeposit::class)->handle($user, $deposit, [
        'destination_account_id' => $account->id,
        'closed_at' => '15-08-2026',
        'interest' => '0',
    ]);

    expect(fn () => app(UpdateDepositDetails::class)->handle($deposit->fresh(), [
        'name' => 'Too late',
    ]))->toThrow(ValidationException::class);
});
