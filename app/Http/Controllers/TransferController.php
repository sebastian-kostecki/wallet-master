<?php

namespace App\Http\Controllers;

use App\Actions\Transfers\CreateTransfer;
use App\Events\TransferCreated;
use App\Http\Requests\Transfers\StoreTransferRequest;
use Illuminate\Http\RedirectResponse;

final class TransferController extends Controller
{
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

        return to_route('transactions.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'transfers.toast.created',
        ]);
    }
}
