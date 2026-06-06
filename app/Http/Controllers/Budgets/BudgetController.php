<?php

declare(strict_types=1);

namespace App\Http\Controllers\Budgets;

use App\Actions\Budgets\ListMonthlyBudget;
use App\Actions\Budgets\ListYearlyBudget;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budgets\MonthlyBudgetRequest;
use App\Http\Requests\Budgets\YearlyBudgetRequest;
use App\Telemetry\Event;
use Inertia\Inertia;
use Inertia\Response;

final class BudgetController extends Controller
{
    public function monthly(MonthlyBudgetRequest $request, ListMonthlyBudget $listMonthlyBudget): Response
    {
        $listMonthlyBudget->handle($request);

        Event::record('budget_view_monthly', [
            'year' => $listMonthlyBudget->getYear(),
            'month' => $listMonthlyBudget->getMonth(),
        ], $request->user()->id);

        return Inertia::render('budget/Monthly', [
            'year' => $listMonthlyBudget->getYear(),
            'month' => $listMonthlyBudget->getMonth(),
            'rows' => $listMonthlyBudget->getRows(),
            'pocket_rows' => $listMonthlyBudget->getPocketRows(),
            'allocation_hint' => $listMonthlyBudget->getAllocationHint(),
            'summary' => $listMonthlyBudget->getSummary(),
            'currency' => $listMonthlyBudget->getCurrency(),
        ]);
    }

    public function yearly(YearlyBudgetRequest $request, ListYearlyBudget $listYearlyBudget): Response
    {
        $listYearlyBudget->handle($request);

        Event::record('budget_view_yearly', [
            'year' => $listYearlyBudget->getYear(),
        ], $request->user()->id);

        return Inertia::render('budget/Yearly', [
            'year' => $listYearlyBudget->getYear(),
            'rows' => $listYearlyBudget->getRows(),
            'summary' => $listYearlyBudget->getSummary(),
            'currency' => $listYearlyBudget->getCurrency(),
        ]);
    }
}
