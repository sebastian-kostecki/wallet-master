<?php

namespace App\ViewModels\Accounts;

use App\Enums\AccountType;
use App\Enums\Bank;

final class AccountFormOptions
{
    /**
     * @return array{
     *   banks: array<int, array{value: string, label_key: string, icon_url: string|null}>,
     *   accountTypes: array<int, array{value: string, label_key: string, icon_name: string}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'banks' => array_map(
                fn (Bank $bank): array => [
                    'value' => $bank->value,
                    'label_key' => $bank->labelKey(),
                    'icon_url' => $bank === Bank::Cash ? null : asset("icons/banks/{$bank->value}.jpeg"),
                ],
                Bank::cases(),
            ),
            'accountTypes' => array_map(
                fn (AccountType $type): array => [
                    'value' => $type->value,
                    'label_key' => $type->labelKey(),
                    'icon_name' => match ($type) {
                        AccountType::Checking => 'wallet',
                        AccountType::Savings => 'piggyBank',
                    },
                ],
                AccountType::cases(),
            ),
        ];
    }
}
