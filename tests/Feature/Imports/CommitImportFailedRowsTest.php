<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\ImportFailedRowReason;
use App\Imports\Workflow\CommitImport;
use App\Jobs\CommitImportJob;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\ImportFailedRow;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

function createFailedRowsImport(User $user, Account $account, string $content): Import
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
            'source_file' => "imports/{$user->id}/failed-rows-{$account->id}.csv",
            'headers' => ['date', 'amount', 'description', 'subject'],
        ],
    ]);

    Storage::disk('local')->put((string) data_get($import->details, 'source_file'), $content);

    return $import;
}

test('commit import persists failed rows with reason codes and raw snapshots', function () {
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

    $import = createFailedRowsImport(
        $user,
        $account,
        implode("\n", [
            'date;amount;description;subject',
            'invalid-date;-12.34;Coffee;Cafe',
            '24-04-2026;0,00;Zero purchase;Shop',
            '24-04-2026;not-a-number;Bad amount;Shop',
            '24-04-2026;10.00;Refund;Shop',
        ]),
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $import->refresh();

    expect($import->rows_total)->toBe(4)
        ->and($import->rows_imported)->toBe(1)
        ->and($import->rows_failed_validation)->toBe(3);

    $failedRows = ImportFailedRow::query()
        ->where('import_id', $import->id)
        ->orderBy('row_number')
        ->get();

    expect($failedRows)->toHaveCount(3)
        ->and($failedRows[0]->reason_code)->toBe(ImportFailedRowReason::InvalidDate)
        ->and($failedRows[0]->date_raw)->toBe('invalid-date')
        ->and($failedRows[0]->amount_raw)->toBe('-12.34')
        ->and($failedRows[0]->description_raw)->toBe('Coffee')
        ->and($failedRows[1]->reason_code)->toBe(ImportFailedRowReason::ZeroAmount)
        ->and($failedRows[2]->reason_code)->toBe(ImportFailedRowReason::InvalidAmount);
});

test('import show returns failed rows after commit', function () {
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

    $import = createFailedRowsImport(
        $user,
        $account,
        "date;amount;description;subject\ninvalid-date;-12.34;Coffee;Cafe\n24-04-2026;10.00;Refund;Shop",
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $this->actingAs($user)
        ->getJson(route('imports.show', $import))
        ->assertOk()
        ->assertJsonPath('failed_rows_total', 1)
        ->assertJsonPath('failed_rows.0.reason_code', 'invalid_date')
        ->assertJsonPath('failed_rows.0.description_raw', 'Coffee');
});
