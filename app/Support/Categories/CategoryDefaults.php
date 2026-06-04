<?php

declare(strict_types=1);

namespace App\Support\Categories;

use App\Enums\CategoryType;

final class CategoryDefaults
{
    /**
     * @return list<array{name: string, type: CategoryType, sort_order: int, is_system: bool}>
     */
    public static function starterRows(): array
    {
        return [
            ['name' => 'Jedzenie', 'type' => CategoryType::Expense, 'sort_order' => 10, 'is_system' => false],
            ['name' => 'Transport', 'type' => CategoryType::Expense, 'sort_order' => 20, 'is_system' => false],
            ['name' => 'Mieszkanie', 'type' => CategoryType::Expense, 'sort_order' => 30, 'is_system' => false],
            ['name' => 'Zdrowie', 'type' => CategoryType::Expense, 'sort_order' => 40, 'is_system' => false],
            ['name' => 'Rozrywka', 'type' => CategoryType::Expense, 'sort_order' => 50, 'is_system' => false],
            ['name' => 'Oszczędności', 'type' => CategoryType::Expense, 'sort_order' => 60, 'is_system' => true],
            ['name' => 'Inne', 'type' => CategoryType::Expense, 'sort_order' => 90, 'is_system' => false],
            ['name' => 'Pensja', 'type' => CategoryType::Income, 'sort_order' => 10, 'is_system' => false],
            ['name' => 'Inne przychody', 'type' => CategoryType::Income, 'sort_order' => 90, 'is_system' => false],
        ];
    }
}
