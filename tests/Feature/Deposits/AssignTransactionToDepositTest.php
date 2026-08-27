<?php

declare(strict_types=1);

use App\Actions\Deposits\AssignTransactionToDeposit;
use App\Actions\Deposits\CreateDeposit;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\DepositMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Database\Seeders\CurrencySeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function createBankAccount(User $user, string $balance = '5000.00'): Account
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

function createExpenseTransaction(
    User $user,
    Account $account,
    string $amount,
    ?int $categoryId = null,
    array $overrides = [],
): Transaction {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $date = $overrides['date'] ?? '2026-08-01';
    $description = $overrides['description'] ?? 'Term deposit open';
    $normalizedDescription = TransactionDedupe::normalizeDescription($description);

    return Transaction::query()->create(array_merge([
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
        'category_id' => $categoryId,
    ], $overrides));
}

function createIncomeTransaction(
    User $user,
    Account $account,
    string $amount,
    ?int $categoryId = null,
    array $overrides = [],
): Transaction {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $date = $overrides['date'] ?? '2026-08-15';
    $description = $overrides['description'] ?? 'Term deposit return';
    $normalizedDescription = TransactionDedupe::normalizeDescription($description);

    return Transaction::query()->create(array_merge([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'date' => $date,
        'booked_at' => $date,
        'amount' => $amount,
        'type' => TransactionType::Income,
        'description' => $description,
        'subject' => null,
        'normalized_description' => $normalizedDescription,
        'dedupe_hash' => TransactionDedupe::manualDedupeHash($date, $amount, $normalizedDescription),
        'category_id' => $categoryId,
    ], $overrides));
}

test('assign open links an existing expense to a new deposit without changing balance', function () {
    $user = User::factory()->create();
    $account = createBankAccount($user);
    $category = Category::factory()->expense()->for($user)->create();
    $transaction = createExpenseTransaction($user, $account, '-2000.00', $category->id);

    $balanceBefore = $account->fresh()->current_balance;

    $logged = captureTelemetryLogs(function () use ($user, $transaction): void {
        app(AssignTransactionToDeposit::class)->handle($user, $transaction, [
            'kind' => 'open',
            'name' => '6M',
            'matures_at' => '01-02-2027',
        ]);
    });

    $transaction->refresh();
    expect($transaction->category_id)->toBeNull();
    expect($account->fresh()->current_balance)->toBe($balanceBefore);

    $movement = $transaction->depositMovement;
    expect($movement)->not->toBeNull();
    expect($movement->kind)->toBe(DepositMovementKind::Open);

    $deposit = $movement->deposit;
    expect($deposit->status)->toBe(DepositStatus::Active);
    expect($deposit->principal)->toBe('2000.00');
    expect($deposit->source_account_id)->toBe($account->id);
    expect($deposit->opened_at?->toDateString())->toBe('2026-08-01');
    expect($deposit->name)->toBe('6M');
    expect($deposit->matures_at?->toDateString())->toBe('2027-02-01');

    assertTelemetryEvent($logged, 'deposit_transaction_assigned', function (array $context) use ($user, $deposit): bool {
        return $context['kind'] === 'open'
            && $context['deposit_id'] === $deposit->id
            && $context['user_id'] === $user->id;
    });
});

test('assign close principal closes an active deposit without changing balance', function () {
    $user = User::factory()->create();
    $account = createBankAccount($user);

    $deposit = app(CreateDeposit::class)->handle($user, [
        'source_account_id' => $account->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
        'name' => '3M',
    ]);

    $category = Category::factory()->income()->for($user)->create();
    $transaction = createIncomeTransaction($user, $account, '1000.00', $category->id, [
        'date' => '2026-08-15',
    ]);

    $balanceBefore = $account->fresh()->current_balance;

    $logged = captureTelemetryLogs(function () use ($user, $transaction, $deposit): void {
        app(AssignTransactionToDeposit::class)->handle($user, $transaction, [
            'kind' => 'close_principal',
            'deposit_id' => $deposit->id,
        ]);
    });

    $transaction->refresh();
    expect($transaction->category_id)->toBeNull();
    expect($account->fresh()->current_balance)->toBe($balanceBefore);

    $movement = $transaction->depositMovement;
    expect($movement)->not->toBeNull();
    expect($movement->kind)->toBe(DepositMovementKind::ClosePrincipal);
    expect($movement->deposit_id)->toBe($deposit->id);

    $closed = $deposit->fresh();
    expect($closed->status)->toBe(DepositStatus::Closed);
    expect($closed->closed_at?->toDateString())->toBe('2026-08-15');

    assertTelemetryEvent($logged, 'deposit_transaction_assigned', function (array $context): bool {
        return $context['kind'] === 'close_principal';
    });
});

