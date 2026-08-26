<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Accounts\AccountResource;
use App\Models\Account;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $accounts = AccountResource::collection(
            Account::query()
                ->whereBelongsTo(auth()->user())
                ->with(['currency:id,code,symbol,precision'])
                ->orderBy('name')
                ->get(['id', 'currency_id', 'name', 'current_balance', 'bank', 'type'])
        )->resolve();

        return Inertia::render('Dashboard', [
            'accounts' => $accounts,
        ]);
    }
}
