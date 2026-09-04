<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\Bank;
use App\Enums\CategoryType;
use App\Enums\DepositMovementKind;
use App\Enums\DepositStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Deposit;
use App\Models\DepositMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Transactions\TransactionDedupe;
use App\Telemetry\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CloseDeposit
{
    /**
     * @param  array{
     *   destination_account_id: int,
     *   closed_at: string,
     *   interest: numeric-string|float|int,
     *   category_id?: int,
     * }  $validated
     *
     * @throws Throwable
     */
    public function handle(User $user, Deposit $deposit, array $validated): Deposit
    {
        if ($deposit->user_id !== $user->id) {
            abort(403);
        }

        $deposit = DB::transaction(function () use ($user, $deposit, $validated): Deposit {
            $deposit = Deposit::query()
                ->whereKey($deposit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($deposit->status !== DepositStatus::Active) {
                throw ValidationException::withMessages([
                    'deposit' => 'Only active deposits can be closed.',
                ]);
            }

            $destination = Account::query()
                ->whereKey($validated['destination_account_id'])
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($destination === null || $destination->trashed()) {
                abort(403);
            }

            if ($destination->bank === Bank::Cash) {
                throw ValidationException::withMessages([
                    'destination_account_id' => 'Cash wallets cannot be used as a deposit destination account.',
                ]);
            }

            if ($destination->currency_id !== $deposit->currency_id) {
                throw ValidationException::withMessages([
                    'destination_account_id' => 'The destination account currency must match the deposit currency.',
                ]);
            }

            if ($deposit->source_account_id !== $destination->id) {
                Account::query()
                    ->whereKey($deposit->source_account_id)
                    ->lockForUpdate()
                    ->first();
            }

            $closedAt = CarbonImmutable::createFromFormat('d-m-Y', $validated['closed_at'])->toDateString();
            $principal = (string) $deposit->principal;
            $interest = TransactionDedupe::amountToDecimalString($validated['interest']);

            $principalDescription = 'Term deposit principal';
            $normalizedPrincipalDescription = TransactionDedupe::normalizeDescription($principalDescription);
            $principalDedupeHash = TransactionDedupe::manualDedupeHash(
                $closedAt,
                $principal,
                $normalizedPrincipalDescription,
            );

            $principalTransaction = Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $destination->id,
                'currency_id' => $destination->currency_id,
                'date' => $closedAt,
                'booked_at' => $closedAt,
                'amount' => $principal,
                'type' => TransactionType::Income,
                'description' => $principalDescription,
                'subject' => null,
                'normalized_description' => $normalizedPrincipalDescription,
                'dedupe_hash' => $principalDedupeHash,
                'category_id' => null,
            ]);

            DepositMovement::query()->create([
                'deposit_id' => $deposit->id,
                'transaction_id' => $principalTransaction->id,
                'kind' => DepositMovementKind::ClosePrincipal,
            ]);

            $destination->current_balance = bcadd((string) $destination->current_balance, $principal, 2);

            $hasInterestMovement = $deposit->movements()
                ->where('kind', DepositMovementKind::Interest)
                ->exists();

            if (bccomp($interest, '0', 2) === 1 && ! $hasInterestMovement) {
                if (! isset($validated['category_id'])) {
                    throw ValidationException::withMessages([
                        'category_id' => 'An income category is required when interest is greater than zero.',
                    ]);
                }

                $category = Category::query()
                    ->whereKey($validated['category_id'])
                    ->where('user_id', $user->id)
                    ->first();

                if ($category === null || $category->type !== CategoryType::Income) {
                    throw ValidationException::withMessages([
                        'category_id' => 'The selected category must be an income category belonging to you.',
                    ]);
                }

                $interestDescription = 'Term deposit interest';
                $normalizedInterestDescription = TransactionDedupe::normalizeDescription($interestDescription);
                $interestDedupeHash = TransactionDedupe::manualDedupeHash(
                    $closedAt,
                    $interest,
                    $normalizedInterestDescription,
                );

                $interestTransaction = Transaction::query()->create([
                    'user_id' => $user->id,
                    'account_id' => $destination->id,
                    'currency_id' => $destination->currency_id,
                    'date' => $closedAt,
                    'booked_at' => $closedAt,
                    'amount' => $interest,
                    'type' => TransactionType::Income,
                    'description' => $interestDescription,
                    'subject' => null,
                    'normalized_description' => $normalizedInterestDescription,
                    'dedupe_hash' => $interestDedupeHash,
                    'category_id' => $category->id,
                ]);

                DepositMovement::query()->create([
                    'deposit_id' => $deposit->id,
                    'transaction_id' => $interestTransaction->id,
                    'kind' => DepositMovementKind::Interest,
                ]);

                $destination->current_balance = bcadd((string) $destination->current_balance, $interest, 2);
            }

            $destination->save();

            $deposit->status = DepositStatus::Closed;
            $deposit->closed_at = Carbon::createFromFormat('d-m-Y', $validated['closed_at']);
            $deposit->save();

            return $deposit;
        }, attempts: 5);

        Event::record('deposit_closed', [
            'deposit_id' => $deposit->id,
        ], $user->id);

        return $deposit;
    }
}
