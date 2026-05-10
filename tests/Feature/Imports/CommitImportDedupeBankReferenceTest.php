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

test(
    'import skips duplicate when bank_reference_id matches an existing transaction',
    function (string $csvBody, int $expectedImported, int $expectedSkipped, array $mapping, array $headers) {
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

        $sourceFile = "imports/{$user->id}/dedupe-ref-{$account->id}.csv";

        $import = Import::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'status' => 'queued',
            'mapping' => $mapping,
            'details' => [
                'source_file' => $sourceFile,
                'headers' => $headers,
            ],
        ]);

        Storage::disk('local')->put($sourceFile, $csvBody);

        app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

        $import->refresh();

        expect($import->status)->toBe('committed');
        expect($import->rows_imported)->toBe($expectedImported);
        expect($import->rows_skipped_duplicate)->toBe($expectedSkipped);

        expect(Transaction::query()->where('import_id', $import->id)->count())->toBe($expectedImported);
    },
)->with([
    'same ref twice' => [
        "date;amount;description;ref\n"
        ."01-04-2026;-10.00;Store purchase;REF-123\n"
        ."01-04-2026;-10.00;Store purchase;REF-123\n",
        1,
        1,
        [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
            'bank_reference_id' => 'ref',
        ],
        ['date', 'amount', 'description', 'ref'],
    ],
    'different ref same fingerprint' => [
        "date;amount;description;ref\n"
        ."01-04-2026;-10.00;Store purchase;REF-AAA\n"
        ."01-04-2026;-10.00;Store purchase;REF-BBB\n",
        2,
        0,
        [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
            'bank_reference_id' => 'ref',
        ],
        ['date', 'amount', 'description', 'ref'],
    ],
    'no ref fallback dedupe' => [
        "date;amount;description\n"
        ."01-04-2026;-10.00;Store purchase\n"
        ."01-04-2026;-10.00;Store purchase\n",
        1,
        1,
        [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
        ],
        ['date', 'amount', 'description'],
    ],
]);
