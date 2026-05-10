<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Imports\Workflow\CommitImport;
use App\Jobs\CommitImportJob;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

test('import sets booked_at equal to operation date for every row', function () {
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

    $sourceFile = "imports/{$user->id}/booked-at-{$account->id}.csv";

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
            'source_file' => $sourceFile,
            'headers' => ['date', 'amount', 'description', 'subject'],
        ],
    ]);

    Storage::disk('local')->put(
        $sourceFile,
        "date;amount;description;subject\n"
        ."01-04-2026;-12.34;Coffee;Cafe\n"
        ."15-04-2026;100.00;Salary;Work\n"
        ."30-04-2026;-5.00;Snack;Shop"
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $import->refresh();

    expect($import->status)->toBe('committed');
    expect($import->rows_imported)->toBe(3);

    $transactions = Transaction::query()
        ->where('import_id', $import->id)
        ->orderBy('date')
        ->get();

    expect($transactions)->toHaveCount(3);

    foreach ($transactions as $transaction) {
        expect($transaction->booked_at->toDateString())
            ->toBe($transaction->date->toDateString());
    }

    expect($transactions[0]->date->toDateString())->toBe('2026-04-01');
    expect($transactions[0]->booked_at->toDateString())->toBe('2026-04-01');
    expect($transactions[1]->date->toDateString())->toBe('2026-04-15');
    expect($transactions[1]->booked_at->toDateString())->toBe('2026-04-15');
    expect($transactions[2]->date->toDateString())->toBe('2026-04-30');
    expect($transactions[2]->booked_at->toDateString())->toBe('2026-04-30');
});
