<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Imports\Workflow\CommitImport;
use App\Integrations\DescriptionMemory\DescriptionMemoryRepository;
use App\Integrations\DescriptionMemory\SuggestedFields;
use App\Jobs\CommitImportJob;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDescriptionMemoryRepository;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    Storage::fake('local');
});

test('import assigns category from description memory when suggestion matches type', function () {
    $fakeRepo = new FakeDescriptionMemoryRepository;
    app()->instance(DescriptionMemoryRepository::class, $fakeRepo);

    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $transport = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Transport')
        ->firstOrFail();

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'BNP',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = createImportWithFile(
        $user,
        $account,
        "date;amount;description;subject\n10-03-2026;-50.00;FUEL STATION 123;Station"
    );

    $fakeRepo->setSuggestion(
        userId: $user->id,
        bank: Bank::BnpParibas,
        rawStatementDescription: 'FUEL STATION 123',
        suggestedFields: new SuggestedFields(
            subject: 'Station',
            description: 'Fuel',
            matchType: 'exact',
            score: 100,
            categoryId: $transport->id,
        ),
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $import->id)->firstOrFail();

    expect($transaction->category_id)->toBe($transport->id);
});

test('import assigns first expense category when memory misses', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $firstExpenseId = defaultCategoryId($user, CategoryType::Expense);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'BNP',
        'bank' => Bank::BnpParibas,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $import = createImportWithFile(
        $user,
        $account,
        "date;amount;description;subject\n10-03-2026;-25.00;UNKNOWN SHOP;"
    );

    app(CommitImportJob::class, ['importId' => $import->id])->handle(app(CommitImport::class));

    $transaction = Transaction::query()->where('import_id', $import->id)->firstOrFail();

    expect($transaction->category_id)->toBe($firstExpenseId);
});

test('update transaction remembers category in description memory', function () {
    $fakeRepo = new FakeDescriptionMemoryRepository;
    app()->instance(DescriptionMemoryRepository::class, $fakeRepo);

    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    ensureUserCategories($user);

    $transport = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Transport')
        ->firstOrFail();

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
        'status' => 'committed',
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => defaultCategoryId($user),
        'import_id' => $import->id,
        'date' => '2026-03-10',
        'booked_at' => '2026-03-10',
        'amount' => '-10.00',
        'type' => TransactionType::Expense,
        'description' => 'Fuel',
        'normalized_description' => 'fuel',
        'dedupe_hash' => md5('fuel-tx', true),
        'raw_statement_description' => 'RAW FUEL 123',
    ]);

    $this->actingAs($user)->put(route('transactions.update', $transaction, absolute: false), [
        'account_id' => $account->id,
        'date' => '10-03-2026',
        'amount' => -10,
        'description' => 'Fuel updated',
        'category_id' => $transport->id,
    ])->assertSessionHasNoErrors();

    expect($fakeRepo->rememberCalls)->toHaveCount(1);
    expect($fakeRepo->rememberCalls[0]['category_id'])->toBe($transport->id);
    expect($fakeRepo->rememberCalls[0]['raw'])->toBe('RAW FUEL 123');
});
