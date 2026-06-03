<?php

declare(strict_types=1);

namespace App\Actions\Budgets;

use App\Actions\Categories\EnsureUserCategories;
use App\Actions\Categories\ListCategories;
use App\Enums\AccountType;
use App\Enums\CategoryType;
use App\Http\Requests\Budgets\MonthlyBudgetRequest;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\User;
use App\Support\Budgets\BudgetPeriod;
use App\Support\Budgets\BudgetTransactionQuery;
use App\Support\Budgets\CategoryPlanAmount;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Database\Eloquent\Collection;

final class ListMonthlyBudget
{
    private int $year;

    private int $month;

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var array{plan: ?string, actual: string, difference: ?string} */
    private array $transfersSummary = [
        'plan' => null,
        'actual' => '0.00',
        'difference' => null,
    ];

    /** @var Collection<int, Category> */
    private Collection $categories;

    /** @var array{monthly_sum: string, annual_sum: string} */
    private array $allocationHint = [
        'monthly_sum' => '0.00',
        'annual_sum' => '0.00',
    ];

    public function handle(MonthlyBudgetRequest $request): void
    {
        $user = $request->user();
        $this->year = $request->getYear();
        $this->month = $request->getMonth();

        app(EnsureUserCategories::class)->handle($user);

        $listCategories = app(ListCategories::class);
        $listCategories->handle($user);
        $this->categories = $listCategories->getCategories();

        $period = BudgetPeriod::forMonth($this->year, $this->month);

        $annualByCategory = CategoryAnnualEstimate::query()
            ->whereIn('category_id', $this->categories->pluck('id'))
            ->where('year', $this->year)
            ->get()
            ->keyBy('category_id');

        $monthlyByCategory = CategoryMonthlyEstimate::query()
            ->whereIn('category_id', $this->categories->pluck('id'))
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->get()
            ->keyBy('category_id');

        $actualsQuery = BudgetTransactionQuery::forUser($user);
        BudgetTransactionQuery::inPeriod($actualsQuery, $period);
        BudgetTransactionQuery::excludeTransfers($actualsQuery);

        $actuals = $actualsQuery
            ->select('category_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) as income')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as expense')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $monthlyPlansSum = '0.00';
        $annualPlansSum = '0.00';

        foreach ($this->categories as $category) {
            $annual = $annualByCategory->get($category->id);
            $monthly = $monthlyByCategory->get($category->id);
            $plan = CategoryPlanAmount::monthly($category, $this->year, $this->month, $annual, $monthly);
            $actual = $actuals->get($category->id);
            $actualIncome = $actual !== null
                ? TransactionDedupe::amountToDecimalString((string) $actual->income)
                : '0.00';
            $actualExpense = $actual !== null
                ? TransactionDedupe::amountToDecimalString((string) $actual->expense)
                : '0.00';
            $actualPrimary = $category->type === CategoryType::Income ? $actualIncome : $actualExpense;
            $difference = $plan !== null
                ? bcsub($actualPrimary, $plan, 2)
                : null;

            if ($plan !== null) {
                $monthlyPlansSum = bcadd($monthlyPlansSum, $plan, 2);
            }

            if ($annual?->amount !== null) {
                $annualPlansSum = bcadd($annualPlansSum, (string) $annual->amount, 2);
            }

            $this->rows[] = [
                'category_id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'type_label_key' => $category->type->labelKey(),
                'is_system' => $category->is_system,
                'monthly_plan' => $plan,
                'actual_income' => $actualIncome,
                'actual_expense' => $actualExpense,
                'actual' => $actualPrimary,
                'difference' => $difference,
            ];
        }

        $this->transfersSummary = $this->buildTransfersSummary($user, $period, $monthlyByCategory, $annualByCategory);
        $this->allocationHint = [
            'monthly_sum' => $monthlyPlansSum,
            'annual_sum' => $annualPlansSum,
        ];
    }

    /**
     * @param  Collection<int, CategoryMonthlyEstimate>  $monthlyByCategory
     * @param  Collection<int, CategoryAnnualEstimate>  $annualByCategory
     * @return array{plan: ?string, actual: string, difference: ?string}
     */
    private function buildTransfersSummary(
        User $user,
        BudgetPeriod $period,
        Collection $monthlyByCategory,
        Collection $annualByCategory,
    ): array {
        $savingsCategory = $this->categories->first(
            fn (Category $c): bool => $c->is_system && $c->name === 'Oszczędności',
        );

        $plan = null;
        if ($savingsCategory !== null) {
            $plan = CategoryPlanAmount::monthly(
                $savingsCategory,
                $this->year,
                $this->month,
                $annualByCategory->get($savingsCategory->id),
                $monthlyByCategory->get($savingsCategory->id),
            );
        }

        $transferQuery = BudgetTransactionQuery::forUser($user);
        BudgetTransactionQuery::inPeriod($transferQuery, $period);
        $transferQuery->whereNotNull('transfer_id');

        $actualSum = $transferQuery
            ->whereHas('account', fn ($q) => $q->where('type', AccountType::Savings))
            ->where('amount', '>', 0)
            ->sum('amount');

        $actual = number_format((float) $actualSum, 2, '.', '');

        $difference = $plan !== null ? bcsub($actual, $plan, 2) : null;

        return [
            'plan' => $plan,
            'actual' => $actual,
            'difference' => $difference,
        ];
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
     * @return list<array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @return array{plan: ?string, actual: string, difference: ?string}
     */
    public function getTransfersSummary(): array
    {
        return $this->transfersSummary;
    }

    /**
     * @return array{monthly_sum: string, annual_sum: string}
     */
    public function getAllocationHint(): array
    {
        return $this->allocationHint;
    }
}
