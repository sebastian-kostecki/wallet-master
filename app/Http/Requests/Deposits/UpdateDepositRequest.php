<?php

declare(strict_types=1);

namespace App\Http\Requests\Deposits;

use App\Models\Deposit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $deposit = $this->route('deposit');

        if ($deposit instanceof Deposit && $deposit->opened_at !== null) {
            $this->merge([
                'opened_at' => $deposit->opened_at->format('d-m-Y'),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'matures_at' => ['nullable', 'date_format:d-m-Y', 'after_or_equal:opened_at'],
        ];
    }
}
