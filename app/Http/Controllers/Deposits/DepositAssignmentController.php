<?php

declare(strict_types=1);

namespace App\Http\Controllers\Deposits;

use App\Actions\Deposits\AssignTransactionToDeposit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deposits\AssignDepositTransactionRequest;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;

final class DepositAssignmentController extends Controller
{
    public function store(
        AssignDepositTransactionRequest $request,
        Transaction $transaction,
        AssignTransactionToDeposit $assignTransactionToDeposit,
    ): RedirectResponse {
        $this->authorize('update', $transaction);

        $assignTransactionToDeposit->handle($request->user(), $transaction, $request->validated());

        return to_route('transactions.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'deposits.toast.assigned',
        ]);
    }
}
