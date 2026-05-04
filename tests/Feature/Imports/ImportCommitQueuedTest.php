<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Jobs\CommitImportJob;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('commit queues import job and marks import as queued', function () {
    Bus::fake();

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::MBank,
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
        'details' => ['headers' => ['date', 'amount', 'description']],
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('imports.commit', $import));

    $response->assertRedirect(route('transactions.index'));
    $import->refresh();
    expect($import->status)->toBe('queued');

    Bus::assertDispatched(CommitImportJob::class);
});

test('commit returns json when requested', function () {
    Bus::fake();

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::MBank,
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
        'details' => ['headers' => ['date', 'amount', 'description']],
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('imports.commit', $import));

    $response->assertAccepted();
    $response->assertJsonPath('import_id', $import->id);

    $import->refresh();
    expect($import->status)->toBe('queued');

    Bus::assertDispatched(CommitImportJob::class);
});

test('cannot commit someone else import', function () {
    Bus::fake();

    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $account = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Main',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = Import::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'status' => 'draft',
        'mapping' => [
            'date' => 'date',
            'amount' => 'amount',
            'description' => 'description',
        ],
        'details' => ['headers' => ['date', 'amount', 'description']],
    ]);

    $this
        ->actingAs($other)
        ->post(route('imports.commit', $import))
        ->assertForbidden();
});
