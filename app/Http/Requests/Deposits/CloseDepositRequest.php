<?php

declare(strict_types=1);

namespace App\Http\Requests\Deposits;

use App\Enums\Bank;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Deposit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CloseDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deposit = $this->route('deposit');

        return $deposit instanceof Deposit
            && $this->user()?->can('update', $deposit) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $bankAccountRule = Rule::exists('accounts', 'id')
            ->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('user_id', $this->user()->id)
                ->where('bank', '!=', Bank::Cash->value)
            );

        $incomeCategoryRule = Rule::exists('categories', 'id')
            ->where(fn ($query) => $query
                ->where('user_id', $this->user()->id)
                ->where('type', CategoryType::Income->value)
            );

        return [
            'destination_account_id' => [
                'required',
                'integer',
                $bankAccountRule,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $deposit = $this->route('deposit');

                    if (! $deposit instanceof Deposit) {
                        return;
                    }

                    if (! is_numeric($value)) {
                        return;
                    }

                    $account = Account::query()->find((int) $value);

                    if ($account !== null && $account->currency_id !== $deposit->currency_id) {
                        $fail('The destination account currency must match the deposit currency.');
                    }
                },
            ],
            'closed_at' => ['required', 'date_format:d-m-Y'],
            'interest' => ['required', 'numeric', 'decimal:0,2', 'gte:0'],
            'category_id' => [
                Rule::requiredIf(fn () => bccomp((string) $this->input('interest', '0'), '0', 2) === 1),
                Rule::prohibitedIf(fn () => bccomp((string) $this->input('interest', '0'), '0', 2) !== 1),
                'nullable',
                'integer',
                $incomeCategoryRule,
            ],
        ];
    }
}
