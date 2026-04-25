<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\StoreTransaction;
use App\Actions\Transactions\UpdateTransaction;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\TransactionIndexRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
        $validated = $request->validated();

        $accountId = isset($validated['account_id']) ? (int) $validated['account_id'] : null;
        $from = isset($validated['from']) ? CarbonImmutable::createFromFormat('d-m-Y', $validated['from'])->toDateString() : null;
        $to = isset($validated['to']) ? CarbonImmutable::createFromFormat('d-m-Y', $validated['to'])->toDateString() : null;
        $sort = isset($validated['sort']) ? (string) $validated['sort'] : 'date';
        $direction = isset($validated['direction']) ? (string) $validated['direction'] : 'desc';

        $baseQuery = Transaction::query()
            ->whereBelongsTo($request->user())
            ->when($accountId !== null, fn (Builder $q) => $q->where('account_id', $accountId))
            ->when(
                $from !== null && $to !== null,
                fn (Builder $q) => $q
                    ->whereDate('date', '>=', $from)
                    ->whereDate('date', '<=', $to)
            )
            ->when(
                $from !== null && $to === null,
                fn (Builder $q) => $q->whereDate('date', '>=', $from)
            )
            ->when(
                $to !== null && $from === null,
                fn (Builder $q) => $q->whereDate('date', '<=', $to)
            );

        $transactions = (clone $baseQuery)
            ->with([
                'account:id,name',
                'currency:id,code,symbol,precision',
            ])
            ->when($sort === 'amount', fn (Builder $q) => $q->orderBy('amount', $direction)->orderBy('date', 'desc')->orderBy('id', 'desc'))
            ->when($sort !== 'amount', fn (Builder $q) => $q->orderBy('date', $direction)->orderBy('id', 'desc'))
            ->paginate(15, [
                'id',
                'account_id',
                'currency_id',
                'date',
                'amount',
                'type',
                'description',
                'subject',
            ])
            ->withQueryString();

        $summary = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS total_income')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) AS total_expense')
            ->first();

        $accounts = Account::query()
            ->whereBelongsTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('transactions/Index', [
            'accounts' => $accounts,
            'filters' => [
                'account_id' => $accountId,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'transactions' => $transactions,
            'summary' => [
                'total_income' => number_format((float) ($summary?->total_income ?? 0), 2, '.', ''),
                'total_expense' => number_format((float) ($summary?->total_expense ?? 0), 2, '.', ''),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $accounts = Account::query()
            ->whereBelongsTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id']);

        return Inertia::render('transactions/Create', [
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreTransactionRequest $request, StoreTransaction $store): RedirectResponse
    {
        $transaction = $store->handle($request->user(), $request->validated());

        return to_route('transactions.edit', $transaction);
    }

    public function edit(Transaction $transaction, Request $request): Response
    {
        $transaction->loadMissing(['account:id,name', 'currency:id,code,symbol,precision']);

        $accounts = Account::query()
            ->whereBelongsTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id']);

        return Inertia::render('transactions/Edit', [
            'transaction' => $transaction->only(['id', 'account_id', 'date', 'amount', 'description', 'subject'])
                + [
                    'account' => $transaction->account?->only(['id', 'name']),
                    'currency' => $transaction->currency?->only(['id', 'code', 'symbol', 'precision']),
                ],
            'accounts' => $accounts,
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction, UpdateTransaction $update): RedirectResponse
    {
        $update->handle($transaction, $request->validated());

        return to_route('transactions.edit', $transaction);
    }

    public function destroy(Transaction $transaction, DeleteTransaction $delete): RedirectResponse
    {
        $delete->handle($transaction);

        return to_route('transactions.index');
    }
}
