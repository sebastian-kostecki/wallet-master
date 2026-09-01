<?php

declare(strict_types=1);

use App\Actions\Deposits\CreateDeposit;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Database\Seeders\CurrencySeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function depositResourceBankAccount(User $user, string $balance = '5000.00'): Account
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

function depositResourceExpense(
    User $user,
    Account $account,
    string $amount,
    string $date = '2026-07-01',
    string $description = 'Plain expense',
): Transaction {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $normalizedDescription = TransactionDedupe::normalizeDescription($description);

    return Transaction::query()->create([
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
        'category_id' => Category::factory()->expense()->for($user)->create()->id,
    ]);
}

test('transaction index exposes deposit movement and assignable flags', function () {
    $user = User::factory()->create();
    $account = depositResourceBankAccount($user);

    app(CreateDeposit::class)->handle($user, [
        'source_account_id' => $account->id,
        'principal' => '1000.00',
        'opened_at' => '01-08-2026',
        'name' => '3M',
    ]);

    $plainExpense = depositResourceExpense($user, $account, '-50.00');

    $response = $this->actingAs($user)->get(route('transactions.index', absolute: false));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('transactions/Index', false)
        ->where('transactions.data.0.deposit_movement.kind', 'open')
        ->where('transactions.data.0.deposit_assignable', false)
        ->where('transactions.data', fn ($rows) => collect($rows)->contains(
            fn (array $row) => $row['id'] === $plainExpense->id && $row['deposit_assignable'] === true,
        )),
    );
});
