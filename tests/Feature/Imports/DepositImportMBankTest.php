<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\DepositMatchStatus;
use App\Enums\DepositMovementKind;
use App\Imports\Workflow\CommitImport;
use App\Jobs\CommitImportJob;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

test('mBank import auto opens deposit for Lokaty i konto oszcz. row', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'mBank ROR',
        'bank' => Bank::MBank,
        'type' => AccountType::Checking,
        'opening_balance' => '10000.00',
        'current_balance' => '10000.00',
    ]);

    $import = createImportWithFixture(
        $user,
        $account,
        'tests/Fixtures/import/mbank-deposit-open.csv',
        [
            'date' => 'Data operacji',
            'amount' => 'Kwota',
            'description' => 'Opis operacji',
        ],
        ['Data operacji', 'Opis operacji', 'Rachunek', 'Kategoria', 'Kwota'],
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $import->id)->sole();
    expect($transaction->deposit_match_status)->toBe(DepositMatchStatus::Auto);
    expect($transaction->category_id)->toBeNull();
    expect($transaction->depositMovement?->kind)->toBe(DepositMovementKind::Open);
    expect(Deposit::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($account->fresh()->current_balance)->toBe('5000.00');
});
