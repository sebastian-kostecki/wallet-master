<?php

namespace App\Http\Controllers\Transfers;

use App\Actions\Goals\ListGoals;
use App\Actions\Transfers\CreateTransfer;
use App\Actions\Transfers\UnlinkTransfer;
use App\Events\TransferCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\StoreTransferRequest;
use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Goals\GoalResource;
use App\Models\Account;
use App\Support\Transactions\TransactionsIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransferController extends Controller
{
    public function create(Request $request, ListGoals $listGoals): Response
    {
        $accounts = AccountResource::collection(
            Account::query()
                ->withTrashed()
                ->whereBelongsTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name', 'currency_id', 'bank', 'type', 'deleted_at'])
        )->resolve();

        $listGoals->handle($request->user());
        $goals = $listGoals->getGoals();

        return Inertia::render('transfers/Create', [
            'accounts' => $accounts,
            'goals' => GoalResource::collection($goals)->resolve(),
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
