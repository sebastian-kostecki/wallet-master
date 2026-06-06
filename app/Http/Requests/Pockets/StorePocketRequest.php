<?php

declare(strict_types=1);

namespace App\Http\Requests\Pockets;

use App\Enums\PocketPlanningMode;
use App\Models\Pocket;
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePocketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Pocket::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('target_amount') === '' || $this->input('target_amount') === null) {
            $this->merge([
                'target_amount' => null,
                'planning_mode' => null,
                'monthly_contribution' => null,
                'target_date' => null,
            ]);
        }
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
                Rule::unique('pockets', 'name')->where(fn ($q) => $q->where('user_id', $this->user()->id)),
            ],
            'icon' => ['required', 'string', Rule::in(CategoryIcons::values())],
            'color' => ['required', 'string', Rule::in(CategoryColors::values())],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'planning_mode' => ['nullable', Rule::enum(PocketPlanningMode::class), 'required_with:target_amount'],
            'monthly_contribution' => ['nullable', 'numeric', 'min:0', 'required_if:planning_mode,monthly', 'prohibited_if:planning_mode,by_date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:today', 'required_if:planning_mode,by_date', 'prohibited_if:planning_mode,monthly'],
        ];
    }
}
