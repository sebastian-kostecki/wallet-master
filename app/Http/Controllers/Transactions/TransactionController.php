<?php

namespace App\Http\Controllers\Transactions;

use App\Actions\Categories\ListCategories;
use App\Actions\Goals\ListGoals;
use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\ListTransactions;
use App\Actions\Transactions\StoreTransaction;
use App\Actions\Transactions\UpdateTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\TransactionIndexRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Categories\CategoryResource;
use App\Http\Resources\Goals\GoalResource;
use App\Http\Resources\Imports\ImportFailedRowResource;
use App\Http\Resources\Transactions\TransactionEditResource;
use App\Http\Resources\Transactions\TransactionResource;
use App\Http\Resources\Transfers\TransferCandidatePairResource;
use App\Models\Account;
use App\Models\Transaction;
use App\Support\Transactions\TransactionsIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class TransactionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Transaction::class, 'transaction');
    }

    public function index(
        TransactionIndexRequest $request,
        ListTransactions $listTransactions,
        ListCategories $listCategories,
    ): Response {
        $listTransactions->handle($request);
        $listCategories->handle($request->user());

        TransactionsIndexQuery::remember($request);

        return Inertia::render('transactions/Index', [
            'filters' => $listTransactions->getFilters(),
            'accounts' => AccountResource::collection($listTransactions->getAccounts())->resolve(),
            'categories' => CategoryResource::collection($listCategories->getCategories())->resolve(),
            'transactions' => TransactionResource::collection($listTransactions->getTransactionPaginator()),
            'summary' => $listTransactions->getSummary(),
            'unresolved_import_failed_rows' => ImportFailedRowResource::collection(
                $listTransactions->getUnresolvedImportFailedRows(),
            )->resolve(),
            'pending_transfer_candidates' => TransferCandidatePairResource::collection(
                $listTransactions->getPendingTransferCandidates(),
            )->resolve(),
        ]);
    }

    public function create(Request $request, ListCategories $listCategories, ListGoals $listGoals): Response
    {
        $accounts = Account::queryForUser($request->user())->get();
        $listCategories->handle($request->user());
        $listGoals->handle($request->user());

        return Inertia::render('transactions/Create', [
            'accounts' => AccountResource::collection($accounts)->resolve(),
            'categories' => CategoryResource::collection($listCategories->getCategories())->resolve(),
            'goals' => GoalResource::collection($listGoals->getGoals())->resolve(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreTransactionRequest $request, StoreTransaction $store): RedirectResponse
    {
        $store->handle($request->user(), $request->validated());

        return TransactionsIndexQuery::redirect($request)->with('toast', [
            'type' => 'success',
            'message_key' => 'transactions.toast.created',
        ]);
    }

    public function edit(Transaction $transaction, Request $request, ListCategories $listCategories, ListGoals $listGoals): Response
    {
        $transaction->loadMissing(['account:id,name', 'currency:id,code,symbol,precision']);
        $accounts = Account::queryForUser($request->user())->get();
        $listCategories->handle($request->user());
        $listGoals->handle($request->user());

        return Inertia::render('transactions/Edit', [
            'transaction' => new TransactionEditResource($transaction)->resolve(),
            'accounts' => AccountResource::collection($accounts)->resolve(),
            'categories' => CategoryResource::collection($listCategories->getCategories())->resolve(),
            'goals' => GoalResource::collection($listGoals->getGoals())->resolve(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction, UpdateTransaction $update): RedirectResponse
    {
        $update->handle($transaction, $request->validated());

        return to_route('transactions.edit', $transaction)->with('toast', [
            'type' => 'success',
            'message_key' => 'transactions.toast.updated',
        ]);
    }

    public function destroy(Request $request, Transaction $transaction, DeleteTransaction $delete): RedirectResponse
    {
        $delete->handle($transaction);

        return TransactionsIndexQuery::redirect($request)->with('toast', [
            'type' => 'success',
            'message_key' => 'transactions.toast.deleted',
        ]);
    }
}
