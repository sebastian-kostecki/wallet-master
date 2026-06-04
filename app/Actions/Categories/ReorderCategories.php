<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderCategories
{
    /**
     * @param  list<int>  $orderedIds
     *
     * @throws ValidationException
     */
    public function handle(User $user, CategoryType $type, array $orderedIds): void
    {
        $expectedCount = Category::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->count();

        if (count($orderedIds) !== $expectedCount) {
            throw ValidationException::withMessages([
                'ids' => 'The category list must include every category of this type.',
            ]);
        }

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');

        if ($categories->count() !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'ids' => 'One or more categories are invalid for this user or type.',
            ]);
        }

        DB::transaction(function () use ($orderedIds, $categories): void {
            $sortOrder = 10;

            foreach ($orderedIds as $id) {
                $category = $categories->get($id);

                if ($category === null) {
                    continue;
                }

                $category->sort_order = $sortOrder;
                $category->save();
                $sortOrder += 10;
            }
        });
    }
}
