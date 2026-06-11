<?php

declare(strict_types=1);

namespace App\Console\Commands\Transactions;

use App\Actions\Transactions\DeleteTransaction;
use App\Models\Account;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDuplicateFinder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('transactions:detect-duplicates {--delete-duplicates : Delete duplicate rows, keeping the oldest (MIN id) per group} {--dry-run : With --delete-duplicates, list deletions without executing them}')]
#[Description('Detect globally identical transactions by date, amount, and normalized description.')]
final class DetectDuplicates extends Command
{
    public function handle(
        TransactionDuplicateFinder $finder,
        DeleteTransaction $deleteTransaction,
    ): int {
        $groups = $finder->findGroups();

        if ($groups === []) {
            $this->info('No logical duplicate transaction groups found.');

            return self::SUCCESS;
        }

        $redundantCount = array_sum(array_map(
            fn (array $group): int => count($group['duplicate_ids']),
            $groups,
        ));

        $this->warn('Found '.count($groups)." duplicate group(s), {$redundantCount} redundant row(s).");
        $this->newLine();

        $deleteDuplicates = (bool) $this->option('delete-duplicates');
        $dryRun = (bool) $this->option('dry-run');
        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($groups as $index => $group) {
            $groupNumber = $index + 1;
            $key = $group['key'];

            $this->line("Group #{$groupNumber} (".count($group['transactions']).' rows) — '.$key['date'].' | '.$key['amount'].' | '.$key['description']);

            foreach ($group['transactions'] as $transaction) {
                $role = $transaction['id'] === $group['keep_id'] ? 'keep' : 'delete';
                $importLabel = $transaction['import_id'] !== null ? (string) $transaction['import_id'] : '—';

                $this->line(sprintf(
                    '  %s #%d  account=#%d  user=#%d  import=%s',
                    str_pad($role.':', 7),
                    $transaction['id'],
                    $transaction['account_id'],
                    $transaction['user_id'],
                    $importLabel,
                ));

                if ($role !== 'delete' || ! $deleteDuplicates) {
                    continue;
                }

                if ($transaction['transfer_id'] !== null) {
                    $skippedCount++;
                    $this->warn("  Skipping transaction #{$transaction['id']} (transfer-linked).");

                    continue;
                }

                $account = Account::query()->withTrashed()->find($transaction['account_id']);

                if ($account === null) {
                    $skippedCount++;
                    $this->warn("  Skipping transaction #{$transaction['id']} (account #{$transaction['account_id']} not found).");

                    continue;
                }

                if ($account->trashed()) {
                    $skippedCount++;
                    $this->warn("  Skipping transaction #{$transaction['id']} (account #{$account->id} is soft-deleted).");

                    continue;
                }

                if ($dryRun) {
                    $this->line("  [dry-run] Would delete transaction #{$transaction['id']} (group keep #{$group['keep_id']}).");

                    continue;
                }

                $model = Transaction::query()->find($transaction['id']);

                if ($model === null) {
                    $skippedCount++;
                    $this->warn("  Skipping transaction #{$transaction['id']} (already deleted).");

                    continue;
                }

                try {
                    $deleteTransaction->handle($model);
                    $deletedCount++;
                    $this->info("  Deleted transaction #{$transaction['id']}.");
                } catch (Throwable $exception) {
                    $skippedCount++;
                    $this->warn("  Skipping transaction #{$transaction['id']} ({$exception->getMessage()}).");
                }
            }

            $this->newLine();
        }

        if ($deleteDuplicates) {
            $prefix = $dryRun ? '[dry-run] ' : '';
            $this->line("{$prefix}Deleted {$deletedCount} row(s), skipped {$skippedCount} row(s).");
        } else {
            $this->line('Use --delete-duplicates --dry-run to preview deletions.');
        }

        return self::FAILURE;
    }
}
