<?php

declare(strict_types=1);

namespace App\Support\Categories;

use App\Enums\CategoryType;

final class CategoryDefaults
{
    /**
     * @return list<array{name: string, type: CategoryType, icon: string, color: string, sort_order: int, is_system: bool}>
     */
    public static function starterRows(): array
    {
        return [
            // Income
            ['name' => 'Wynagrodzenie', 'type' => CategoryType::Income, 'icon' => 'briefcase', 'color' => '#10b981', 'sort_order' => 1, 'is_system' => false],
            ['name' => 'Bonus', 'type' => CategoryType::Income, 'icon' => 'gift', 'color' => '#06b6d4', 'sort_order' => 2, 'is_system' => false],
            ['name' => 'Praca freelance', 'type' => CategoryType::Income, 'icon' => 'laptop', 'color' => '#f59e0b', 'sort_order' => 3, 'is_system' => false],
            ['name' => 'Odsetki', 'type' => CategoryType::Income, 'icon' => 'trending-up', 'color' => '#8b5cf6', 'sort_order' => 4, 'is_system' => false],
            ['name' => 'Inne przychody', 'type' => CategoryType::Income, 'icon' => 'plus-circle', 'color' => '#6366f1', 'sort_order' => 5, 'is_system' => false],
            // Expense
            ['name' => 'Artykuły spożywcze', 'type' => CategoryType::Expense, 'icon' => 'shopping-cart', 'color' => '#ef4444', 'sort_order' => 1, 'is_system' => false],
            ['name' => 'Transport', 'type' => CategoryType::Expense, 'icon' => 'car', 'color' => '#f97316', 'sort_order' => 2, 'is_system' => false],
            ['name' => 'Media', 'type' => CategoryType::Expense, 'icon' => 'zap', 'color' => '#eab308', 'sort_order' => 3, 'is_system' => false],
            ['name' => 'Rozrywka', 'type' => CategoryType::Expense, 'icon' => 'film', 'color' => '#ec4899', 'sort_order' => 4, 'is_system' => false],
            ['name' => 'Restauracje', 'type' => CategoryType::Expense, 'icon' => 'utensils', 'color' => '#d946ef', 'sort_order' => 5, 'is_system' => false],
            ['name' => 'Zakupy', 'type' => CategoryType::Expense, 'icon' => 'shopping-bag', 'color' => '#06b6d4', 'sort_order' => 6, 'is_system' => false],
            ['name' => 'Zdrowie', 'type' => CategoryType::Expense, 'icon' => 'heart', 'color' => '#10b981', 'sort_order' => 7, 'is_system' => false],
            ['name' => 'Edukacja', 'type' => CategoryType::Expense, 'icon' => 'book-open', 'color' => '#8b5cf6', 'sort_order' => 8, 'is_system' => false],
            ['name' => 'Ubezpieczenie', 'type' => CategoryType::Expense, 'icon' => 'shield', 'color' => '#6366f1', 'sort_order' => 9, 'is_system' => false],
            ['name' => 'Czynsz', 'type' => CategoryType::Expense, 'icon' => 'home', 'color' => '#3b82f6', 'sort_order' => 10, 'is_system' => false],
            ['name' => 'Telefon', 'type' => CategoryType::Expense, 'icon' => 'smartphone', 'color' => '#0ea5e9', 'sort_order' => 11, 'is_system' => false],
            ['name' => 'Internet', 'type' => CategoryType::Expense, 'icon' => 'wifi', 'color' => '#06b6d4', 'sort_order' => 12, 'is_system' => false],
            ['name' => 'Siłownia', 'type' => CategoryType::Expense, 'icon' => 'activity', 'color' => '#10b981', 'sort_order' => 13, 'is_system' => false],
            ['name' => 'Subskrypcje', 'type' => CategoryType::Expense, 'icon' => 'repeat', 'color' => '#14b8a6', 'sort_order' => 14, 'is_system' => false],
            ['name' => 'Naprawy', 'type' => CategoryType::Expense, 'icon' => 'wrench', 'color' => '#f59e0b', 'sort_order' => 15, 'is_system' => false],
            ['name' => 'Prezenty', 'type' => CategoryType::Expense, 'icon' => 'gift', 'color' => '#f97316', 'sort_order' => 16, 'is_system' => false],
            ['name' => 'Podróże', 'type' => CategoryType::Expense, 'icon' => 'plane', 'color' => '#fd7e14', 'sort_order' => 17, 'is_system' => false],
            ['name' => 'Zwierzęta', 'type' => CategoryType::Expense, 'icon' => 'paw-print', 'color' => '#ff922b', 'sort_order' => 18, 'is_system' => false],
            ['name' => 'Pielęgnacja osobista', 'type' => CategoryType::Expense, 'icon' => 'scissors', 'color' => '#ff6b6b', 'sort_order' => 19, 'is_system' => false],
            ['name' => 'Inne wydatki', 'type' => CategoryType::Expense, 'icon' => 'minus-circle', 'color' => '#868e96', 'sort_order' => 20, 'is_system' => false],
            ['name' => 'Oszczędności', 'type' => CategoryType::Expense, 'icon' => 'piggy-bank', 'color' => '#10b981', 'sort_order' => 25, 'is_system' => true],
        ];
    }
}
