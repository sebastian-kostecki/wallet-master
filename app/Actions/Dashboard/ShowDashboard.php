<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Budgets\ListMonthlyBudget;
use App\Enums\AccountType;
use App\Enums\DepositStatus;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Budgets\BudgetCurrency;
use App\Support\Budgets\BudgetPeriod;
use App\Support\Budgets\BudgetTransactionQuery;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Database\Eloquent\Collection;

final class ShowDashboard
{
    private int $year;

    private int $month;

    /** @var array{total_pln: string, savings_pln: string, deposits_pln: string} */
    private array $balances = [
        'total_pln' => '0.00',
        'savings_pln' => '0.00',
        'deposits_pln' => '0.00',
    ];

    /** @var array{income: string, expense: string} */
    private array $cashflow = [
        'income' => '0.00',
        'expense' => '0.00',
    ];

    /** @var array<string, mixed> */
    private array $budgetSummary = [];

    /** @var Collection<int, Transaction> */
    private Collection $recentTransactions;

    public function __construct(private ListMonthlyBudget $listMonthlyBudget) {}

    public function handle(User $user): void
    {
        $this->recentTransactions = new Collection;
        $now = now();
        $this->year = (int) $now->year;
        $this->month = (int) $now->month;

        $this->handleBalances($user);
        $this->handleCashflow($user);
        $this->listMonthlyBudget->handleForPeriod($user, $this->year, $this->month);
        $this->budgetSummary = $this->listMonthlyBudget->getSummary();
        $this->handleRecent($user);
    }

    private function handleBalances(User $user): void
    {
        $accounts = Account::query()
            ->whereBelongsTo($user)
            ->with('currency')
            ->get();

        $total = '0.00';
        $savings = '0.00';

        foreach ($accounts as $account) {
            if ($account->currency?->code !== 'PLN') {
                continue;
            }

            $amount = TransactionDedupe::amountToDecimalString((string) $account->current_balance);
            $total = bcadd($total, $amount, 2);

            if ($account->type === AccountType::Savings) {
                $savings = bcadd($savings, $amount, 2);
            }
        }

        $plnId = (int) Currency::query()->where('code', 'PLN')->value('id');
        $depositsSum = Deposit::query()
            ->whereBelongsTo($user)
            ->where('status', DepositStatus::Active)
            ->where('currency_id', $plnId)
            ->sum('principal');

        $this->balances = [
            'total_pln' => $total,
            'savings_pln' => $savings,
            'deposits_pln' => TransactionDedupe::amountToDecimalString((string) $depositsSum),
        ];
    }

    private function handleCashflow(User $user): void
    {
        $query = BudgetTransactionQuery::forUser($user);
        BudgetTransactionQuery::inPeriod($query, BudgetPeriod::forMonth($this->year, $this->month));
        BudgetTransactionQuery::excludeTransfers($query);

        /** @var object{income: string|int|float, expense: string|int|float}|null $row */
        $row = $query
            ->selectRaw('COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) as income')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as expense')
            ->first();

        $this->cashflow = [
            'income' => TransactionDedupe::amountToDecimalString((string) ($row?->income ?? 0)),
            'expense' => TransactionDedupe::amountToDecimalString((string) ($row?->expense ?? 0)),
        ];
    }

    private function handleRecent(User $user): void
    {
        $query = Transaction::query()->whereBelongsTo($user);
        $grammar = $query->getGrammar();
        $bookedAt = $grammar->wrap($query->qualifyColumn('booked_at'));
        $date = $grammar->wrap($query->qualifyColumn('date'));

        $this->recentTransactions = $query
            ->with([
                'account.currency',
                'currency',
                'category',
                'pocket',
            ])
            ->orderByRaw("COALESCE({$bookedAt}, {$date}) desc")
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    /**
     * @return array{total_pln: string, savings_pln: string, deposits_pln: string}
     */
    public function getBalances(): array
    {
        return $this->balances;
    }

    /**
     * @return array{income: string, expense: string}
     */
    public function getCashflow(): array
    {
        return $this->cashflow;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBudgetSummary(): array
    {
        return $this->budgetSummary;
    }

    /**
     * @return array{code: string, symbol: string, precision: int}
     */
    public function getCurrency(): array
    {
        return BudgetCurrency::pln();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getRecentTransactions(): Collection
    {
        return $this->recentTransactions;
    }
}
