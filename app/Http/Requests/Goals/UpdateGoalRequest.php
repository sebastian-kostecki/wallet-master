<?php

declare(strict_types=1);

namespace App\Http\Requests\Goals;

use App\Models\Goal;
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

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Goal $goal */
        $goal = $this->route('goal');

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
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
