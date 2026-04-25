<?php

namespace App\Http\Requests\Transactions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransactionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.date_format' => 'Podaj datę w formacie DD-MM-YYYY.',
            'to.date_format' => 'Podaj datę w formacie DD-MM-YYYY.',
            'from.before_or_equal' => 'Data „Od” nie może być późniejsza niż „Do”.',
            'to.after_or_equal' => 'Data „Do” nie może być wcześniejsza niż „Od”.',
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accountExistsRule = Rule::exists('accounts', 'id')
            ->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('user_id', $this->user()->id)
            );

        return [
            'account_id' => [
                'nullable',
                'integer',
                $accountExistsRule,
            ],
            'from' => ['nullable', 'date_format:d-m-Y', 'before_or_equal:to'],
            'to' => ['nullable', 'date_format:d-m-Y', 'after_or_equal:from'],
            'sort' => ['nullable', 'string', 'in:date,amount'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
