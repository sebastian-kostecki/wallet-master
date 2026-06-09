<?php

declare(strict_types=1);

namespace App\Http\Controllers\Transfers;

use App\Actions\Transfers\ConfirmTransferCandidate;
use App\Actions\Transfers\RejectTransferCandidate;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TransferCandidateController extends Controller
{
    public function confirm(
        Request $request,
        Transaction $transaction,
        ConfirmTransferCandidate $confirmTransferCandidate,
    ): RedirectResponse {
        $this->authorize('confirmTransferCandidate', $transaction);

        $confirmTransferCandidate->handle($request->user(), $transaction);

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'transfers.toast.candidate_confirmed',
        ]);
    }

    public function reject(
        Request $request,
        Transaction $transaction,
        RejectTransferCandidate $rejectTransferCandidate,
    ): RedirectResponse {
        $this->authorize('rejectTransferCandidate', $transaction);

        $rejectTransferCandidate->handle($request->user(), $transaction);

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'transfers.toast.candidate_rejected',
        ]);
    }
}
