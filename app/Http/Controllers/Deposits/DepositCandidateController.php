<?php

declare(strict_types=1);

namespace App\Http\Controllers\Deposits;

use App\Actions\Deposits\ConfirmDepositCandidate;
use App\Actions\Deposits\RejectDepositCandidate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deposits\ConfirmDepositCandidateRequest;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DepositCandidateController extends Controller
{
    public function confirm(
        ConfirmDepositCandidateRequest $request,
        Transaction $transaction,
        ConfirmDepositCandidate $confirmDepositCandidate,
    ): RedirectResponse {
        $this->authorize('confirmDepositCandidate', $transaction);

        $confirmDepositCandidate->handle($request->user(), $transaction, $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'deposits.toast.candidate_confirmed',
        ]);
    }

    public function reject(
        Request $request,
        Transaction $transaction,
        RejectDepositCandidate $rejectDepositCandidate,
    ): RedirectResponse {
        $this->authorize('rejectDepositCandidate', $transaction);

        $rejectDepositCandidate->handle($request->user(), $transaction);

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'deposits.toast.candidate_rejected',
        ]);
    }
}
