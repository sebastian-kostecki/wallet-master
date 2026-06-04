<?php

declare(strict_types=1);

namespace App\Http\Requests\Budgets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class MonthlyBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function getYear(): int
    {
        $year = $this->integer('year');

        return $year > 0 ? $year : (int) now()->year;
    }

    public function getMonth(): int
    {
        $month = $this->integer('month');

        return $month >= 1 && $month <= 12 ? $month : (int) now()->month;
    }
}
