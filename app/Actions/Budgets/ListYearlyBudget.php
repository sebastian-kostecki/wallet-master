<?php

declare(strict_types=1);

namespace App\Actions\Budgets;

use App\Actions\Categories\EnsureUserCategories;
use App\Actions\Categories\ListCategories;
use App\Enums\CategoryType;
use App\Http\Requests\Budgets\YearlyBudgetRequest;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Support\Budgets\BudgetCurrency;
use App\Support\Budgets\BudgetForecast;
use App\Support\Budgets\BudgetPeriod;
use App\Support\Budgets\BudgetProgress;
use App\Support\Budgets\BudgetSummary;
use App\Support\Budgets\BudgetTransactionQuery;
use App\Support\Budgets\CategoryPlanAmount;
use App\Support\Budgets\YearlyMonthlyTemplate;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Database\Eloquent\Collection;

final class ListYearlyBudget
{
    private int $year;

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var Collection<int, Category> */
    private Collection $categories;

    /** @var array<string, mixed> */
    private array $summary = [];

    public function handle(YearlyBudgetRequest $request): void
    {
        $user = $request->user();
        $this->year = $request->getYear();

        app(EnsureUserCategories::class)->handle($user);

        $listCategories = app(ListCategories::class);
        $listCategories->handle($user);
        $this->categories = $listCategories->getCategories();

        $period = BudgetPeriod::forYear($this->year);
        $closedMonth = BudgetForecast::closedMonthForForecast($this->year);

        $annualByCategory = CategoryAnnualEstimate::query()
            ->whereIn('category_id', $this->categories->pluck('id'))
            ->where('year', $this->year)
            ->get()
            ->keyBy('category_id');

        $monthlyByCategoryAndMonth = CategoryMonthlyEstimate::query()
            ->whereIn('category_id', $this->categories->pluck('id'))
            ->where('year', $this->year)
            ->get()
            ->groupBy('category_id')
            ->map(fn (Collection $items) => $items->keyBy('month'));

        $actualsQuery = BudgetTransactionQuery::forUser($user);
        BudgetTransactionQuery::inPeriod($actualsQuery, $period);
        BudgetTransactionQuery::excludeTransfers($actualsQuery);

        /** @var Collection<int, object{category_id: int, income: string, expense: string}> $actuals */
        $actuals = $actualsQuery
            ->select('category_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) as income')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as expense')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        /** @var Collection<int, object{category_id: int, income: string, expense: string}> $forecastActuals */
        $forecastActuals = new Collection;

        if ($closedMonth > 0) {
            $forecastPeriod = BudgetPeriod::throughMonth($this->year, $closedMonth);
            $forecastActualsQuery = BudgetTransactionQuery::forUser($user);
            BudgetTransactionQuery::inPeriod($forecastActualsQuery, $forecastPeriod);
            BudgetTransactionQuery::excludeTransfers($forecastActualsQuery);

            $forecastActuals = $forecastActualsQuery
                ->select('category_id')
                ->selectRaw('COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) as income')
                ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as expense')
                ->groupBy('category_id')
                ->get()
                ->keyBy('category_id');
        }

        foreach ($this->categories as $category) {
            $annual = $annualByCategory->get($category->id);
            $actual = $actuals->get($category->id);
            $forecastActual = $forecastActuals->get($category->id);
            $plan = CategoryPlanAmount::annual($annual);
            $actualIncome = $actual !== null
                ? TransactionDedupe::amountToDecimalString((string) $actual->income)
                : '0.00';
            $actualExpense = $actual !== null
                ? TransactionDedupe::amountToDecimalString((string) $actual->expense)
                : '0.00';
            $actualPrimary = $category->type === CategoryType::Income ? $actualIncome : $actualExpense;
            $forecastActualIncome = $forecastActual !== null
                ? TransactionDedupe::amountToDecimalString((string) $forecastActual->income)
                : '0.00';
            $forecastActualExpense = $forecastActual !== null
                ? TransactionDedupe::amountToDecimalString((string) $forecastActual->expense)
                : '0.00';
            $forecastActualPrimary = $category->type === CategoryType::Income ? $forecastActualIncome : $forecastActualExpense;

            $monthlyEstimates = $monthlyByCategoryAndMonth->get($category->id, new Collection);
            $elapsedPlansSum = BudgetForecast::elapsedPlansSum(
                $category,
                $this->year,
                $closedMonth,
                $annual,
                $monthlyEstimates,
            );
            $forecast = BudgetForecast::forecast($forecastActualPrimary, $plan, $elapsedPlansSum);

            $this->rows[] = [
                'category_id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'type' => $category->type->value,
                'type_label_key' => $category->type->labelKey(),
                'annual_plan' => $plan,
                'monthly_template' => YearlyMonthlyTemplate::template(
                    $this->year,
                    $monthlyEstimates,
                    $annual,
                    $category,
                ),
                'actual_income' => $actualIncome,
                'actual_expense' => $actualExpense,
                'actual' => $actualPrimary,
                'forecast' => $forecast,
                'progress_percent' => BudgetProgress::percent($plan, $actualPrimary),
            ];
        }

        $this->summary = BudgetSummary::fromRows($this->rows, planKey: 'annual_plan', forecastKey: 'forecast');
    }

    public function getYear(): int
    {
        return $this->year;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return $this->summary;
    }

    /**
     * @return array{code: string, symbol: string, precision: int}
     */
    public function getCurrency(): array
    {
        return BudgetCurrency::pln();
    }
}
