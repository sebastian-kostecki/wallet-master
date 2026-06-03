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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

function createTelemetryImportWithFile(User $user, Account $account, string $content): Import
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

test('commit import records import_started and import_completed telemetry events', function () {
    Log::fake();

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

    $import = createTelemetryImportWithFile(
        $user,
        $account,
        "date;amount;description;subject\n24-04-2026;-12.34;Coffee;Cafe\n25-04-2026;100.00;Salary;Work"
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $import->refresh();

    expect($import->status)->toBe('committed');
    expect($import->rows_total)->toBe(2);
    expect($import->rows_imported)->toBe(2);

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) use ($import, $user) {
        return $message === 'import_started'
            && $context['event'] === 'import_started'
            && $context['import_id'] === $import->id
            && $context['user_id'] === $user->id
            && isset($context['recorded_at']);
    });

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) use ($import, $user) {
        return $message === 'import_completed'
            && $context['event'] === 'import_completed'
            && $context['import_id'] === $import->id
            && $context['user_id'] === $user->id
            && $context['rows_total'] === 2
            && $context['rows_imported'] === 2
            && $context['rows_skipped_duplicate'] === 0
            && $context['rows_failed_validation'] === 0
            && ! array_key_exists('description', $context)
            && ! array_key_exists('description_raw', $context)
            && isset($context['recorded_at']);
    });
});

test('upload with unrecognized headers records import_headers_unrecognized telemetry event', function () {
    Log::fake();

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

    $file = UploadedFile::fake()->createWithContent('import.csv', "foo;bar;baz\n1;2;3");

    $this
        ->actingAs($user)
        ->postJson(route('imports.upload'), [
            'account_id' => $account->id,
            'file' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'unrecognized_headers');

    Log::channel('telemetry')->assertLogged('info', function ($message, $context) use ($user, $account) {
        return $message === 'import_headers_unrecognized'
            && $context['event'] === 'import_headers_unrecognized'
            && $context['account_id'] === $account->id
            && $context['bank'] === Bank::BnpParibas->value
            && $context['user_id'] === $user->id
            && isset($context['recorded_at']);
    });
});
