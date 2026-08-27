<?php

declare(strict_types=1);

use App\Actions\Deposits\CloseDeposit;
use App\Actions\Deposits\CreateDeposit;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('closes deposit returning principal and optional interest', function () {
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
    $incomeCategory = Category::factory()->income()->for($user)->create();

    $deposit = app(CreateDeposit::class)->handle($user, [
        'source_account_id' => $account->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
        'name' => '3M',
    ]);

    $balanceBeforeClose = $account->fresh()->current_balance;
    expect($balanceBeforeClose)->toBe('4000.00');

    $closed = app(CloseDeposit::class)->handle($user, $deposit, [
        'destination_account_id' => $account->id,
        'closed_at' => '15-08-2026',
        'interest' => '15.00',
        'category_id' => $incomeCategory->id,
    ]);

    expect($closed->status)->toBe(DepositStatus::Closed);
    expect($closed->closed_at?->toDateString())->toBe('2026-08-15');
    expect($account->fresh()->current_balance)->toBe(
        bcadd($balanceBeforeClose, bcadd($deposit->principal, '15.00', 2), 2)
    );

    $newMovements = $closed->movements()
        ->with('transaction')
        ->whereIn('kind', [DepositMovementKind::ClosePrincipal, DepositMovementKind::Interest])
        ->get();

    expect($newMovements)->toHaveCount(2);

    $principalMovement = $newMovements->firstWhere('kind', DepositMovementKind::ClosePrincipal);
    expect($principalMovement)->not->toBeNull();
    expect($principalMovement->transaction->amount)->toBe('1000.00');
    expect($principalMovement->transaction->category_id)->toBeNull();

    $interestMovement = $newMovements->firstWhere('kind', DepositMovementKind::Interest);
    expect($interestMovement)->not->toBeNull();
    expect($interestMovement->transaction->amount)->toBe('15.00');
    expect($interestMovement->transaction->category_id)->toBe($incomeCategory->id);
});

test('close without interest does not require a category', function () {
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

    $closePrincipalCount = $deposit->fresh()
        ->movements()
        ->where('kind', DepositMovementKind::ClosePrincipal)
        ->count();

    expect($closePrincipalCount)->toBe(1);
    expect($deposit->fresh()->movements()->where('kind', DepositMovementKind::Interest)->count())->toBe(0);
});

test('cannot close twice', function () {
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

    expect(fn () => app(CloseDeposit::class)->handle($user, $deposit->fresh(), [
        'destination_account_id' => $account->id,
        'closed_at' => '16-08-2026',
        'interest' => '0',
    ]))->toThrow(ValidationException::class);
});
