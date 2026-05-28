<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Data\Transactions\TransactionIndexFilters;
use App\Data\Transactions\TransactionIndexResult;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ListTransactions
{
    /**
     * @param  array{sort_by: string|null, sort_direction: string}  $sorts
     */
    public function handle(
        User $user,
        TransactionIndexFilters $filters,
        array $sorts,
        int $perPage,
        int $page = 1,
    ): TransactionIndexResult {
        $query = $this->baseQuery($user);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $sorts);

        /** @var object{total_income: string|int|float, total_expense: string|int|float}|null $summary */
        $summary = (clone $query)
            ->selectRaw('COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) as total_income')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) as total_expense')
            ->first();
        $totalIncome = $summary?->total_income ?? 0;
        $totalExpense = $summary?->total_expense ?? 0;

        return new TransactionIndexResult(
            paginator: $query->paginate(perPage: $perPage, page: $page),
            totalIncome: $totalIncome ?? 0,
            totalExpense: $totalExpense ?? 0,
        );
    }

    /**
     * @return Builder<Transaction>
     */
    private function baseQuery(User $user): Builder
    {
        return Transaction::query()
            ->with('account.currency')
            ->whereBelongsTo($user);
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    private function applyFilters(Builder $query, TransactionIndexFilters $filters): void
    {
        if ($filters->accountId !== null) {
            $query->where('account_id', $filters->accountId);
        }

        if ($filters->from !== null) {
            $query->whereDate(
                'date',
                '>=',
                CarbonImmutable::createFromFormat('d-m-Y', $filters->from)->toDateString(),
            );
        }

        if ($filters->to !== null) {
            $query->whereDate(
                'date',
                '<=',
                CarbonImmutable::createFromFormat('d-m-Y', $filters->to)->toDateString(),
            );
        }
    }

    /**
     * @param  Builder<Transaction>  $query
     * @param  array{sort_by: string|null, sort_direction: string}  $sorts
     */
    private function applySort(Builder $query, array $sorts): void
    {
        $sortBy = $sorts['sort_by'] ?? null;
        $sortDirection = $sorts['sort_direction'] ?? 'desc';

        if ($sortBy === null) {
            return;
        }

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortBy, $sortDirection);
    }
}
