<?php

declare(strict_types=1);

namespace App\Support\Categories;

use App\Enums\CategoryType;
use App\Enums\TransactionType;

final class CategoryForTransactionType
{
    public static function categoryTypeFor(TransactionType $transactionType): ?CategoryType
    {
        return match ($transactionType) {
            TransactionType::Income => CategoryType::Income,
            TransactionType::Expense => CategoryType::Expense,
            TransactionType::Transfer, TransactionType::Adjustment => null,
        };
    }
}
