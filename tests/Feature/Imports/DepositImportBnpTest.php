<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositMatchStatus;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Enums\TransferMatchStatus;
use App\Imports\BankAdapters\BnpParibasImportAdapter;
use App\Imports\Workflow\CommitImport;
use App\Jobs\CommitImportJob;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

function createBnpDepositImportAccount(User $user, string $balance = '10000.00'): Account
{
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    return Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'BNP ROR',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => $balance,
        'current_balance' => $balance,
    ]);
}

function createBnpDepositImport(User $user, Account $account, string $fixturePath): Import
{
    $import = createImportWithFixture(
        $user,
        $account,
        $fixturePath,
        ['date' => 'Data transakcji', 'amount' => 'Kwota', 'description' => 'Opis'],
        ['Data transakcji', 'Kwota', 'Opis', 'Typ transakcji'],
    );

    $import->details = array_merge((array) $import->details, [
        'bank' => Bank::BnpParibas->value,
        'parser' => BnpParibasImportAdapter::class,
    ]);
    $import->save();

    return $import;
}

test('BNP import auto opens deposit for OTWARCIE LOKATY row', function () {
    $user = User::factory()->create();
    $account = createBnpDepositImportAccount($user);
    $import = createBnpDepositImport(
        $user,
        $account,
        'tests/Fixtures/import/bnp-deposit-open.csv',
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $import->id)->sole();
    expect($transaction->deposit_match_status)->toBe(DepositMatchStatus::Auto);
    expect($transaction->category_id)->toBeNull();
    expect($transaction->depositMovement?->kind)->toBe(DepositMovementKind::Open);
    expect(Deposit::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($account->fresh()->current_balance)->toBe('5000.00');
});

test('BNP import auto closes deposit for SPŁATA DEPOZYTU row when one active deposit matches', function () {
    $user = User::factory()->create();
    $account = createBnpDepositImportAccount($user, '5000.00');
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    Deposit::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'source_account_id' => $account->id,
        'name' => '3M',
        'principal' => '5000.00',
        'opened_at' => '2026-08-01',
        'status' => DepositStatus::Active,
    ]);

    $import = createBnpDepositImport(
        $user,
        $account,
        'tests/Fixtures/import/bnp-deposit-close.csv',
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $import->id)->sole();
    expect($transaction->deposit_match_status)->toBe(DepositMatchStatus::Auto);
    expect($transaction->category_id)->toBeNull();
    expect($transaction->depositMovement?->kind)->toBe(DepositMovementKind::ClosePrincipal);
    expect(Deposit::query()->where('user_id', $user->id)->where('status', DepositStatus::Closed)->count())->toBe(1);
    expect($account->fresh()->current_balance)->toBe('10000.00');
});

test('BNP import marks ambiguous close as candidate when multiple active deposits match', function () {
    $user = User::factory()->create();
    $account = createBnpDepositImportAccount($user, '5000.00');
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    foreach (['3M A', '3M B'] as $name) {
        Deposit::query()->create([
            'user_id' => $user->id,
            'currency_id' => $plnId,
            'source_account_id' => $account->id,
            'name' => $name,
            'principal' => '5000.00',
            'opened_at' => '2026-08-01',
            'status' => DepositStatus::Active,
        ]);
    }

    $import = createBnpDepositImport(
        $user,
        $account,
        'tests/Fixtures/import/bnp-deposit-close.csv',
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $import->id)->sole();
    expect($transaction->deposit_match_status)->toBe(DepositMatchStatus::Manual);
    expect($transaction->deposit_candidate_kind)->toBe('close_principal');
    expect($transaction->category_id)->toBeNull();
    expect($transaction->depositMovement)->toBeNull();
    expect(Deposit::query()->where('user_id', $user->id)->where('status', DepositStatus::Active)->count())->toBe(2);
});

test('BNP deposit open import row is not transfer matched', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $accountB = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'BNP Savings',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => '5000.00',
        'current_balance' => '5000.00',
    ]);

    $accountA = createBnpDepositImportAccount($user);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountB->id,
        'currency_id' => $plnId,
        'date' => '2026-08-01',
        'booked_at' => '2026-08-01',
        'amount' => '5000.00',
        'type' => TransactionType::Income,
        'description' => 'Przelew własny z mBank',
        'subject' => null,
        'normalized_description' => 'przelew wlasny z mbank',
        'dedupe_hash' => md5('seed-bnp-income-deposit', true),
    ]);

    $import = createBnpDepositImport(
        $user,
        $accountA,
        'tests/Fixtures/import/bnp-deposit-open.csv',
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $imported = Transaction::query()->where('import_id', $import->id)->sole();

    expect($imported->transfer_id)->toBeNull();
    expect($imported->transfer_match_status)->toBe(TransferMatchStatus::None);
    expect($imported->deposit_match_status)->toBe(DepositMatchStatus::Auto);
});
