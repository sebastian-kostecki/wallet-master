<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

test('upload returns 422 with unrecognized_headers code when file headers cannot be mapped', function () {
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

    $response = $this
        ->actingAs($user)
        ->postJson(route('imports.upload'), [
            'account_id' => $account->id,
            'file' => $file,
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'unrecognized_headers');

    expect(Import::query()->count())->toBe(0);

    $files = Storage::disk('local')->allFiles("imports/{$user->id}");
    expect($files)->toBe([]);
});
