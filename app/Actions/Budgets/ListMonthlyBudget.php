<?php

declare(strict_types=1);

namespace App\Actions\Budgets;

use App\Actions\Categories\EnsureUserCategories;
use App\Actions\Categories\ListCategories;
use App\Enums\CategoryType;
use App\Http\Requests\Budgets\MonthlyBudgetRequest;
use App\Models\Category;
use App\Models\CategoryAnnualEstimate;
use App\Models\CategoryMonthlyEstimate;
use App\Models\Pocket;
use App\Models\User;
use App\Support\Budgets\BudgetCurrency;
use App\Support\Budgets\BudgetPeriod;
use App\Support\Budgets\BudgetProgress;
use App\Support\Budgets\BudgetSummary;
use App\Support\Budgets\BudgetTransactionQuery;
use App\Support\Budgets\CategoryPlanAmount;
use App\Support\Pockets\PocketBalance;
use App\Support\Pockets\PocketPlanningProjection;
use App\Support\Pockets\PocketTransactionMetrics;
use App\Support\Transactions\TransactionDedupe;
use Illuminate\Database\Eloquent\Collection;

final class ListMonthlyBudget
{
    private int $year;

    private int $month;

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var list<array<string, mixed>> */
    private array $pocketRows = [];

    /** @var Collection<int, Category> */
    private Collection $categories;

    /** @var array{monthly_sum: string, annual_sum: string} */
    private array $allocationHint = [
        'monthly_sum' => '0.00',
        'annual_sum' => '0.00',
    ];

    /** @var array<string, mixed> */
    private array $summary = [];

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

            if ($plan !== null) {
                $monthlyPlansSum = bcadd($monthlyPlansSum, $plan, 2);
            }

            if ($annual?->amount !== null) {
                $annualPlansSum = bcadd($annualPlansSum, (string) $annual->amount, 2);
            }

            $this->rows[] = [
                'category_id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'type' => $category->type->value,
                'type_label_key' => $category->type->labelKey(),
                'is_system' => $category->is_system,
                'monthly_plan' => $plan,
                'actual_income' => $actualIncome,
                'actual_expense' => $actualExpense,
                'actual' => $actualPrimary,
                'progress_percent' => BudgetProgress::percent($plan, $actualPrimary),
            ];
        }

        $this->pocketRows = $this->buildPocketRows($user, $period);

        $summary = BudgetSummary::fromRows($this->rows, planKey: 'monthly_plan');
        $this->summary = BudgetSummary::withPockets(
            $summary,
            $this->pocketRows,
            BudgetCurrency::pln()['code'],
        );
        $this->allocationHint = [
            'monthly_sum' => $monthlyPlansSum,
            'annual_sum' => $annualPlansSum,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildPocketRows(User $user, BudgetPeriod $period): array
    {
        $pockets = Pocket::query()
            ->forUser($user->id)
            ->active()
            ->ordered()
            ->with('currency')
            ->get();

        if ($pockets->isEmpty()) {
            return [];
        }

        $rows = [];

        foreach ($pockets as $pocket) {
            $metrics = PocketTransactionMetrics::forMonth($user, $pocket, $period);
            $cumulative = PocketBalance::cumulative($user, $pocket);
            $targetAmount = $pocket->target_amount !== null ? (string) $pocket->target_amount : null;

            $rows[] = [
                'pocket_id' => $pocket->id,
                'name' => $pocket->name,
                'icon' => $pocket->icon,
                'color' => $pocket->color,
                'monthly_plan' => PocketPlanningProjection::monthlyPlanForBudget($pocket, $cumulative['balance']),
                'saved' => $metrics['saved'],
                'released' => $metrics['released'],
                'balance' => $metrics['balance'],
                'balance_cumulative' => $cumulative['balance'],
                'target_amount' => $targetAmount,
                'progress_percent' => PocketBalance::progressPercent($pocket, $cumulative['balance']),
                'currency' => [
                    'code' => $pocket->currency->code,
                    'symbol' => $pocket->currency->symbol,
                    'precision' => $pocket->currency->precision,
                ],
            ];
        }

        return $rows;
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
     * @return list<array<string, mixed>>
     */
    public function getPocketRows(): array
    {
        return $this->pocketRows;
    }

    /**
     * @return array{monthly_sum: string, annual_sum: string}
     */
    public function getAllocationHint(): array
    {
        return $this->allocationHint;
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
