<?php

declare(strict_types=1);

namespace App\Console\Commands\Accounts;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('accounts:set-balance {account : The account ID} {balance : Target current_balance} {--dry-run : Show change without saving}')]
#[Description('Set account current_balance directly without creating adjustment transactions.')]
final class SetAccountBalance extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $accountId = (int) $this->argument('account');
        $balanceArg = $this->argument('balance');

        if (! is_string($balanceArg) && ! is_numeric($balanceArg)) {
            $this->error('Balance must be a numeric value.');

            return self::FAILURE;
        }

        $account = Account::query()->whereKey($accountId)->first();

        if ($account === null) {
            $this->error('Account not found.');

            return self::FAILURE;
        }

        $current = TransactionDedupe::amountToDecimalString((string) $account->current_balance);
        $newBalance = TransactionDedupe::amountToDecimalString((string) $balanceArg);

        if (bccomp($current, $newBalance, 2) === 0) {
            $this->info("Account #{$account->id} ({$account->name}) balance already matches {$current}.");

            return self::SUCCESS;
        }

        $sumRaw = Transaction::query()->where('account_id', $account->id)->sum('amount');
        $sum = TransactionDedupe::amountToDecimalString((string) $sumRaw);
        $expected = bcadd(TransactionDedupe::amountToDecimalString((string) $account->opening_balance), $sum, 2);

        if (bccomp($expected, $newBalance, 2) !== 0) {
            $this->warn("Account #{$account->id} ({$account->name}): new balance {$newBalance} differs from transaction sum {$expected}.");
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->line("Account #{$account->id} ({$account->name}): {$current} → {$newBalance}{$suffix}");

        if ($dryRun) {
            return self::SUCCESS;
        }

        $account->current_balance = $newBalance;
        $account->save();

        $this->info("Account #{$account->id} balance updated to {$newBalance}.");

        return self::SUCCESS;
    }
}
