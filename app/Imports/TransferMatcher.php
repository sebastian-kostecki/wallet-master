<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\TransactionType;
use App\Enums\TransferMatchStatus;
use App\Models\Import;
use App\Models\Transaction;
use App\Support\Transactions\TransactionDedupe;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TransferMatcher
{
    /** @var list<int> */
    private array $linkedTransactionIds = [];

    public function matchAfterImport(Import $import): TransferMatcherResult
    {
        $result = new TransferMatcherResult;

        $imported = Transaction::query()
            ->where('import_id', $import->id)
            ->orderBy('id')
            ->get();

        foreach ($imported as $transaction) {
            if ($this->shouldSkipImportedTransaction($transaction)) {
                continue;
            }

            $candidates = $this->findCandidates($transaction);

            if ($candidates->isEmpty()) {
                continue;
            }

            if ($candidates->count() > 1) {
                $result->ambiguousSkipped++;

                Log::channel('telemetry')->info('transfer_match_skipped_ambiguous', [
                    'event' => 'transfer_match_skipped_ambiguous',
                    'import_id' => $import->id,
                    'user_id' => $import->user_id,
                    'transaction_id' => $transaction->id,
                    'candidate_count' => $candidates->count(),
                ]);
            }

            $best = $this->pickBestCandidate($transaction, $candidates);
            $hasToken = $this->hasTransferToken($transaction) || $this->hasTransferToken($best);

            if ($candidates->count() === 1 && $hasToken) {
                $transferId = $this->autoLink($transaction, $best);
                $result->autoLinked++;

                Log::channel('telemetry')->info('transfer_auto_linked', [
                    'event' => 'transfer_auto_linked',
                    'import_id' => $import->id,
                    'user_id' => $import->user_id,
                    'transfer_id' => $transferId,
                    'transaction_ids' => [$transaction->id, $best->id],
                ]);
            } else {
                $this->manualLink($transaction, $best);
                $result->manualLinked++;
            }

            $this->linkedTransactionIds[] = $transaction->id;
            $this->linkedTransactionIds[] = $best->id;
        }

        return $result;
    }

    private function shouldSkipImportedTransaction(Transaction $transaction): bool
    {
        if (in_array($transaction->id, $this->linkedTransactionIds, true)) {
            return true;
        }

        if ($transaction->transfer_match_status !== TransferMatchStatus::None) {
            return true;
        }

        if ($transaction->transfer_id !== null && $transaction->transfer_id !== '') {
            return true;
        }

        return false;
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function findCandidates(Transaction $transaction): Collection
    {
        $candidates = Transaction::query()
            ->where('user_id', $transaction->user_id)
            ->where('account_id', '!=', $transaction->account_id)
            ->where('currency_id', $transaction->currency_id)
            ->whereNull('transfer_id')
            ->where('transfer_match_status', '!=', TransferMatchStatus::Rejected)
            ->whereNull('transfer_candidate_for_id')
            ->when(
                $this->linkedTransactionIds !== [],
                fn ($query) => $query->whereNotIn('id', $this->linkedTransactionIds),
            )
            ->orderBy('id')
            ->get();

        return $candidates->filter(
            fn (Transaction $candidate): bool => $this->isOppositeAmountPair($transaction, $candidate)
                && $this->isWithinDateWindow($transaction, $candidate),
        )->values();
    }

    private function isOppositeAmountPair(Transaction $left, Transaction $right): bool
    {
        $leftAmount = TransactionDedupe::amountToDecimalString((string) $left->amount);
        $rightAmount = TransactionDedupe::amountToDecimalString((string) $right->amount);

        if (bccomp($leftAmount, '0', 2) === 0 || bccomp($rightAmount, '0', 2) === 0) {
            return false;
        }

        if (bccomp(bcmul($leftAmount, $rightAmount, 4), '0', 4) >= 0) {
            return false;
        }

        $leftAbs = ltrim($leftAmount, '-');
        $rightAbs = ltrim($rightAmount, '-');

        return bccomp($leftAbs, $rightAbs, 2) === 0;
    }

    private function isWithinDateWindow(Transaction $left, Transaction $right): bool
    {
        $leftDate = CarbonImmutable::parse($left->date->toDateString());
        $rightDate = CarbonImmutable::parse($right->date->toDateString());

        return $leftDate->diffInDays($rightDate) <= 3;
    }

    /**
     * @param  Collection<int, Transaction>  $candidates
     */
    private function pickBestCandidate(Transaction $transaction, Collection $candidates): Transaction
    {
        return $candidates
            ->sortBy(fn (Transaction $candidate): array => [
                $this->dateDeltaDays($transaction, $candidate),
                $candidate->id,
            ])
            ->firstOrFail();
    }

    private function dateDeltaDays(Transaction $left, Transaction $right): int
    {
        $leftDate = CarbonImmutable::parse($left->date->toDateString());
        $rightDate = CarbonImmutable::parse($right->date->toDateString());

        return (int) $leftDate->diffInDays($rightDate);
    }

    private function hasTransferToken(Transaction $transaction): bool
    {
        $haystack = mb_strtolower(trim(implode(' ', array_filter([
            $transaction->description,
            $transaction->raw_statement_description,
        ]))));

        if ($haystack === '') {
            return false;
        }

        foreach ($this->transferTokens() as $token) {
            if (str_contains($haystack, mb_strtolower($token))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function transferTokens(): array
    {
        /** @var list<string> $tokens */
        $tokens = config('imports.transfer_tokens', []);

        return $tokens;
    }

    private function autoLink(Transaction $left, Transaction $right): string
    {
        $transferId = (string) Str::uuid();

        foreach ([$left, $right] as $transaction) {
            $transaction->update([
                'transfer_id' => $transferId,
                'type' => TransactionType::Transfer,
                'transfer_match_status' => TransferMatchStatus::Auto,
                'transfer_candidate_for_id' => null,
            ]);
        }

        return $transferId;
    }

    private function manualLink(Transaction $left, Transaction $right): void
    {
        $left->update([
            'transfer_match_status' => TransferMatchStatus::Manual,
            'transfer_candidate_for_id' => $right->id,
        ]);

        $right->update([
            'transfer_match_status' => TransferMatchStatus::Manual,
            'transfer_candidate_for_id' => $left->id,
        ]);
    }
}
