<?php

namespace App\Http\Controllers\Transaction;

use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\StoreTransaction;
use App\Actions\Transactions\UpdateTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\TransactionIndexRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\Transaction\TransactionEditResource;
use App\Http\Resources\Transaction\TransactionResource;
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

    public function index(TransactionIndexRequest $request): Response
    {
        $request->setSorts(['date', 'amount']);

        $accounts = Account::queryForUser($request->user())->get();
        $transactionsQuery = Transaction::queryForUser(
            $request->user(),
            $request->getFilters(),
            $request->getSorts(),
        );

        $query = clone $transactionsQuery;
        $totalIncome = $query->selectRaw('COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) as total_income')->value('total_income');
        $totalExpense = $query->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) as total_expense')->value('total_expense');

        return Inertia::render('transactions/Index', [
            'filters' => [
                ...$request->getFilters(),
                ...$request->getData(),
            ],
            'accounts' => AccountResource::collection($accounts)->resolve(),
            'transactions' => TransactionResource::collection($transactionsQuery->paginate($request->getPerPage())),
            'summary' => [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $accounts = AccountResource::collection(
            Account::query()
                ->whereBelongsTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name', 'currency_id', 'bank'])
        )->resolve();

        return Inertia::render('transactions/Create', [
            'accounts' => $accounts,
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
            Account::query()
                ->whereBelongsTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name', 'currency_id', 'bank'])
        )->resolve();

        return Inertia::render('transactions/Edit', [
            'transaction' => (new TransactionEditResource($transaction))->resolve(),
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
