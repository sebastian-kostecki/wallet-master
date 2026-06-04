<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use App\Telemetry\Event;

final class StoreCategory
{
    /**
     * @param  array{name: string, type: string}  $validated
     */
    public function handle(User $user, array $validated): Category
    {
        $maxSort = (int) Category::query()
            ->where('user_id', $user->id)
            ->where('type', $validated['type'])
            ->max('sort_order');

        $category = Category::query()->create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'type' => CategoryType::from($validated['type']),
            'sort_order' => $maxSort + 10,
            'is_system' => false,
        ]);

        Event::record('category_created', ['category_id' => $category->id], $user->id);

        return $category;
    }
}
