<?php

declare(strict_types=1);

namespace App\Http\Requests\Goals;

use App\Models\Goal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class SaveMonthlyEstimateRequest extends FormRequest
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
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }
}
