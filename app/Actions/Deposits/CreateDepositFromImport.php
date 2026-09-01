<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\Bank;
use App\Enums\DepositMatchStatus;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Imports\ParsedImportRow;
use App\Models\Account;
use App\Models\Deposit;
use App\Models\DepositMovement;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use App\Telemetry\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateDepositFromImport
{
    /**
     * @param  array{
     *   user_id: int,
     *   account_id: int,
     *   currency_id: int,
     *   import_id: int,
     *   date: string,
     *   booked_at: string,
     *   amount: numeric-string,
     *   type: string,
     *   description: string,
     *   subject: ?string,
     *   raw_statement_description: string,
     *   normalized_description: string,
     *   dedupe_hash: string,
     *   import_fingerprint: string,
     *   category_id: ?int,
     * }  $insertMetadata
     *
     * @throws Throwable
     */
    public function handle(
        User $user,
        Import $import,
        Account $account,
        ParsedImportRow $parsed,
        array $insertMetadata,
    ): Deposit {
        if ($import->user_id !== $user->id) {
            abort(403);
        }

        if ($import->account_id !== $account->id) {
            throw ValidationException::withMessages([
                'import' => 'The import account does not match the provided account.',
            ]);
        }

        $deposit = DB::transaction(function () use ($user, $account, $parsed, $insertMetadata): Deposit {
            $lockedAccount = Account::query()
                ->whereKey($account->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($lockedAccount === null || $lockedAccount->trashed()) {
                abort(403);
            }

            if ($lockedAccount->bank === Bank::Cash) {
                throw ValidationException::withMessages([
                    'account' => 'Cash wallets cannot be used as a deposit source account.',
                ]);
            }

            $amount = (string) $insertMetadata['amount'];

            if (bccomp($amount, '0', 2) !== -1) {
                throw ValidationException::withMessages([
                    'amount' => 'Opening a deposit from import requires a negative transaction amount.',
                ]);
            }

            $principal = bcmul($amount, '-1', 2);
            $openedAt = $parsed->date;

            $deposit = Deposit::query()->create([
                'user_id' => $user->id,
                'currency_id' => $lockedAccount->currency_id,
                'source_account_id' => $lockedAccount->id,
                'name' => null,
                'principal' => $principal,
                'opened_at' => $openedAt,
                'matures_at' => null,
                'status' => DepositStatus::Active,
                'closed_at' => null,
            ]);

            $transaction = Transaction::query()->create([
                ...$insertMetadata,
                'category_id' => null,
                'deposit_match_status' => DepositMatchStatus::Auto,
                'deposit_candidate_kind' => null,
            ]);

            DepositMovement::query()->create([
                'deposit_id' => $deposit->id,
                'transaction_id' => $transaction->id,
                'kind' => DepositMovementKind::Open,
            ]);

            $lockedAccount->current_balance = bcadd((string) $lockedAccount->current_balance, $amount, 2);
            $lockedAccount->save();

            return $deposit;
        }, attempts: 5);

        Event::record('deposit_created', [
            'deposit_id' => $deposit->id,
            'import_id' => $import->id,
        ], $user->id);

        return $deposit;
    }
}
