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

test('transfer matcher auto links import row with existing opposite transaction when transfer token present', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $accountB = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'BNP Savings',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 200,
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
        'date' => '2026-04-02',
        'booked_at' => '2026-04-02',
        'amount' => '200.00',
        'type' => TransactionType::Income,
        'description' => 'Przelew własny z mBank',
        'subject' => null,
        'normalized_description' => 'przelew wlasny z mbank',
        'dedupe_hash' => md5('seed-bnp-income', true),
    ]);

    $balanceBBefore = (string) $accountB->fresh()->current_balance;

    $import = createTransferMatcherImport(
        $user,
        $accountA,
        "date;amount;description;subject\n"
        .'01-04-2026;-200.00;Przelew własny na konto BNP;'
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $imported = Transaction::query()->where('import_id', $import->id)->first();
    $existing->refresh();

    expect($imported)->not->toBeNull();
    expect($imported->transfer_id)->not->toBeNull();
    expect($imported->transfer_id)->toBe($existing->transfer_id);
    expect($imported->transfer_match_status)->toBe(TransferMatchStatus::Auto);
    expect($existing->transfer_match_status)->toBe(TransferMatchStatus::Auto);
    expect($imported->type)->toBe(TransactionType::Transfer);
    expect($existing->type)->toBe(TransactionType::Transfer);
    expect($imported->category_id)->toBeNull();
    expect($existing->category_id)->toBeNull();
    expect((string) $accountA->fresh()->current_balance)->toBe('-200.00');
    expect((string) $accountB->fresh()->current_balance)->toBe($balanceBBefore);
});
