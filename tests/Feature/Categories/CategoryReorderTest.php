<?php

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;

test('user can reorder expense categories', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $expenseIds = Category::query()
        ->where('user_id', $user->id)
        ->where('type', CategoryType::Expense)
        ->ordered()
        ->pluck('id')
        ->all();

    $reorderedIds = array_reverse($expenseIds);

    $this->actingAs($user)
        ->patch(route('categories.reorder'), [
            'type' => CategoryType::Expense->value,
            'ids' => $reorderedIds,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $updatedOrder = Category::query()
        ->where('user_id', $user->id)
        ->where('type', CategoryType::Expense)
        ->ordered()
        ->pluck('id')
        ->all();

    expect($updatedOrder)->toBe($reorderedIds);

    $sortOrders = Category::query()
        ->where('user_id', $user->id)
        ->where('type', CategoryType::Expense)
        ->ordered()
        ->pluck('sort_order')
        ->all();

    expect($sortOrders)->toBe([10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150, 160, 170, 180, 190, 200, 210]);
});

test('cannot reorder with another users category id', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    ensureUserCategories($userA);
    ensureUserCategories($userB);

    $userAExpenseIds = Category::query()
        ->where('user_id', $userA->id)
        ->where('type', CategoryType::Expense)
        ->ordered()
        ->pluck('id')
        ->all();

    $foreignId = Category::query()
        ->where('user_id', $userB->id)
        ->where('type', CategoryType::Expense)
        ->ordered()
        ->value('id');

    $idsWithForeign = $userAExpenseIds;
    $idsWithForeign[0] = $foreignId;

    $this->actingAs($userA)
        ->patch(route('categories.reorder'), [
            'type' => CategoryType::Expense->value,
            'ids' => $idsWithForeign,
        ])
        ->assertSessionHasErrors('ids');
});

test('cannot reorder with category id from different type', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $expenseIds = Category::query()
        ->where('user_id', $user->id)
        ->where('type', CategoryType::Expense)
        ->ordered()
        ->pluck('id')
        ->all();

    $incomeId = Category::query()
        ->where('user_id', $user->id)
        ->where('type', CategoryType::Income)
        ->ordered()
        ->value('id');

    $idsWithWrongType = $expenseIds;
    $idsWithWrongType[0] = $incomeId;

    $this->actingAs($user)
        ->patch(route('categories.reorder'), [
            'type' => CategoryType::Expense->value,
            'ids' => $idsWithWrongType,
        ])
        ->assertSessionHasErrors('ids');
});

test('cannot reorder with incomplete category list', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $partialIds = Category::query()
        ->where('user_id', $user->id)
        ->where('type', CategoryType::Expense)
        ->ordered()
        ->limit(3)
        ->pluck('id')
        ->all();

    $this->actingAs($user)
        ->patch(route('categories.reorder'), [
            'type' => CategoryType::Expense->value,
            'ids' => $partialIds,
        ])
        ->assertSessionHasErrors('ids');
});
