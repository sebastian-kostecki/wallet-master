<?php

declare(strict_types=1);

use App\Actions\Deposits\CloseDepositFromImport;
use App\Actions\Deposits\CreateDepositFromImport;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositMatchStatus;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Imports\ParsedImportRow;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

function createDepositImportAccount(User $user, string $balance = '10000.00'): Account
{
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    return Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'ROR',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => $balance,
        'current_balance' => $balance,
    ]);
}

function buildImportInsertMetadata(
    User $user,
    Account $account,
    Import $import,
    string $date,
    string $amount,
    string $description,
    string $rawStatementDescription,
): array {
    $normalizedDescription = TransactionDedupe::normalizeDescription($description);
    $normalizedRawStatementDescription = TransactionDedupe::normalizeDescription($rawStatementDescription);

    return [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $account->currency_id,
        'import_id' => $import->id,
        'date' => $date,
        'booked_at' => $date,
        'amount' => $amount,
        'type' => TransactionType::fromAmount($amount)->value,
        'description' => $description,
        'subject' => null,
        'raw_statement_description' => $rawStatementDescription,
        'normalized_description' => $normalizedDescription,
        'dedupe_hash' => TransactionDedupe::dedupeHash($date, $amount, $normalizedDescription),
        'import_fingerprint' => TransactionDedupe::importFingerprint(
            accountId: $account->id,
            dateYmd: $date,
            amountDecimalString: $amount,
            normalizedRawStatementDescription: $normalizedRawStatementDescription,
        ),
        'category_id' => null,
    ];
}

test('create deposit from import opens deposit and links import transaction', function () {
    $user = User::factory()->create();
    $account = createDepositImportAccount($user, '10000.00');
    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'queued',
        'mapping' => ['date' => 'date', 'amount' => 'amount', 'description' => 'description'],
        'details' => ['source_file' => 'imports/test.csv'],
    ]);

    $parsed = new ParsedImportRow(
        date: '2026-08-01',
        amount: '-5000.00',
        description: 'OTWARCIE LOKATY',
        subject: null,
        rawStatementDescription: 'OTWARCIE LOKATY',
    );

    $insertMetadata = buildImportInsertMetadata(
        user: $user,
        account: $account,
        import: $import,
        date: $parsed->date,
        amount: $parsed->amount,
        description: $parsed->description,
        rawStatementDescription: $parsed->rawStatementDescription,
    );

    $deposit = app(CreateDepositFromImport::class)->handle(
        $user,
        $import,
        $account,
        $parsed,
        $insertMetadata,
    );

    expect($deposit->status)->toBe(DepositStatus::Active);
    expect($deposit->principal)->toBe('5000.00');
    expect($deposit->source_account_id)->toBe($account->id);
    expect($account->fresh()->current_balance)->toBe('5000.00');

    $movement = $deposit->movements()->firstOrFail();
    expect($movement->kind)->toBe(DepositMovementKind::Open);

    $transaction = $movement->transaction;
    expect($transaction->import_id)->toBe($import->id);
    expect($transaction->import_fingerprint)->not->toBeNull();
    expect(bin2hex($transaction->import_fingerprint))->toBe(
        bin2hex($insertMetadata['import_fingerprint']),
    );
    expect($transaction->category_id)->toBeNull();
    expect($transaction->deposit_match_status)->toBe(DepositMatchStatus::Auto);
});

test('close deposit from import links transaction and closes deposit', function () {
    $user = User::factory()->create();
    $account = createDepositImportAccount($user, '5000.00');
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'queued',
        'mapping' => ['date' => 'date', 'amount' => 'amount', 'description' => 'description'],
        'details' => ['source_file' => 'imports/test.csv'],
    ]);

    $deposit = Deposit::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'source_account_id' => $account->id,
        'name' => '3M',
        'principal' => '5000.00',
        'opened_at' => '2026-08-01',
        'status' => DepositStatus::Active,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'import_id' => $import->id,
        'date' => '2026-11-01',
        'booked_at' => '2026-11-01',
        'amount' => '5000.00',
        'type' => TransactionType::Income,
        'description' => 'SPŁATA DEPOZYTU',
        'subject' => null,
        'normalized_description' => TransactionDedupe::normalizeDescription('SPŁATA DEPOZYTU'),
        'dedupe_hash' => TransactionDedupe::manualDedupeHash(
            '2026-11-01',
            '5000.00',
            TransactionDedupe::normalizeDescription('SPŁATA DEPOZYTU'),
        ),
        'import_fingerprint' => TransactionDedupe::importFingerprint(
            accountId: $account->id,
            dateYmd: '2026-11-01',
            amountDecimalString: '5000.00',
            normalizedRawStatementDescription: TransactionDedupe::normalizeDescription('SPŁATA DEPOZYTU'),
        ),
        'category_id' => null,
        'deposit_match_status' => DepositMatchStatus::None,
    ]);

    $closed = app(CloseDepositFromImport::class)->handle($user, $deposit, $transaction);

    expect($closed->status)->toBe(DepositStatus::Closed);
    expect($closed->closed_at?->toDateString())->toBe('2026-11-01');

    $movement = $closed->movements()->where('kind', DepositMovementKind::ClosePrincipal)->firstOrFail();
    expect($movement->transaction_id)->toBe($transaction->id);

    $transaction->refresh();
    expect($transaction->category_id)->toBeNull();
    expect($transaction->deposit_match_status)->toBe(DepositMatchStatus::Auto);
});
