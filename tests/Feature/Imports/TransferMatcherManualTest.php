<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Enums\TransferMatchStatus;
use App\Imports\Workflow\CommitImport;
use App\Jobs\CommitImportJob;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

test('transfer matcher marks pair as manual without transfer id when no transfer token in descriptions', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $accountB = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'BNP',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 150,
    ]);

    $accountA = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'BNP ROR',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $existing = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountB->id,
        'currency_id' => $plnId,
        'date' => '2026-04-03',
        'booked_at' => '2026-04-03',
        'amount' => '150.00',
        'type' => TransactionType::Income,
        'description' => 'Wpłata ze sklepu',
        'subject' => null,
        'normalized_description' => 'wplata ze sklepu',
        'dedupe_hash' => md5('seed-manual-bnp', true),
    ]);

    $import = createTransferMatcherImport(
        $user,
        $accountA,
        "date;amount;description;subject\n"
        .'02-04-2026;-150.00;Płatność kartą;'
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $imported = Transaction::query()->where('import_id', $import->id)->first();
    $existing->refresh();

    expect($imported)->not->toBeNull();
    expect($imported->transfer_id)->toBeNull();
    expect($imported->transfer_match_status)->toBe(TransferMatchStatus::Manual);
    expect($existing->transfer_match_status)->toBe(TransferMatchStatus::Manual);
    expect($imported->transfer_candidate_for_id)->toBe($existing->id);
    expect($existing->transfer_candidate_for_id)->toBe($imported->id);
});
