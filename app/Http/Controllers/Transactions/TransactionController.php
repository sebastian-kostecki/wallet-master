<?php

namespace App\Http\Controllers\Transactions;

use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\ListTransactions;
use App\Actions\Transactions\StoreTransaction;
use App\Actions\Transactions\UpdateTransaction;
use App\Data\Transactions\TransactionIndexFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\TransactionIndexRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Transactions\TransactionEditResource;
use App\Http\Resources\Transactions\TransactionResource;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        $request->setSorts(['date', 'amount']);

        $accounts = Account::queryForUser($request->user())->get();
        $result = $listTransactions->handle(
            $request->user(),
            TransactionIndexFilters::fromArray($request->getFilters()),
            $request->getSorts(),
            $request->getPerPage(),
            $request->getPage(),
        );

        return Inertia::render('transactions/Index', [
            'filters' => [
                ...$request->getFilters(),
                ...$request->getData(),
            ],
            'accounts' => AccountResource::collection($accounts)->resolve(),
            'transactions' => $this->transactionsPaginatorPayload($result->paginator),
            'summary' => [
                'total_income' => $result->totalIncome,
                'total_expense' => $result->totalExpense,
            ],
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, Transaction>  $paginator
     * @return array<string, mixed>
     */
    private function transactionsPaginatorPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = TransactionResource::collection($paginator->items())->resolve();

        return $payload;
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
