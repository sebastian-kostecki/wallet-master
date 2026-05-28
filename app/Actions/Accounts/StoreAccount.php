<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\User;

final class StoreAccount
{
    /**
     * @param array{
     *   currency_id: int,
     *   name: string,
     *   bank: Bank,
     *   type: AccountType,
     *   opening_balance: numeric-string|float|int,
     * } $validated
     */
    public function handle(User $user, array $validated): Account
    {
        $openingBalance = (string) $validated['opening_balance'];

        return Account::query()->create([
            'user_id' => $user->id,
            'currency_id' => $validated['currency_id'],
            'name' => $validated['name'],
            'bank' => $validated['bank'],
            'type' => $validated['type'],
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
        ]);
    }
}
