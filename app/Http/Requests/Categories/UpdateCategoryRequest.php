<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Models\Category;
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Category $category */
        $category = $this->route('category');

        return $this->user()?->can('update', $category) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where(fn ($q) => $q->where('user_id', $this->user()->id))
                    ->ignore($category->id),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'icon' => ['sometimes', 'required', 'string', Rule::in(CategoryIcons::values())],
            'color' => ['sometimes', 'required', 'string', Rule::in(CategoryColors::values())],
        ];
    }
}
