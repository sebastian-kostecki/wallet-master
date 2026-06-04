<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can create list update and delete goal without transactions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('goals.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
    ])->assertRedirect();
    $goal = Goal::where('user_id', $user->id)->where('name', 'Wakacje')->first();
    expect($goal)->not->toBeNull();

    $this->actingAs($user)->patch(route('goals.update', $goal), ['name' => 'Wakacje 2026'])->assertRedirect();
    expect($goal->fresh()->name)->toBe('Wakacje 2026');

    $this->actingAs($user)->delete(route('goals.destroy', $goal))->assertRedirect();
    expect(Goal::find($goal->id))->toBeNull();
});

test('goals index only lists own goals', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA)->post(route('goals.store'), [
        'name' => 'Only A',
        'icon' => 'target',
        'color' => '#6366f1',
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($userB)->get(route('goals.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('goals')
        ->where('goals', fn ($goals) => collect($goals)->pluck('name')->doesntContain('Only A'))
    );
});

// Requires transactions.goal_id column (Task 5).
test('cannot delete goal with linked transactions', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id]);
    $categoryId = defaultCategoryId($user);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'currency_id' => $plnId,
        'name' => 'Cash',
        'bank' => Bank::Cash,
        'type' => AccountType::Checking,
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'currency_id' => $plnId,
        'category_id' => $categoryId,
        'goal_id' => $goal->id,
        'date' => '2026-04-10',
        'booked_at' => '2026-04-10',
        'amount' => '-10.00',
        'type' => TransactionType::Expense,
        'description' => 'Coffee',
        'normalized_description' => 'coffee',
        'dedupe_hash' => md5('goal-linked', true),
    ]);

    $this->actingAs($user)->delete(route('goals.destroy', $goal))->assertForbidden();
    expect(Goal::find($goal->id))->not->toBeNull();
});
