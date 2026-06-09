<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

final class DeleteCategory
{
    public function handle(Category $category): void
    {
        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => 'System categories cannot be deleted.',
            ]);
        }

        if ($category->transactions()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete category with transactions.',
            ]);
        }

        $category->delete();
    }
}
