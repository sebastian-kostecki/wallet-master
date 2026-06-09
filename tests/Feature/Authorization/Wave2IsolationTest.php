<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Enums\TransferMatchStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Pocket;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('users cannot edit another users category', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('categories.edit', $category))
        ->assertForbidden();
});

test('users cannot update another users category', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    $this->actingAs($other)
        ->put(route('categories.update', $category), ['name' => 'Stolen'])
        ->assertForbidden();
});

test('users cannot delete another users category', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    $this->actingAs($other)
        ->delete(route('categories.destroy', $category))
        ->assertForbidden();
});

test('users cannot edit another users pocket', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $pocket = Pocket::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('pockets.edit', $pocket))
        ->assertForbidden();
});

test('users cannot delete another users pocket', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $pocket = Pocket::factory()->for($owner)->create();

    $this->actingAs($other)
        ->delete(route('pockets.destroy', $pocket))
        ->assertForbidden();
});

test('budget monthly does not expose another users category execution', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $ownerCategory = Category::factory()->for($owner)->create([
        'name' => 'OwnerOnlyFood',
        'type' => CategoryType::Expense,
    ]);

    $ownerAccount = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'Owner ROR',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => -100,
    ]);

    Transaction::query()->create(array_merge([
        'user_id' => $owner->id,
        'normalized_description' => 'owner food',
        'dedupe_hash' => md5('owner-food', true),
        'description' => 'Groceries',
        'subject' => null,
    ], transactionWithCategory($owner, [
        'account_id' => $ownerAccount->id,
        'currency_id' => $plnId,
        'category_id' => $ownerCategory->id,
        'date' => '2026-04-15',
        'booked_at' => '2026-04-15',
        'amount' => '-100.00',
        'type' => TransactionType::Expense,
    ])));

    $this->actingAs($other)
        ->get(route('budget.monthly', ['year' => 2026, 'month' => 4]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows')
            ->where('rows', fn ($rows) => collect($rows)->doesntContain(
                fn (array $row): bool => ($row['name'] ?? null) === 'OwnerOnlyFood'
            )));
});

test('other user cannot unlink another users transfer', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $transferId = (string) Str::uuid();

    $from = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'From',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => -50,
    ]);

    $to = Account::query()->create([
        'user_id' => $owner->id,
        'currency_id' => $plnId,
        'name' => 'To',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 50,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $from->id,
        'currency_id' => $plnId,
        'date' => '2026-04-01',
        'booked_at' => '2026-04-01',
        'amount' => '-50.00',
        'type' => TransactionType::Transfer,
        'description' => 'Transfer out',
        'subject' => null,
        'normalized_description' => 'transfer out',
        'dedupe_hash' => md5('iso-unlink-out', true),
        'transfer_id' => $transferId,
        'transfer_match_status' => TransferMatchStatus::Auto,
        'category_id' => null,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $to->id,
        'currency_id' => $plnId,
        'date' => '2026-04-01',
        'booked_at' => '2026-04-01',
        'amount' => '50.00',
        'type' => TransactionType::Transfer,
        'description' => 'Transfer in',
        'subject' => null,
        'normalized_description' => 'transfer in',
        'dedupe_hash' => md5('iso-unlink-in', true),
        'transfer_id' => $transferId,
        'transfer_match_status' => TransferMatchStatus::Auto,
        'category_id' => null,
    ]);

    $this->actingAs($other)
        ->post(route('transfers.unlink', $transferId))
        ->assertStatus(409);
});
