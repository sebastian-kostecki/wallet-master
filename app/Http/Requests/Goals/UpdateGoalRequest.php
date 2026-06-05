<?php

declare(strict_types=1);

namespace App\Http\Requests\Goals;

use App\Enums\GoalPlanningMode;
use App\Models\Goal;
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Goal $goal */
        $goal = $this->route('goal');

        return $this->user()?->can('update', $goal) ?? false;
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
        /** @var Goal $goal */
        $goal = $this->route('goal');

        $hasTarget = $this->filled('target_amount') || ($goal->target_amount !== null && ! $this->has('target_amount'));

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('goals', 'name')
                    ->where(fn ($q) => $q->where('user_id', $this->user()->id))
                    ->ignore($goal->id),
            ],
            'icon' => ['sometimes', 'required', 'string', Rule::in(CategoryIcons::values())],
            'color' => ['sometimes', 'required', 'string', Rule::in(CategoryColors::values())],
            'currency_id' => ['prohibited'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'planning_mode' => ['nullable', Rule::enum(GoalPlanningMode::class), Rule::requiredIf($hasTarget && $this->filled('target_amount'))],
            'monthly_contribution' => ['nullable', 'numeric', 'min:0', 'required_if:planning_mode,monthly', 'prohibited_if:planning_mode,by_date'],
            'target_date' => ['nullable', 'date', 'required_if:planning_mode,by_date', 'prohibited_if:planning_mode,monthly'],
            'is_archived' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
