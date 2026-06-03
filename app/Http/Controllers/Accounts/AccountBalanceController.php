<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounts;

use App\Actions\Accounts\AdjustAccountBalance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\AdjustAccountBalanceRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;

final class AccountBalanceController extends Controller
{
    public function update(AdjustAccountBalanceRequest $request, Account $account, AdjustAccountBalance $adjustBalance): RedirectResponse
    {
        $this->authorize('adjustBalance', $account);

        $validated = $request->validated();

        $adjustBalance->handle($request->user(), $account, (string) $validated['new_balance']);

        return to_route('accounts.edit', $account);
    }
}
