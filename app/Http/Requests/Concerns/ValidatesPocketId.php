<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Pocket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesPocketId
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function optionalPocketIdRules(): array
    {
        return [
            'pocket_id' => [
                'nullable',
                'integer',
                Rule::exists('pockets', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_numeric($value) || ! is_numeric($this->input('account_id'))) {
                        return;
                    }

                    if (! $this->pocketCurrencyMatchesAccounts((int) $value, [(int) $this->input('account_id')])) {
                        $fail('Pocket currency must match the account currency.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function pocketIdRulesForTransfer(
        string $fromAccountField = 'from_account_id',
        string $toAccountField = 'to_account_id',
    ): array {
        if ($this->transferInvolvesSavingsAccount($fromAccountField, $toAccountField)) {
            return [
                'pocket_id' => [
                    'required',
                    'integer',
                    Rule::exists('pockets', 'id')->where(
                        fn ($query) => $query->where('user_id', $this->user()->id),
                    ),
                    function (string $attribute, mixed $value, \Closure $fail) use ($fromAccountField, $toAccountField): void {
                        $fromId = $this->input($fromAccountField);
                        $toId = $this->input($toAccountField);

                        if (! is_numeric($value) || ! is_numeric($fromId) || ! is_numeric($toId)) {
                            return;
                        }

                        if (! $this->pocketCurrencyMatchesAccounts((int) $value, [(int) $fromId, (int) $toId])) {
                            $fail('Pocket currency must match the savings account currency.');
                        }
                    },
                ],
            ];
        }

        return [
            'pocket_id' => ['prohibited'],
        ];
    }

    protected function validateOptionalPocketCurrency(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pocketId = $this->input('pocket_id');
            $accountId = $this->input('account_id');

            if (! is_numeric($pocketId) || ! is_numeric($accountId)) {
                return;
            }

            if (! $this->pocketCurrencyMatchesAccounts((int) $pocketId, [(int) $accountId])) {
                $validator->errors()->add('pocket_id', 'Pocket currency must match the account currency.');
            }
        });
    }

    protected function pocketCurrencyMatchesAccounts(int $pocketId, array $accountIds): bool
    {
        $pocket = Pocket::query()
            ->where('user_id', $this->user()->id)
            ->find($pocketId);

        if ($pocket === null) {
            return true;
        }

        $currencyIds = Account::query()
            ->where('user_id', $this->user()->id)
            ->whereIn('id', $accountIds)
            ->whereNull('deleted_at')
            ->pluck('currency_id')
            ->unique()
            ->values();

        if ($currencyIds->count() !== 1) {
            return false;
        }

        return (int) $currencyIds->first() === (int) $pocket->currency_id;
    }

    protected function transferInvolvesSavingsAccount(
        string $fromAccountField = 'from_account_id',
        string $toAccountField = 'to_account_id',
    ): bool {
        $fromId = $this->input($fromAccountField);
        $toId = $this->input($toAccountField);

        if (! is_numeric($fromId) || ! is_numeric($toId)) {
            return false;
        }

        $accounts = Account::query()
            ->where('user_id', $this->user()->id)
            ->whereIn('id', [(int) $fromId, (int) $toId])
            ->whereNull('deleted_at')
            ->get(['id', 'type']);

        foreach ($accounts as $account) {
            if ($account->type === AccountType::Savings) {
                return true;
            }
        }

        return false;
    }
}
