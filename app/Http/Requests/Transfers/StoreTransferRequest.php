<?php

namespace App\Http\Requests\Transfers;

use App\Events\TransferFailedValidation;
use App\Http\Requests\Concerns\ValidatesCategoryId;
use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTransferRequest extends FormRequest
{
    use ValidatesCategoryId;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        $accountExistsRule = Rule::exists('accounts', 'id')
            ->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('user_id', $userId)
            );

        return [
            'from_account_id' => [
                'required',
                'integer',
                $accountExistsRule,
            ],
            'to_account_id' => [
                'required',
                'integer',
                'different:from_account_id',
                $accountExistsRule,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $fromId = $this->input('from_account_id');
                    $toId = $this->input('to_account_id');

                    if (! is_numeric($fromId) || ! is_numeric($toId)) {
                        return;
                    }

                    $ids = [(int) $fromId, (int) $toId];

                    $currencies = Account::query()
                        ->whereIn('id', $ids)
                        ->where('user_id', $this->user()->id)
                        ->whereNull('deleted_at')
                        ->pluck('currency_id', 'id');

                    if ($currencies->count() !== 2) {
                        return;
                    }

                    if ((int) $currencies[$ids[0]] !== (int) $currencies[$ids[1]]) {
                        $fail('Transfers between different currencies are not supported.');
                    }
                },
            ],
            'date' => ['required', 'date_format:d-m-Y'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:255'],
            ...$this->categoryIdRules(),
        ];
    }

    /**
     * @return array{
     *   from_account_id: int,
     *   to_account_id: int,
     *   date: string,
     *   amount: numeric-string|float|int,
     *   description?: ?string,
     *   subject?: ?string,
     *   category_id: int,
     * }
     */
    public function validated($key = null, $default = null): array
    {
        return parent::validated($key, $default);
    }

    protected function failedValidation(Validator $validator): void
    {
        event(new TransferFailedValidation(
            userId: $this->user()?->id,
            fields: array_keys($validator->errors()->toArray()),
            errors: $validator->errors()->toArray(),
        ));

        parent::failedValidation($validator);
    }
}
