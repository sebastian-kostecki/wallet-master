<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountBalanceAdjustment;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use App\Telemetry\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdjustAccountBalance
{
    /**
     * @param  numeric-string  $newBalance
     */
    public function handle(User $user, Account $account, string $newBalance): void
    {
        DB::transaction(function () use ($user, $account, $newBalance): void {
            $locked = Account::query()
                ->whereKey($account->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldBalance = TransactionDedupe::amountToDecimalString((string) $locked->current_balance);
            $normalizedNew = TransactionDedupe::amountToDecimalString($newBalance);

            $delta = bcsub($normalizedNew, $oldBalance, 2);

            if (bccomp($delta, '0', 2) === 0) {
                return;
            }

            $today = CarbonImmutable::today()->toDateString();
            $description = 'Korekta salda';
            $normalizedDescription = TransactionDedupe::normalizeDescription($description);
            $dedupeHash = md5(((string) Str::uuid()).'|balance-adjustment', true);

            Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $locked->id,
                'currency_id' => $locked->currency_id,
                'date' => $today,
                'booked_at' => $today,
                'amount' => $delta,
                'type' => TransactionType::Adjustment,
                'description' => $description,
                'subject' => null,
                'normalized_description' => $normalizedDescription,
                'dedupe_hash' => $dedupeHash,
            ]);

            $locked->current_balance = $normalizedNew;
            $locked->save();

            AccountBalanceAdjustment::query()->create([
                'account_id' => $locked->id,
                'user_id' => $user->id,
                'old_balance' => $oldBalance,
                'new_balance' => $normalizedNew,
            ]);

            Event::record('account_balance_adjusted', [
                'account_id' => $locked->id,
                'old_balance' => $oldBalance,
                'new_balance' => $normalizedNew,
            ], $user->id);
        });
    }
}
