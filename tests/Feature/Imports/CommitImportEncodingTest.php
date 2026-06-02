<?php

declare(strict_types=1);

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

test('import preserves polish characters from cp1250 csv', function () {
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

    $description = 'Zakup w Żabce';
    $csvBody = "date;amount;description\n01-04-2026;-12,34;{$description}\n";
    $encoded = iconv('UTF-8', 'WINDOWS-1250//IGNORE', $csvBody);
    expect($encoded)->not->toBeFalse();

    $sourceFile = "imports/{$user->id}/encoding.csv";
    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'queued',
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
        ],
        'details' => [
            'source_file' => $sourceFile,
            'bank' => Bank::BnpParibas->value,
            'headers' => ['date', 'amount', 'description'],
        ],
    ]);

    Storage::disk('local')->put($sourceFile, $encoded);

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $import->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->raw_statement_description)->toBe($description);
});
