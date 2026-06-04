<?php

declare(strict_types=1);

namespace App\Data\Goals;

use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;

final class GoalFormOptions
{
    /**
     * @return array{
     *   icons: list<array{value: string, label_key: string}>,
     *   colors: list<array{value: string}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'icons' => array_map(
                fn (string $icon): array => [
                    'value' => $icon,
                    'label_key' => 'categories.icons.'.$icon,
                ],
                CategoryIcons::values(),
            ),
            'colors' => array_map(
                fn (string $hex): array => ['value' => $hex],
                CategoryColors::values(),
            ),
        ];
    }
}
