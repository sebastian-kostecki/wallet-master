<?php

declare(strict_types=1);

namespace App\Support\Categories;

use App\Actions\Categories\EnsureUserCategories;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;

final class DefaultCategoryId
{
    public static function for(User $user, TransactionType $transactionType): int
    {
        app(EnsureUserCategories::class)->handle($user);

        $categoryType = CategoryForTransactionType::categoryTypeFor($transactionType);

        if ($categoryType !== null) {
            return self::firstIdByType($user, $categoryType);
        }

        if ($transactionType === TransactionType::Transfer) {
            $savingsId = Category::query()
                ->where('user_id', $user->id)
                ->where('is_system', true)
                ->where('name', 'Oszczędności')
                ->value('id');

            if ($savingsId !== null) {
                return (int) $savingsId;
            }
        }

        return self::firstIdByType($user, CategoryType::Expense);
    }

    private static function firstIdByType(User $user, CategoryType $type): int
    {
        return (int) Category::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->ordered()
            ->value('id');
    }
}
