<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;
use App\Support\Categories\CategoryDefaults;
use Illuminate\Support\Facades\DB;

final class EnsureUserCategories
{
    public function handle(User $user): void
    {
        if (Category::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $timestamp = now();

            foreach (CategoryDefaults::starterRows() as $row) {
                Category::query()->create([
                    'user_id' => $user->id,
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'sort_order' => $row['sort_order'],
                    'is_system' => $row['is_system'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        });
    }
}
