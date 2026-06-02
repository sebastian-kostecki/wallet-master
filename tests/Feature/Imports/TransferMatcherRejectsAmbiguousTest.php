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

test('transfer matcher does not auto link when multiple candidates exist', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $accountB = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'BNP',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 400,
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

    $candidateNear = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountB->id,
        'currency_id' => $plnId,
        'date' => '2026-04-02',
        'booked_at' => '2026-04-02',
        'amount' => '100.00',
        'type' => TransactionType::Income,
        'description' => 'Przelew własny A',
        'subject' => null,
        'normalized_description' => 'przelew wlasny a',
        'dedupe_hash' => md5('ambig-a', true),
    ]);

    $candidateFar = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountB->id,
        'currency_id' => $plnId,
        'date' => '2026-04-04',
        'booked_at' => '2026-04-04',
        'amount' => '100.00',
        'type' => TransactionType::Income,
        'description' => 'Przelew własny B',
        'subject' => null,
        'normalized_description' => 'przelew wlasny b',
        'dedupe_hash' => md5('ambig-b', true),
    ]);

    $import = createTransferMatcherImport(
        $user,
        $accountA,
        "date;amount;description;subject\n"
        .'03-04-2026;-100.00;Przelew własny z mBank;'
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $imported = Transaction::query()->where('import_id', $import->id)->first();
    $candidateNear->refresh();
    $candidateFar->refresh();

    expect($imported)->not->toBeNull();
    expect($imported->transfer_match_status)->toBe(TransferMatchStatus::Manual);
    expect($imported->transfer_id)->toBeNull();
    expect($imported->transfer_candidate_for_id)->toBe($candidateNear->id);
    expect($candidateNear->transfer_match_status)->toBe(TransferMatchStatus::Manual);
    expect($candidateFar->transfer_match_status)->toBe(TransferMatchStatus::None);
});
