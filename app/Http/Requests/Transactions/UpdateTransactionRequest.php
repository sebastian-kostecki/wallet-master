<?php

namespace App\Http\Requests\Transactions;

use App\Http\Requests\Concerns\ValidatesCategoryId;
use App\Http\Requests\Concerns\ValidatesGoalId;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateTransactionRequest extends FormRequest
{
    use ValidatesCategoryId;
    use ValidatesGoalId;

    public function authorize(): bool
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');

        return $this->user()?->can('update', $transaction) ?? false;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount' => 'Kwoty połączonego transferu nie można zmienić. Najpierw rozłącz transfer.',
            'account_id' => 'Konta połączonego transferu nie można zmienić. Najpierw rozłącz transfer.',
        ];
    }

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
            ...$this->optionalGoalIdRules(),
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
     *   goal_id?: ?int,
     * }
     */
    public function validated($key = null, $default = null): array
    {
        return parent::validated($key, $default);
    }
}
