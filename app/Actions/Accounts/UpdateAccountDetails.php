<?php

namespace App\Actions\Accounts;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Models\Account;
use App\Telemetry\Event;
use Illuminate\Support\Facades\DB;

final class UpdateAccountDetails
{
    /**
     * @param array{
     *   name: string,
     *   bank: Bank,
     *   type: AccountType,
     *   opening_balance: numeric-string|float|int,
     * } $validated
     */
    public function handle(Account $account, array $validated): void
    {
        DB::transaction(function () use ($account, $validated): void {
            $locked = Account::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldOpening = (string) $locked->opening_balance;
            $newOpening = (string) $validated['opening_balance'];

            $delta = bcsub($newOpening, $oldOpening, 2);

            $locked->name = $validated['name'];
            $locked->bank = $validated['bank'];
            $locked->type = $validated['type'];
            $locked->opening_balance = $newOpening;
            $locked->current_balance = bcadd((string) $locked->current_balance, $delta, 2);
            $locked->save();

            Event::record('account_updated', ['account_id' => $locked->id]);
        });
    }
}
