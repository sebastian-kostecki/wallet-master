<?php

declare(strict_types=1);

namespace App\Actions\Transactions;

use App\Http\Requests\Transactions\TransactionIndexRequest;
use App\Models\Account;
use App\Models\ImportFailedRow;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListTransactions
{
    /** @var LengthAwarePaginator<int, Transaction> */
    private LengthAwarePaginator $transactions;

    /** @var Collection<int, Account> */
    private Collection $accounts;

    /** @var Collection<int, ImportFailedRow> */
    private Collection $unresolvedImportFailedRows;

    /** @var Collection<int, Transaction> */
    private Collection $pendingTransferCandidates;

    private string|int|float $totalIncome;

    private string|int|float $totalExpense;

    /**
     * @var array{
     *   account_id: ?int,
     *   from: ?string,
     *   to: ?string,
     *   sort: ?string,
     *   direction: string,
     *   per_page: int,
     *   page?: int,
     * }
     */
    private array $filters;

    public function handle(TransactionIndexRequest $request): void
    {
        $this->handleFilters($request);
        $this->handleAccounts($request);
        $this->handleUnresolvedImportFailedRows($request);
        $this->handlePendingTransferCandidates($request);
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
        $this->totalIncome = $summary !== null ? $summary->total_income : 0;
        $this->totalExpense = $summary !== null ? $summary->total_expense : 0;

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

    private function handleUnresolvedImportFailedRows(TransactionIndexRequest $request): void
    {
        $accountId = $request->getFilters()['account_id'] ?? null;

        $this->unresolvedImportFailedRows = ImportFailedRow::query()
            ->where('user_id', $request->user()->id)
            ->unresolved()
            ->when($accountId !== null, fn ($query) => $query->where('account_id', $accountId))
            ->orderByDesc('created_at')
            ->orderBy('row_number')
            ->get();
    }

    private function handlePendingTransferCandidates(TransactionIndexRequest $request): void
    {
        $accountId = $request->getFilters()['account_id'] ?? null;

        $this->pendingTransferCandidates = Transaction::query()
            ->with([
                'account.currency',
                'transferCandidate.account.currency',
                'currency',
            ])
            ->whereBelongsTo($request->user())
            ->pendingTransferCandidate()
            ->when($accountId !== null, function (Builder $query) use ($accountId): void {
                $query->where(function (Builder $scoped) use ($accountId): void {
                    $scoped
                        ->where('account_id', $accountId)
                        ->orWhereHas('transferCandidate', fn (Builder $candidate) => $candidate->where('account_id', $accountId));
                });
            })
            ->orderByDesc('date')
            ->orderBy('id')
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
     * @param  array{account_id: ?int, from: ?string, to: ?string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['account_id'] !== null) {
            $query->where('account_id', $filters['account_id']);
        }

        if ($filters['from'] !== null) {
            $fromDate = CarbonImmutable::createFromFormat('d-m-Y', $filters['from'])->toDateString();
            $query->whereRaw(
                'DATE('.$this->displayDateSqlExpression($query).') >= ?',
                [$fromDate],
            );
        }

        if ($filters['to'] !== null) {
            $toDate = CarbonImmutable::createFromFormat('d-m-Y', $filters['to'])->toDateString();
            $query->whereRaw(
                'DATE('.$this->displayDateSqlExpression($query).') <= ?',
                [$toDate],
            );
        }
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    private function displayDateSqlExpression(Builder $query): string
    {
        $grammar = $query->getGrammar();
        $bookedAt = $grammar->wrap($query->qualifyColumn('booked_at'));
        $date = $grammar->wrap($query->qualifyColumn('date'));

        return "COALESCE({$bookedAt}, {$date})";
    }

    /**
     * @param  Builder<Transaction>  $query
     * @param  array{sort_by: string|null, sort_direction: string}  $sorts
     */
    private function applySort(Builder $query, array $sorts): void
    {
        $sortBy = $sorts['sort_by'];
        $sortDirection = $sorts['sort_direction'];

        if ($sortBy === null) {
            return;
        }

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        if ($sortBy === 'date') {
            $query->orderByRaw($this->displayDateSqlExpression($query).' '.$sortDirection);
            $query->orderByDesc('date')->orderByDesc('id');

            return;
        }

        $query->orderBy($sortBy, $sortDirection);
        $query->orderByRaw($this->displayDateSqlExpression($query).' desc')->orderByDesc('id');
    }

    /**
     * @return array{
     *   account_id: ?int,
     *   from: ?string,
     *   to: ?string,
     *   sort: ?string,
     *   direction: string,
     *   per_page: int,
     *   page?: int,
     * }
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function getTransactionPaginator(): LengthAwarePaginator
    {
        return $this->transactions;
    }

    /**
     * @return Collection<int, Account>
     */
    public function getAccounts(): Collection
    {
        return $this->accounts;
    }

    /**
     * @return Collection<int, ImportFailedRow>
     */
    public function getUnresolvedImportFailedRows(): Collection
    {
        return $this->unresolvedImportFailedRows;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getPendingTransferCandidates(): Collection
    {
        return $this->pendingTransferCandidates;
    }

    /**
     * @return array{total_income: string|int|float, total_expense: string|int|float}
     */
    public function getSummary(): array
    {
        return [
            'total_income' => $this->totalIncome,
            'total_expense' => $this->totalExpense,
        ];
    }
}
