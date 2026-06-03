<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use App\Telemetry\Event;
use Illuminate\Validation\ValidationException;

final class UpdateCategory
{
    /**
     * @param  array{name?: string, sort_order?: int}  $validated
     */
    public function handle(Category $category, array $validated): Category
    {
        if (isset($validated['name'])) {
            $category->name = $validated['name'];
        }

        if (isset($validated['sort_order'])) {
            $category->sort_order = $validated['sort_order'];
        }

        $category->save();

        Event::record('category_updated', ['category_id' => $category->id], $category->user_id);

        return $category;
    }

    /**
     * @throws ValidationException
     */
    public function assertTypeChangeAllowed(Category $category, string $newType): void
    {
        if ($category->type->value === $newType) {
            return;
        }

        if ($category->transactions()->exists()) {
            throw ValidationException::withMessages([
                'type' => 'Cannot change category type when transactions exist.',
            ]);
        }
    }
}
