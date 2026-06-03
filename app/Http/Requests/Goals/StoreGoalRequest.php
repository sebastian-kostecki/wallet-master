<?php

declare(strict_types=1);

namespace App\Http\Requests\Goals;

use App\Models\Goal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Goal::class) ?? false;
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
                Rule::unique('goals', 'name')->where(fn ($q) => $q->where('user_id', $this->user()->id)),
            ],
        ];
    }
}
