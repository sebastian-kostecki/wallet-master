<?php

use App\Actions\Imports\CommitImport;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Events\Imports\ImportEnrichmentTypesenseHit;
use App\Events\Imports\ImportEnrichmentTypesenseMiss;
use App\Jobs\CommitImportJob;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use App\Support\DescriptionMemory\DescriptionMemoryRepository;
use App\Support\DescriptionMemory\SuggestedFields;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDescriptionMemoryRepository;

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

function createImportWithFixture(User $user, Account $account, string $fixturePath, array $mapping, array $headers): Import
{
    $extension = pathinfo($fixturePath, PATHINFO_EXTENSION);
    $sourceFile = "imports/{$user->id}/source-{$account->id}.{$extension}";
    $content = file_get_contents(base_path($fixturePath));
    expect($content)->not->toBeFalse();

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

    Storage::disk('local')->put($sourceFile, (string) $content);

    return $import;
}

test('job commits valid rows and updates account balance', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $import = createImportWithFile(
        $user,
        $account,
        "date;amount;description;subject\n24-04-2026;-12.34;Coffee;Cafe\n25-04-2026;100.00;Salary;Work"
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $import->refresh();
    $account->refresh();

    expect($import->status)->toBe('committed');
    expect($import->rows_total)->toBe(2);
    expect($import->rows_imported)->toBe(2);
    expect($import->rows_skipped_duplicate)->toBe(0);
    expect($import->rows_failed_validation)->toBe(0);
    expect($account->current_balance)->toBe('187.66');
    expect(Transaction::query()->where('import_id', $import->id)->count())->toBe(2);
});

test('job enriches imported transactions via description memory (typesense best-effort)', function () {
    Event::fake([ImportEnrichmentTypesenseHit::class, ImportEnrichmentTypesenseMiss::class]);

    $fakeRepo = new FakeDescriptionMemoryRepository;
    app()->instance(DescriptionMemoryRepository::class, $fakeRepo);

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

    $import = createImportWithFile(
        $user,
        $account,
        "date;amount;description;subject\n24-04-2026;-12.34;Coffee;Cafe\n25-04-2026;100.00;Salary;Work"
    );

    $fakeRepo->setSuggestion(
        userId: $user->id,
        bank: Bank::BnpParibas,
        rawStatementDescription: 'Coffee',
        suggestedFields: new SuggestedFields(subject: 'Cafe override', description: 'Coffee (learned)', matchType: 'fuzzy', score: 42),
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $import->refresh();
    expect($import->status)->toBe('committed');

    $coffee = Transaction::query()
        ->where('import_id', $import->id)
        ->where('raw_statement_description', 'Coffee')
        ->firstOrFail();

    expect($coffee->subject)->toBe('Cafe override');
    expect($coffee->description)->toBe('Coffee (learned)');
    expect($coffee->raw_statement_description)->toBe('Coffee');

    Event::assertDispatched(ImportEnrichmentTypesenseHit::class, function (ImportEnrichmentTypesenseHit $event) use ($user, $import): bool {
        return $event->userId === $user->id
            && $event->importId === $import->id
            && $event->bank === Bank::BnpParibas
            && $event->matchType === 'fuzzy'
            && $event->score === 42;
    });

    Event::assertDispatched(ImportEnrichmentTypesenseMiss::class, function (ImportEnrichmentTypesenseMiss $event) use ($user, $import): bool {
        return $event->userId === $user->id
            && $event->importId === $import->id
            && $event->bank === Bank::BnpParibas;
    });
});

test('job skips duplicates when importing same file twice', function () {
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

    $secondImport = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $secondImport->id])->handle(app(CommitImport::class));

    $secondImport->refresh();

    expect($secondImport->rows_imported)->toBe(0);
    expect($secondImport->rows_skipped_duplicate)->toBe(1);
});

test('job counts invalid rows and keeps valid ones', function () {
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

    $import = createImportWithFile(
        $user,
        $account,
        "date;amount;description;subject\ninvalid-date;-12.34;Coffee;Cafe\n24-04-2026;10.00;Refund;Shop"
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $import->refresh();
    $account->refresh();

    expect($import->rows_total)->toBe(2);
    expect($import->rows_imported)->toBe(1);
    expect($import->rows_failed_validation)->toBe(1);
    expect($account->current_balance)->toBe('10.00');
});

test('job processes both import fixtures', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $mbankAccount = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'mBank',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $bnpAccount = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'BNP',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $mbankImport = createImportWithFixture(
        $user,
        $mbankAccount,
        'tests/Fixtures/import/mbank-basic.csv',
        [
            'date' => 'Data operacji',
            'amount' => 'Kwota',
            'description' => 'Opis operacji',
        ],
        ['Data operacji', 'Opis operacji', 'Rachunek', 'Kategoria', 'Kwota']
    );

    $bnpImport = createImportWithFixture(
        $user,
        $bnpAccount,
        'tests/Fixtures/import/bnp-basic.csv',
        [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
            'subject' => 'subject',
        ],
        ['date', 'amount', 'description', 'subject']
    );

    app(CommitImportJob::class, ['importId' => $mbankImport->id])->handle(app(CommitImport::class));
    app(CommitImportJob::class, ['importId' => $bnpImport->id])->handle(app(CommitImport::class));

    $mbankImport->refresh();
    $bnpImport->refresh();

    expect($mbankImport->status)->toBe('committed');
    expect($mbankImport->rows_imported)->toBeGreaterThan(0);
    expect($bnpImport->status)->toBe('committed');
    expect($bnpImport->rows_imported)->toBe(2);
});
