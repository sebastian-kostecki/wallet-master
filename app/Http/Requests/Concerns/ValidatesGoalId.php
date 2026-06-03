<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesGoalId
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function optionalGoalIdRules(): array
    {
        return [
            'goal_id' => [
                'nullable',
                'integer',
                Rule::exists('goals', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
            ],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function goalIdRulesForTransfer(
        string $fromAccountField = 'from_account_id',
        string $toAccountField = 'to_account_id',
    ): array {
        if ($this->transferInvolvesSavingsAccount($fromAccountField, $toAccountField)) {
            return [
                'goal_id' => [
                    'required',
                    'integer',
                    Rule::exists('goals', 'id')->where(
                        fn ($query) => $query->where('user_id', $this->user()->id),
                    ),
                ],
            ];
        }

        return [
            'goal_id' => ['prohibited'],
        ];
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
