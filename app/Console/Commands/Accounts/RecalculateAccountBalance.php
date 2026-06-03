<?php

declare(strict_types=1);

namespace App\Console\Commands\Accounts;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('accounts:recalculate-balance {account? : The account ID to recalculate} {--all : Recalculate all accounts} {--dry-run : Compare without saving updates}')]
#[Description('Set each account current_balance to opening_balance + SUM(transactions.amount).')]
final class RecalculateAccountBalance extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $all = (bool) $this->option('all');
        $accountIdArg = $this->argument('account');

        if (($accountIdArg === null || $accountIdArg === '') && ! $all) {
            $this->error('Specify an account ID or use --all.');

            return self::FAILURE;
        }

        if ($accountIdArg !== null && $accountIdArg !== '' && $all) {
            $this->error('Use either an account ID argument or --all, not both.');

            return self::FAILURE;
        }

        /** @var Collection<int, Account> $accounts */
        $accounts = Account::query()
            ->when(
                $all,
                fn ($q) => $q->orderBy('id'),
                fn ($q) => $q->whereKey((int) $accountIdArg)
            )
            ->get();

        if ($accounts->isEmpty()) {
            $this->error('Account not found.');

            return self::FAILURE;
        }

        $mismatches = 0;

        foreach ($accounts as $account) {
            $sumRaw = Transaction::query()->where('account_id', $account->id)->sum('amount');
            $sum = TransactionDedupe::amountToDecimalString((string) $sumRaw);
            $expected = bcadd(TransactionDedupe::amountToDecimalString((string) $account->opening_balance), $sum, 2);
            $current = TransactionDedupe::amountToDecimalString((string) $account->current_balance);

            if (bccomp($expected, $current, 2) !== 0) {
                $mismatches++;
                $this->warn("Account #{$account->id} ({$account->name}): current={$current} expected={$expected}".($dryRun ? ' (dry-run)' : ''));

                if (! $dryRun) {
                    $account->current_balance = $expected;
                    $account->save();
                    $this->info("Account #{$account->id} balance updated to {$expected}.");
                }
            }
        }

        if ($mismatches === 0) {
            $this->info(($dryRun ? '[dry-run] ' : '').'All '.$accounts->count().' account(s) match the transaction sum.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line("Found {$mismatches} account(s) out of sync.".($dryRun ? ' No writes performed.' : ''));

        return self::SUCCESS;
    }
}
