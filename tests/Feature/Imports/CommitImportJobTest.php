<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Events\Imports\ImportEnrichmentTypesenseHit;
use App\Events\Imports\ImportEnrichmentTypesenseMiss;
use App\Events\ImportStatusUpdated;
use App\Imports\BankAdapters\BnpParibasImportAdapter;
use App\Imports\Workflow\CommitImport;
use App\Integrations\DescriptionMemory\DescriptionMemoryRepository;
use App\Integrations\DescriptionMemory\SuggestedFields;
use App\Jobs\CommitImportJob;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDescriptionMemoryRepository;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

test('job commits valid rows and updates account balance', function () {
    Event::fake([ImportStatusUpdated::class]);

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
    expect(Transaction::query()->where('import_id', $import->id)->whereColumn('booked_at', 'date')->count())->toBe(2);

    Event::assertDispatched(ImportStatusUpdated::class, function (ImportStatusUpdated $event) use ($user, $import): bool {
        return (int) $event->import->id === (int) $import->id
            && (int) $event->import->user_id === (int) $user->id;
    });
});

test('job uses typ transakcji for BNP rows when opis is empty', function () {
    Event::fake([ImportStatusUpdated::class]);

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'BNP',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'queued',
        'mapping' => [
            'date' => 'Data transakcji',
            'amount' => 'Kwota',
            'description' => 'Opis',
        ],
        'details' => [
            'source_file' => "imports/{$user->id}/bnp-opis-fallback.csv",
            'headers' => ['Data transakcji', 'Kwota', 'Opis', 'Typ transakcji'],
            'bank' => Bank::BnpParibas->value,
            'parser' => BnpParibasImportAdapter::class,
        ],
    ]);

    Storage::disk('local')->put(
        data_get($import->details, 'source_file'),
        "Data transakcji;Kwota;Opis;Typ transakcji\n24-04-2026;-12.34;Coffee shop;\n25-04-2026;100.00;;Przelew przychodzący"
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $import->refresh();

    expect($import->status)->toBe('committed');
    expect($import->rows_imported)->toBe(2);
    expect($import->rows_failed_validation)->toBe(0);
    expect(Transaction::query()->where('import_id', $import->id)->where('description', 'Coffee shop')->exists())->toBeTrue();
    expect(Transaction::query()->where('import_id', $import->id)->where('description', 'Przelew przychodzący')->exists())->toBeTrue();
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

test('job skips duplicate rows within the same import file', function () {
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

    $content = "date;amount;description;subject\n"
        ."24-04-2026;-12.34;Coffee;Cafe\n"
        .'24-04-2026;-12.34;Coffee;Cafe';

    $import = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $import->refresh();

    expect($import->rows_imported)->toBe(1);
    expect($import->rows_skipped_duplicate)->toBe(1);
});

test('job skips duplicates after imported transaction descriptions are edited', function () {
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

    $content = "date;amount;description;subject\n"
        ."24-04-2026;-12.34;Coffee;Cafe\n"
        .'25-04-2026;100.00;Salary;Work';

    $firstImport = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $firstImport->id])->handle(app(CommitImport::class));

    Transaction::query()
        ->where('import_id', $firstImport->id)
        ->orderBy('date')
        ->get()
        ->each(function (Transaction $transaction, int $index): void {
            $transaction->forceFill([
                'description' => "Edited description {$index}",
                'subject' => "Edited subject {$index}",
                'normalized_description' => "edited description {$index}",
                'dedupe_hash' => md5("changed-hash-{$index}", true),
            ])->save();
        });

    $secondImport = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $secondImport->id])->handle(app(CommitImport::class));

    $secondImport->refresh();

    expect($secondImport->rows_imported)->toBe(0);
    expect($secondImport->rows_skipped_duplicate)->toBe(2);
    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(2);
});

test('job skips duplicates after imported transaction date and amount are edited', function () {
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

    $transaction = Transaction::query()->where('import_id', $firstImport->id)->firstOrFail();
    $transaction->forceFill([
        'date' => '2026-04-30',
        'booked_at' => '2026-04-30',
        'amount' => '-20.00',
        'dedupe_hash' => md5('changed-date-and-amount', true),
    ])->save();

    $secondImport = createImportWithFile($user, $account, $content);
    app(CommitImportJob::class, ['importId' => $secondImport->id])->handle(app(CommitImport::class));

    $secondImport->refresh();

    expect($secondImport->rows_imported)->toBe(0);
    expect($secondImport->rows_skipped_duplicate)->toBe(1);
    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(1);
});

test('job stores import fingerprint for imported rows', function () {
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
        "date;amount;description;subject\n24-04-2026;-12.34;Coffee;Cafe"
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $import->id)->firstOrFail();

    expect($transaction->import_fingerprint)->not->toBeNull();
    expect(bin2hex($transaction->import_fingerprint))->toBe(
        bin2hex(md5($account->id.'|2026-04-24|-12.34|coffee', true))
    );
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

test('job uses bank captured at upload when account bank is changed before processing', function () {
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

    $import = createImportWithFixture(
        $user,
        $account,
        'tests/Fixtures/import/bnp-basic.csv',
        [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
            'subject' => 'subject',
        ],
        ['date', 'amount', 'description', 'subject']
    );

    $import->details = array_merge((array) $import->details, [
        'bank' => Bank::BnpParibas->value,
        'parser' => BnpParibasImportAdapter::class,
    ]);
    $import->save();

    $account->bank = Bank::MBank;
    $account->save();

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $import->refresh();

    expect($import->status)->toBe('committed');
    expect($import->rows_imported)->toBe(2);
});

test('duplicate commit import job dispatch is deduped while unique lock is held', function () {
    Queue::fake();

    CommitImportJob::dispatch(123);
    CommitImportJob::dispatch(123);

    Queue::assertPushedTimes(CommitImportJob::class, 1);
});

test('commit import returns false when import is not queued or processing', function () {
    Storage::fake('local');

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

    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => 'draft',
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
        ],
        'details' => [
            'source_file' => "imports/{$user->id}/draft.csv",
            'headers' => ['date', 'amount', 'description'],
        ],
    ]);

    Storage::disk('local')->put("imports/{$user->id}/draft.csv", "date;amount;description\n24-04-2026;-1.00;X");

    expect(app(CommitImport::class)->handle($import))->toBeFalse();

    $import->refresh();
    expect($import->status)->toBe('draft');
    expect(Transaction::query()->where('import_id', $import->id)->count())->toBe(0);
});

test('commit import returns false when import already committed', function () {
    Storage::fake('local');

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
        "date;amount;description\n24-04-2026;-12.34;Coffee"
    );

    expect(app(CommitImport::class)->handle($import))->toBeTrue();
    expect(app(CommitImport::class)->handle($import))->toBeFalse();

    expect(Transaction::query()->where('import_id', $import->id)->count())->toBe(1);
});
