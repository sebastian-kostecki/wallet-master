<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Imports\Workflow\CommitImport;
use App\Jobs\CommitImportJob;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

function createImportWithFile(User $user, Account $account, string $content): Import
{
    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'queued',
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
            'subject' => 'subject',
        ],
        'details' => [
            'source_file' => "imports/{$user->id}/source-{$account->id}.csv",
            'headers' => ['date', 'amount', 'description', 'subject'],
        ],
    ]);

    Storage::disk('local')->put(data_get($import->details, 'source_file'), $content);

    return $import;
}

test('import with only duplicate rows commits with zero imported', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $content = "date;amount;description;subject\n24-04-2026;-12.34;Coffee;Cafe";

    $firstImport = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $firstImport->id])->handle(app(CommitImport::class));

    $firstImport->refresh();
    expect($firstImport->rows_imported)->toBeGreaterThanOrEqual(1);

    $secondImport = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $secondImport->id])->handle(app(CommitImport::class));

    $secondImport->refresh();

    expect($secondImport->status)->toBe('committed');
    expect($secondImport->rows_imported)->toBe(0);
    expect($secondImport->rows_skipped_duplicate)->toBeGreaterThan(0);
});
