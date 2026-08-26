<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\PocketMovement;
use App\Models\Account;
use App\Models\Pocket;
use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesPocketId
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prohibitedPocketRules(): array
    {
        return [
            'pocket_id' => ['prohibited'],
            'pocket_movement' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function pocketIdRulesForTransfer(
        string $fromAccountField = 'from_account_id',
        string $toAccountField = 'to_account_id',
    ): array {
        return [
            'pocket_id' => [
                'nullable',
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
                        $fail('Pocket currency must match the transfer currency.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function optionalPocketIdRulesForTransferLeg(): array
    {
        return [
            'pocket_id' => [
                'nullable',
                'integer',
                Rule::exists('pockets', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id),
                ),
            ],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function pocketMovementRules(): array
    {
        return [
            'pocket_movement' => [
                Rule::requiredIf(fn () => $this->filled('pocket_id')),
                Rule::prohibitedIf(fn () => ! $this->filled('pocket_id')),
                Rule::enum(PocketMovement::class),
            ],
        ];
    }

    protected function validateLinkedTransferPocketCurrency(Validator $validator, Transaction $transaction): void
    {
        $validator->after(function (Validator $validator) use ($transaction): void {
            $pocketId = $this->input('pocket_id');

            if (! is_numeric($pocketId)) {
                return;
            }

            $sibling = Transaction::query()
                ->where('transfer_id', $transaction->transfer_id)
                ->where('id', '!=', $transaction->id)
                ->first();

            if ($sibling === null) {
                return;
            }

            $accountIds = [(int) $transaction->account_id, (int) $sibling->account_id];

            if (! $this->pocketCurrencyMatchesAccounts((int) $pocketId, $accountIds)) {
                $validator->errors()->add('pocket_id', 'Pocket currency must match the transfer currency.');
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
}
