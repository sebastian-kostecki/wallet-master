<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateTransactionRequest extends FormRequest
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
        $userId = $this->user()?->id;

        $accountExistsRule = Rule::exists('accounts', 'id')->whereNull('deleted_at');

        $accountExistsRule->where('user_id', $userId ?? 0);

        return [
            'account_id' => [
                'required',
                'integer',
                $accountExistsRule,
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

                $transaction = $this->route('transaction');

                if (! $transaction instanceof Transaction) {
                    return;
                }

                $date = CarbonImmutable::createFromFormat('d-m-Y', (string) $this->input('date'));
                $amount = TransactionDedupe::amountToDecimalString((string) $this->input('amount'));
                $normalizedDescription = TransactionDedupe::normalizeDescription((string) $this->input('description'));
                $dedupeHash = TransactionDedupe::dedupeHash($date->toDateString(), $amount, $normalizedDescription);

                $exists = DB::table('transactions')
                    ->where('account_id', (int) $this->input('account_id'))
                    ->where('dedupe_hash', $dedupeHash)
                    ->where('id', '!=', $transaction->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('description', 'A similar transaction already exists for this account.');
                }
            },
        ];
    }
}
