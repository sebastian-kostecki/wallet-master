<?php

namespace App\Http\Controllers\Transfers;

use App\Actions\Categories\ListCategories;
use App\Actions\Goals\ListGoals;
use App\Actions\Transfers\CreateTransfer;
use App\Actions\Transfers\UnlinkTransfer;
use App\Events\TransferCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\StoreTransferRequest;
use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Categories\CategoryResource;
use App\Http\Resources\Goals\GoalResource;
use App\Models\Account;
use App\Models\Category;
use App\Support\Transactions\TransactionsIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransferController extends Controller
{
    public function create(Request $request, ListCategories $listCategories, ListGoals $listGoals): Response
    {
        $accounts = AccountResource::collection(
            Account::query()
                ->withTrashed()
                ->whereBelongsTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name', 'currency_id', 'bank', 'type', 'deleted_at'])
        )->resolve();

        $listCategories->handle($request->user());
        $categories = $listCategories->getCategories();

        $listGoals->handle($request->user());
        $goals = $listGoals->getGoals();

        $defaultCategoryId = $categories->first(
            fn (Category $c): bool => $c->is_system && $c->name === 'Oszczędności',
        )?->id ?? $categories->first()?->id;

        return Inertia::render('transfers/Create', [
            'accounts' => $accounts,
            'categories' => CategoryResource::collection($categories)->resolve(),
            'goals' => GoalResource::collection($goals)->resolve(),
            'default_category_id' => $defaultCategoryId,
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
