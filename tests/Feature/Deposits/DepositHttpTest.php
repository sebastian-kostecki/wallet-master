<?php

declare(strict_types=1);

use App\Actions\Deposits\CreateDeposit;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function depositHttpBankAccount(User $user, string $balance = '5000.00'): Account
{
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    return Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => $balance,
        'current_balance' => $balance,
    ]);
}

test('guest cannot store a deposit', function () {
    $this->post(route('deposits.store', absolute: false), [
        'source_account_id' => 1,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
    ])->assertRedirect(route('login', absolute: false));
});

test('user can store a deposit via HTTP and lands on kind=deposits', function () {
    $user = User::factory()->create();
    $account = depositHttpBankAccount($user);

    $response = $this->actingAs($user)->post(route('deposits.store', absolute: false), [
        'source_account_id' => $account->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
        'name' => '3M',
        'matures_at' => '01-11-2026',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('accounts.index', ['kind' => 'deposits'], absolute: false));
    $response->assertSessionHas('toast.message_key', 'deposits.toast.created');

    $deposit = Deposit::query()->where('user_id', $user->id)->first();
    expect($deposit)->not->toBeNull();
    expect($deposit->status)->toBe(DepositStatus::Active);
    expect($deposit->principal)->toBe('1000.00');
});

test('cash wallet is 422', function () {
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

    $this->actingAs($user)
        ->post(route('deposits.store', absolute: false), [
            'source_account_id' => $cash->id,
            'principal' => '1000.00',
            'opened_at' => '01-08-2026',
        ])
        ->assertSessionHasErrors('source_account_id');
});

test('assign open via POST', function () {
    $user = User::factory()->create();
    $account = depositHttpBankAccount($user);
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $date = '2026-08-01';
    $description = 'Term deposit open';
    $amount = '-1500.00';
    $normalizedDescription = TransactionDedupe::normalizeDescription($description);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => $date,
        'booked_at' => $date,
        'amount' => $amount,
        'type' => TransactionType::Expense,
        'description' => $description,
        'subject' => null,
        'normalized_description' => $normalizedDescription,
        'dedupe_hash' => TransactionDedupe::manualDedupeHash($date, $amount, $normalizedDescription),
        'category_id' => null,
    ]);

    $response = $this->actingAs($user)->post(
        route('deposits.assign', $transaction, absolute: false),
        [
            'kind' => DepositMovementKind::Open->value,
            'name' => 'Assigned deposit',
            'matures_at' => '01-11-2026',
        ],
    );

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('transactions.index', absolute: false));
    $response->assertSessionHas('toast.message_key', 'deposits.toast.assigned');

    $deposit = Deposit::query()->where('user_id', $user->id)->first();
    expect($deposit)->not->toBeNull();
    expect($deposit->principal)->toBe('1500.00');
    expect($deposit->name)->toBe('Assigned deposit');
    expect($transaction->fresh()->depositMovement?->kind)->toBe(DepositMovementKind::Open);
});

test('other user 403 on close', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = depositHttpBankAccount($owner);

    $deposit = app(CreateDeposit::class)->handle($owner, [
        'source_account_id' => $account->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
    ]);

    $this->actingAs($other)
        ->post(route('deposits.close', $deposit, absolute: false), [
            'destination_account_id' => $account->id,
            'closed_at' => '15-08-2026',
            'interest' => '0',
        ])
        ->assertForbidden();
});
