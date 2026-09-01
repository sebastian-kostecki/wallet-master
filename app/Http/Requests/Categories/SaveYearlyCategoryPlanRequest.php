<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class SaveYearlyCategoryPlanRequest extends FormRequest
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
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'annual_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'monthly_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    /**
     * @return array{
     *   year: int,
     *   annual_amount: numeric-string|float|int|null,
     *   monthly_amount?: numeric-string|float|int|null,
     * }
     */
    public function validated($key = null, $default = null): array
    {
        return parent::validated($key, $default);
    }
}
