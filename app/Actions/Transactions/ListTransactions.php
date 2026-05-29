<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Http\Requests\Transactions\TransactionIndexRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListTransactions
{
    private LengthAwarePaginator $transactions;

    private Collection $accounts;

    private string|int|float $totalIncome;

    private string|int|float $totalExpense;

    private array $filters;

    public function handle(TransactionIndexRequest $request): void
    {
        $this->handleFilters($request);
        $this->handleAccounts($request);
        $this->handleTransactions($request);
    }

    private function handleFilters(TransactionIndexRequest $request): void
    {
        $request->setSorts(['date', 'amount']);
        $this->filters = [
            ...$request->getFilters(),
            ...$request->getData(),
        ];
    }

    private function handleTransactions(TransactionIndexRequest $request): void
    {
        $query = $this->baseQuery($request->user());
        $this->applyFilters($query, $request->getFilters());
        $this->applySort($query, $request->getSorts());

        /** @var object{total_income: string|int|float, total_expense: string|int|float}|null $summary */
        $summary = (clone $query)
            ->selectRaw('COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) as total_income')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) as total_expense')
            ->first();
        $this->totalIncome = $summary?->total_income ?? 0;
        $this->totalExpense = $summary?->total_expense ?? 0;

        $this->transactions = $query->paginate(perPage: $request->getPerPage(), page: $request->getPage());
    }

    private function handleAccounts(TransactionIndexRequest $request): void
    {
        $this->accounts = Account::query()
            ->whereBelongsTo($request->user())
            ->with('currency')
            ->orderBy('name')
            ->get();
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
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['account_id'] !== null) {
            $query->where('account_id', $filters['account_id']);
        }

        if ($filters['from'] !== null) {
            $query->whereDate(
                'date',
                '>=',
                CarbonImmutable::createFromFormat('d-m-Y', $filters['from'])->toDateString(),
            );
        }

        if ($filters['to'] !== null) {
            $query->whereDate(
                'date',
                '<=',
                CarbonImmutable::createFromFormat('d-m-Y', $filters['to'])->toDateString(),
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

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getTransactionPaginator(): LengthAwarePaginator
    {
        return $this->transactions;
    }

    public function getAccounts(): Collection
    {
        return $this->accounts;
    }

    public function getSummary(): array
    {
        return [
            'total_income' => $this->totalIncome,
            'total_expense' => $this->totalExpense,
        ];
    }
}
