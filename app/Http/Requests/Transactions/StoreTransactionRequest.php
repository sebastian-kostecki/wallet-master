<?php

namespace App\Http\Requests\Transactions;

use App\Http\Requests\Concerns\ValidatesCategoryId;
use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreTransactionRequest extends FormRequest
{
    use ValidatesCategoryId;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Transaction::class) ?? false;
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
                'required',
                'integer',
                $accountExistsRule,
            ],
            'date' => ['required', 'date_format:d-m-Y'],
            'booked_at' => ['nullable', 'date_format:d-m-Y'],
            'amount' => ['required', 'numeric', 'decimal:0,2', Rule::notIn([0])],
            'description' => ['required', 'string', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:255'],
            ...$this->categoryIdRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateCategoryMatchesAmount($validator);
    }

    /**
     * @return array{
     *   account_id: int,
     *   date: string,
     *   booked_at?: ?string,
     *   amount: numeric-string|float|int,
     *   description: string,
     *   subject?: ?string,
     *   category_id: int,
     * }
     */
    public function validated($key = null, $default = null): array
    {
        return parent::validated($key, $default);
    }
}
