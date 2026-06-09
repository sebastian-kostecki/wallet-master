<?php

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Pocket;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CurrencySeeder;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
});

test('user can create list update and delete pocket without transactions', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ])->assertRedirect();
    $pocket = Pocket::where('user_id', $user->id)->where('name', 'Wakacje')->first();
    expect($pocket)->not->toBeNull();

    $this->actingAs($user)->patch(route('pockets.update', $pocket), ['name' => 'Wakacje 2026'])->assertRedirect();
    expect($pocket->fresh()->name)->toBe('Wakacje 2026');

    $this->actingAs($user)->delete(route('pockets.destroy', $pocket))->assertRedirect();
    expect(Pocket::find($pocket->id))->toBeNull();
});

test('pockets index only lists own pockets', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($userA)->post(route('pockets.store'), [
        'name' => 'Only A',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($userB)->get(route('pockets.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('pockets')
        ->where('pockets', fn ($pockets) => collect($pockets)->pluck('name')->doesntContain('Only A'))
    );
});

// Requires transactions.pocket_id column (Task 5).
test('cannot delete pocket with linked transactions', function () {
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id]);
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
        'pocket_id' => $pocket->id,
        'date' => '2026-04-10',
        'booked_at' => '2026-04-10',
        'amount' => '-10.00',
        'type' => TransactionType::Expense,
        'description' => 'Coffee',
        'normalized_description' => 'coffee',
        'dedupe_hash' => md5('pocket-linked', true),
    ]);

    $this->actingAs($user)->delete(route('pockets.destroy', $pocket))->assertForbidden();
    expect(Pocket::find($pocket->id))->not->toBeNull();
});

test('user can create pocket with initial balance', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Wakacje',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
        'initial_balance' => '1500.50',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $pocket = Pocket::query()->where('user_id', $user->id)->where('name', 'Wakacje')->first();

    expect($pocket)->not->toBeNull();
    expect((string) $pocket->initial_balance)->toBe('1500.50');
});

test('create pocket without initial balance defaults to zero', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Bez kwoty',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $pocket = Pocket::query()->where('user_id', $user->id)->where('name', 'Bez kwoty')->first();

    expect((string) $pocket->initial_balance)->toBe('0.00');
});

test('update pocket cannot change initial balance', function () {
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create([
        'user_id' => $user->id,
        'initial_balance' => '500.00',
    ]);

    $this->actingAs($user)->patch(route('pockets.update', $pocket), [
        'name' => $pocket->name,
        'icon' => $pocket->icon,
        'color' => $pocket->color,
        'initial_balance' => '999.00',
    ])->assertSessionHasErrors('initial_balance');

    expect((string) $pocket->fresh()->initial_balance)->toBe('500.00');
});

test('pockets index balance includes initial balance', function () {
    $user = User::factory()->create();
    $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');

    $this->actingAs($user)->post(route('pockets.store'), [
        'name' => 'Start',
        'icon' => 'target',
        'color' => '#6366f1',
        'currency_id' => $plnId,
        'initial_balance' => '300',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->get(route('pockets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pockets', 1)
            ->where('pockets.0.balance', '300.00')
        );
});
