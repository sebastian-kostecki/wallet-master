<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\ShowDashboard;
use App\Http\Resources\Transactions\TransactionResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, ShowDashboard $showDashboard): Response
    {
        $showDashboard->handle($request->user());

        return Inertia::render('Dashboard', [
            'period' => [
                'year' => $showDashboard->getYear(),
                'month' => $showDashboard->getMonth(),
            ],
            'balances' => $showDashboard->getBalances(),
            'cashflow' => $showDashboard->getCashflow(),
            'budget_summary' => $showDashboard->getBudgetSummary(),
            'currency' => $showDashboard->getCurrency(),
            'recent_transactions' => TransactionResource::collection(
                $showDashboard->getRecentTransactions(),
            )->resolve(),
        ]);
    }
}
