<?php

namespace App\Http\Requests\Transactions;

use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class StoreTransactionRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'decimal:0,2', Rule::notIn([0])],
            'description' => [
                'required',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $dateInput = $this->input('date');
                    $amountInput = $this->input('amount');
                    $accountIdInput = $this->input('account_id');

                    if (! is_string($dateInput) || $dateInput === '' || $amountInput === null || ! is_numeric($amountInput) || ! is_numeric($accountIdInput)) {
                        return;
                    }

                    try {
                        $date = CarbonImmutable::createFromFormat('d-m-Y', $dateInput);
                    } catch (\Throwable) {
                        return;
                    }

                    $amount = TransactionDedupe::amountToDecimalString((string) $amountInput);
                    $normalizedDescription = TransactionDedupe::normalizeDescription($value);
                    $dedupeHash = TransactionDedupe::dedupeHash($date->toDateString(), $amount, $normalizedDescription);

                    $exists = DB::table('transactions')
                        ->where('account_id', (int) $accountIdInput)
                        ->where('dedupe_hash', $dedupeHash)
                        ->exists();

                    if ($exists) {
                        $fail('A similar transaction already exists for this account.');
                    }
                },
            ],
            'subject' => ['nullable', 'string', 'max:255'],
        ];
    }
}
