<?php

namespace App\Http\Controllers\Transactions;

use App\Actions\Categories\ListCategories;
use App\Actions\Deposits\ListDeposits;
use App\Actions\Pockets\ListPockets;
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
use App\Http\Resources\Deposits\DepositCandidateResource;
use App\Http\Resources\Imports\ImportFailedRowResource;
use App\Http\Resources\Pockets\PocketResource;
use App\Http\Resources\Transactions\TransactionResource;
use App\Http\Resources\Transfers\TransferCandidatePairResource;
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
        ListPockets $listPockets,
        ListDeposits $listDeposits,
    ): Response {
        $user = $request->user();

        $listTransactions->handle($request);
        $listCategories->handle($user);
        $listPockets->handle($user);
        $listDeposits->handle($user);

        TransactionsIndexQuery::remember($request);

        return Inertia::render('transactions/Index', [
            'filters' => $listTransactions->getFilters(),
            'accounts' => AccountResource::collection($listTransactions->getAccounts())->resolve(),
            'categories' => CategoryResource::collection($listCategories->getCategories())->resolve(),
            'pockets' => PocketResource::collection($listPockets->getPockets())->resolve(),
            'transactions' => TransactionResource::collection($listTransactions->getTransactionPaginator()),
            'summary' => $listTransactions->getSummary(),
            'unresolved_import_failed_rows' => ImportFailedRowResource::collection(
                $listTransactions->getUnresolvedImportFailedRows(),
            )->resolve(),
            'pending_transfer_candidates' => TransferCandidatePairResource::collection(
                $listTransactions->getPendingTransferCandidates(),
            )->resolve(),
            'pending_deposit_candidates' => DepositCandidateResource::collection(
                $listTransactions->getPendingDepositCandidates(),
            )->resolve(),
            'assignable_deposits' => $listDeposits->getDeposits()
                ->map(fn ($deposit) => [
                    'id' => $deposit->id,
                    'name' => $deposit->name,
                    'principal' => $deposit->principal,
                    'status' => $deposit->status->value,
                    'currency_id' => $deposit->currency_id,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return to_route('transactions.index', [
            ...TransactionsIndexQuery::params(),
            'new' => 1,
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

    public function edit(Request $request, Transaction $transaction): RedirectResponse
    {
        return TransactionsIndexQuery::redirect($request);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction, UpdateTransaction $update): RedirectResponse
    {
        $update->handle($transaction, $request->validated());

        return TransactionsIndexQuery::redirect($request)->with('toast', [
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