test('assign interest links income to a deposit and keeps category', function () {
    $user = User::factory()->create();
    $account = createBankAccount($user);

    $deposit = app(CreateDeposit::class)->handle($user, [
        'source_account_id' => $account->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
    ]);

    $category = Category::factory()->income()->for($user)->create();
    $transaction = createIncomeTransaction($user, $account, '25.00', $category->id);

    $balanceBefore = $account->fresh()->current_balance;

    $logged = captureTelemetryLogs(function () use ($user, $transaction, $deposit): void {
        app(AssignTransactionToDeposit::class)->handle($user, $transaction, [
            'kind' => 'interest',
            'deposit_id' => $deposit->id,
        ]);
    });

    $transaction->refresh();
    expect($transaction->category_id)->toBe($category->id);
    expect($account->fresh()->current_balance)->toBe($balanceBefore);

    $movement = $transaction->depositMovement;
    expect($movement)->not->toBeNull();
    expect($movement->kind)->toBe(DepositMovementKind::Interest);

    assertTelemetryEvent($logged, 'deposit_transaction_assigned', function (array $context): bool {
        return $context['kind'] === 'interest';
    });
});

test('assign rejects another users transaction', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = createBankAccount($owner);
    $transaction = createExpenseTransaction($owner, $account, '-1000.00');

    expect(fn () => app(AssignTransactionToDeposit::class)->handle($other, $transaction, [
        'kind' => 'open',
    ]))->toThrow(ValidationException::class);
});

test('assign rejects a transfer-linked transaction', function () {
    $user = User::factory()->create();
    $account = createBankAccount($user);
    $transaction = createExpenseTransaction($user, $account, '-1000.00', overrides: [
        'transfer_id' => 'transfer-123',
    ]);

    expect(fn () => app(AssignTransactionToDeposit::class)->handle($user, $transaction, [
        'kind' => 'open',
    ]))->toThrow(ValidationException::class);
});

test('assign rejects a transaction that already has a deposit movement', function () {
    $user = User::factory()->create();
    $account = createBankAccount($user);
    $transaction = createExpenseTransaction($user, $account, '-1000.00');

    $deposit = Deposit::query()->create([
        'user_id' => $user->id,
        'currency_id' => $account->currency_id,
        'source_account_id' => $account->id,
        'principal' => '1000.00',
        'opened_at' => '2026-08-01',
        'status' => DepositStatus::Active,
    ]);

    DepositMovement::query()->create([
        'deposit_id' => $deposit->id,
        'transaction_id' => $transaction->id,
        'kind' => DepositMovementKind::Open,
    ]);

    expect(fn () => app(AssignTransactionToDeposit::class)->handle($user, $transaction, [
        'kind' => 'open',
    ]))->toThrow(ValidationException::class);
});

test('assign rejects an adjustment transaction', function () {
    $user = User::factory()->create();
    $account = createBankAccount($user);
    $transaction = createExpenseTransaction($user, $account, '-1000.00', overrides: [
        'type' => TransactionType::Adjustment,
    ]);

    expect(fn () => app(AssignTransactionToDeposit::class)->handle($user, $transaction, [
        'kind' => 'open',
    ]))->toThrow(ValidationException::class);
});

test('assign rejects a cash account transaction', function () {
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
    $transaction = createExpenseTransaction($user, $cash, '-1000.00');

    expect(fn () => app(AssignTransactionToDeposit::class)->handle($user, $transaction, [
        'kind' => 'open',
    ]))->toThrow(ValidationException::class);
});

test('assign rejects a trashed account transaction', function () {
    $user = User::factory()->create();
    $account = createBankAccount($user);
    $account->delete();
    $transaction = createExpenseTransaction($user, $account, '-1000.00');

    expect(fn () => app(AssignTransactionToDeposit::class)->handle($user, $transaction, [
        'kind' => 'open',
    ]))->toThrow(ValidationException::class);
});

test('assign open rejects a non-negative amount', function () {
    $user = User::factory()->create();
    $account = createBankAccount($user);
    $transaction = createIncomeTransaction($user, $account, '1000.00');

    expect(fn () => app(AssignTransactionToDeposit::class)->handle($user, $transaction, [
        'kind' => 'open',
    ]))->toThrow(ValidationException::class);
});

test('assign close principal rejects amount mismatch', function () {
    $user = User::factory()->create();
    $account = createBankAccount($user);

    $deposit = app(CreateDeposit::class)->handle($user, [
        'source_account_id' => $account->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
    ]);

    $transaction = createIncomeTransaction($user, $account, '999.99');

    expect(fn () => app(AssignTransactionToDeposit::class)->handle($user, $transaction, [
        'kind' => 'close_principal',
        'deposit_id' => $deposit->id,
    ]))->toThrow(ValidationException::class);
});

test('assign interest rejects a second interest movement', function () {
    $user = User::factory()->create();
    $account = createBankAccount($user);

    $deposit = app(CreateDeposit::class)->handle($user, [
        'source_account_id' => $account->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
    ]);

    $firstInterest = createIncomeTransaction($user, $account, '10.00', Category::factory()->income()->for($user)->create()->id);
    app(AssignTransactionToDeposit::class)->handle($user, $firstInterest, [
        'kind' => 'interest',
        'deposit_id' => $deposit->id,
    ]);

    $secondInterest = createIncomeTransaction($user, $account, '5.00', Category::factory()->income()->for($user)->create()->id, [
        'description' => 'More interest',
    ]);

    expect(fn () => app(AssignTransactionToDeposit::class)->handle($user, $secondInterest, [
        'kind' => 'interest',
        'deposit_id' => $deposit->id,
    ]))->toThrow(ValidationException::class);
});
