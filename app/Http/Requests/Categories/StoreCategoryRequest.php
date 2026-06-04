<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->where(fn ($q) => $q->where('user_id', $this->user()->id)),
            ],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'icon' => ['required', 'string', Rule::in(CategoryIcons::values())],
            'color' => ['required', 'string', Rule::in(CategoryColors::values())],
        ];
    }
}
