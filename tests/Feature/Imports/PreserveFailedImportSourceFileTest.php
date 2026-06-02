<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Imports\Workflow\PreserveFailedImportSourceFile;
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

test('failed import preserves source file under source-failed path', function () {
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

    $sourceFile = "imports/{$user->id}/1/source.csv";
    Storage::disk('local')->put($sourceFile, 'date;amount;description');

    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => ImportStatus::Failed->value,
        'details' => ['source_file' => $sourceFile],
    ]);

    app(PreserveFailedImportSourceFile::class)->execute($import);

    $import->refresh();

    expect(Storage::disk('local')->exists($sourceFile))->toBeFalse();
    expect(Storage::disk('local')->exists("imports/{$user->id}/1/source-failed.csv"))->toBeTrue();
    expect(data_get($import->details, 'source_file'))->toBe("imports/{$user->id}/1/source-failed.csv");
});
