<?php

namespace App\Http\Controllers\Transactions;

use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\ListTransactions;
use App\Actions\Transactions\StoreTransaction;
use App\Actions\Transactions\UpdateTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\TransactionIndexRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Transactions\TransactionEditResource;
use App\Http\Resources\Transactions\TransactionResource;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransactionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Transaction::class, 'transaction');
    }

    public function index(
        TransactionIndexRequest $request,
        ListTransactions $listTransactions,
    ): Response {
        $listTransactions->handle($request);

        return Inertia::render('transactions/Index', [
            'filters' => $listTransactions->getFilters(),
            'accounts' => AccountResource::collection($listTransactions->getAccounts())->resolve(),
            'transactions' => TransactionResource::collection($listTransactions->getTransactionPaginator()),
            'summary' => $listTransactions->getSummary(),
        ]);
    }

    public function create(Request $request): Response
    {
        $accounts = Account::queryForUser($request->user())->get();

        return Inertia::render('transactions/Create', [
            'accounts' => AccountResource::collection($accounts)->resolve(),
        ]);
    }

    public function store(StoreTransactionRequest $request, StoreTransaction $store): RedirectResponse
    {
        $store->handle($request->user(), $request->validated());

        return to_route('transactions.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'transactions.toast.created',
        ]);
    }

    public function edit(Transaction $transaction, Request $request): Response
    {
        $transaction->loadMissing(['account:id,name', 'currency:id,code,symbol,precision']);

        $accounts = AccountResource::collection(
            Account::queryForUser($request->user())
                ->get(['id', 'name', 'currency_id', 'bank', 'type', 'current_balance', 'opening_balance'])
        )->resolve();

        return Inertia::render('transactions/Edit', [
            'transaction' => new TransactionEditResource($transaction)->resolve(),
            'accounts' => $accounts,
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction, UpdateTransaction $update): RedirectResponse
    {
        $update->handle($transaction, $request->validated());

        return to_route('transactions.edit', $transaction)->with('toast', [
            'type' => 'success',
            'message_key' => 'transactions.toast.updated',
        ]);
    }

    public function destroy(Transaction $transaction, DeleteTransaction $delete): RedirectResponse
    {
        $delete->handle($transaction);

        return to_route('transactions.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'transactions.toast.deleted',
        ]);
    }
}
