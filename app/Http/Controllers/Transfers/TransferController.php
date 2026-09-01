<?php

namespace App\Http\Controllers\Transfers;

use App\Actions\Transfers\CreateTransfer;
use App\Actions\Transfers\UnlinkTransfer;
use App\Events\TransferCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\StoreTransferRequest;
use App\Support\Transactions\TransactionsIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TransferController extends Controller
{
    public function create(): RedirectResponse
    {
        return to_route('transactions.index', [
            ...TransactionsIndexQuery::params(),
            'transfer' => 1,
        ]);
    }

    public function store(StoreTransferRequest $request, CreateTransfer $createTransfer): RedirectResponse
    {
        $result = $createTransfer->handle($request->user(), $request->validated());

        event(new TransferCreated(
            userId: $request->user()->id,
            transferId: $result['transfer_id'],
            fromAccountId: $result['from_account_id'],
            toAccountId: $result['to_account_id'],
            amount: $result['amount'],
            date: $result['date'],
        ));

        return TransactionsIndexQuery::redirect($request)->with('toast', [
            'type' => 'success',
            'message_key' => 'transfers.toast.created',
        ]);
    }

    public function unlink(Request $request, string $transferId, UnlinkTransfer $unlinkTransfer): RedirectResponse
    {
        $unlinkTransfer->handle($request->user(), $transferId);

        return back()->with('toast', [
            'type' => 'success',
            'message_key' => 'transfers.toast.unlinked',
        ]);
    }
}
