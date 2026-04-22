<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustAccountBalanceRequest;
use App\Models\Account;
use App\Models\AccountBalanceAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AccountBalanceController extends Controller
{
    public function update(AdjustAccountBalanceRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('adjustBalance', $account);

        $validated = $request->validated();

        DB::transaction(function () use ($account, $request, $validated): void {
            $locked = Account::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldBalance = $locked->current_balance;
            $newBalance = $validated['new_balance'];

            $locked->current_balance = $newBalance;
            $locked->save();

            AccountBalanceAdjustment::query()->create([
                'account_id' => $locked->id,
                'user_id' => $request->user()->id,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
            ]);
        });

        return to_route('accounts.edit', $account);
    }
}
