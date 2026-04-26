<?php

namespace App\Http\Controllers;

use App\Actions\Transfers\CreateTransfer;
use App\Events\TransferCreated;
use App\Http\Requests\Transfers\StoreTransferRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransferController extends Controller
{
    public function create(Request $request): Response
    {
        $accounts = Account::query()
            ->withTrashed()
            ->whereBelongsTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id', 'deleted_at'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'currency_id' => $account->currency_id,
                'is_deleted' => $account->trashed(),
            ]);

        return Inertia::render('transfers/Create', [
            'accounts' => $accounts,
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

        return to_route('transactions.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'transfers.toast.created',
        ]);
    }
}
