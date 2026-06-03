<?php

declare(strict_types=1);

use App\Console\Commands\Imports\PurgeOldImportFiles;
use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

test('purge command deletes failed import source files older than retention period', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 6, 3, 12, 0, 0));

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

    $oldPath = "imports/{$user->id}/1/source-failed.csv";
    $recentPath = "imports/{$user->id}/2/source-failed.csv";

    Storage::disk('local')->put($oldPath, 'old failed import');
    Storage::disk('local')->put($recentPath, 'recent failed import');

    $oldImport = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => ImportStatus::Failed->value,
        'details' => ['source_file' => $oldPath],
    ]);
    $oldImport->created_at = now()->subDays(45);
    $oldImport->saveQuietly();

    $recentImport = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => ImportStatus::Failed->value,
        'details' => ['source_file' => $recentPath],
    ]);
    $recentImport->created_at = now()->subDays(5);
    $recentImport->saveQuietly();

    $this->artisan(PurgeOldImportFiles::class, ['--days' => 30])
        ->assertSuccessful();

    expect(Storage::disk('local')->exists($oldPath))->toBeFalse();
    expect(Storage::disk('local')->exists($recentPath))->toBeTrue();

    CarbonImmutable::setTestNow();
});

test('purge command dry run does not delete files', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 6, 3, 12, 0, 0));

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

    $path = "imports/{$user->id}/9/source-failed.csv";
    Storage::disk('local')->put($path, 'failed import');

    $failedImport = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'status' => ImportStatus::Failed->value,
        'details' => ['source_file' => $path],
    ]);
    $failedImport->created_at = now()->subDays(60);
    $failedImport->saveQuietly();

    $this->artisan(PurgeOldImportFiles::class, ['--days' => 30, '--dry-run' => true])
        ->assertSuccessful();

    expect(Storage::disk('local')->exists($path))->toBeTrue();

    CarbonImmutable::setTestNow();
});
