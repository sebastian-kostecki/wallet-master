<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\ImportFailedRowReason;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Import;
use App\Models\ImportFailedRow;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('owner can dismiss an unresolved import failed row', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

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
        'status' => 'committed',
    ]);

    $failedRow = ImportFailedRow::query()->create([
        'import_id' => $import->id,
        'user_id' => $user->id,
        'account_id' => $account->id,
        'row_number' => 2,
        'reason_code' => ImportFailedRowReason::InvalidDate,
        'date_raw' => 'bad-date',
        'amount_raw' => '-10.00',
        'description_raw' => 'Coffee',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->post(route('import-failed-rows.dismiss', $failedRow))
        ->assertRedirect(route('transactions.index'));

    expect($failedRow->fresh()->dismissed_at)->not->toBeNull();

    $this->actingAs($otherUser)
        ->post(route('import-failed-rows.dismiss', $failedRow))
        ->assertForbidden();
});

test('owner can dismiss all unresolved failed rows optionally scoped to account', function () {
    $plnId = Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();

    $accountA = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'A',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $accountB = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'B',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = Import::query()->create([
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'status' => 'committed',
    ]);

    ImportFailedRow::query()->create([
        'import_id' => $import->id,
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'row_number' => 1,
        'reason_code' => ImportFailedRowReason::InvalidDate,
        'date_raw' => 'bad',
        'amount_raw' => '1',
        'description_raw' => 'A row',
    ]);

    ImportFailedRow::query()->create([
        'import_id' => $import->id,
        'user_id' => $user->id,
        'account_id' => $accountB->id,
        'row_number' => 2,
        'reason_code' => ImportFailedRowReason::InvalidAmount,
        'date_raw' => '24-04-2026',
        'amount_raw' => 'x',
        'description_raw' => 'B row',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->post(route('import-failed-rows.dismiss-all'), ['account_id' => $accountA->id])
        ->assertRedirect(route('transactions.index'));

    expect(ImportFailedRow::query()->unresolved()->count())->toBe(1)
        ->and(ImportFailedRow::query()->unresolved()->value('account_id'))->toBe($accountB->id);
});

test('already dismissed import failed row cannot be dismissed again', function () {
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
        'status' => 'committed',
    ]);

    $failedRow = ImportFailedRow::query()->create([
        'import_id' => $import->id,
        'user_id' => $user->id,
        'account_id' => $account->id,
        'row_number' => 1,
        'reason_code' => ImportFailedRowReason::InvalidDate,
        'date_raw' => 'bad',
        'amount_raw' => '1',
        'description_raw' => 'Coffee',
        'dismissed_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('import-failed-rows.dismiss', $failedRow))
        ->assertForbidden();
});
