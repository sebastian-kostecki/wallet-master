<?php

namespace App\Http\Requests;

use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\DB;

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
        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->whereNull('deleted_at'),
            ],
            'date' => ['required', 'date_format:d-m-Y'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['required', 'string', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->fails()) {
                    return;
                }

                $date = CarbonImmutable::createFromFormat('d-m-Y', (string) $this->input('date'));
                $amount = TransactionDedupe::amountToDecimalString((string) $this->input('amount'));
                $normalizedDescription = TransactionDedupe::normalizeDescription((string) $this->input('description'));
                $dedupeHash = TransactionDedupe::dedupeHash($date->toDateString(), $amount, $normalizedDescription);

                $exists = DB::table('transactions')
                    ->where('account_id', (int) $this->input('account_id'))
                    ->where('dedupe_hash', $dedupeHash)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('description', 'A similar transaction already exists for this account.');
                }
            },
        ];
    }
}

