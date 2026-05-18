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

        $allTime = isset($validated['all_time']) ? (bool) $validated['all_time'] : false;
        $accountId = isset($validated['account_id']) ? (int) $validated['account_id'] : null;
        $importId = isset($validated['import_id']) ? (int) $validated['import_id'] : null;
        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;

        $hasFrom = isset($validated['from']);
        $hasTo = isset($validated['to']);

        $fromInput = $hasFrom ? (string) $validated['from'] : null; // d-m-Y
        $toInput = $hasTo ? (string) $validated['to'] : null; // d-m-Y

        if (! $hasFrom && ! $hasTo && ! $allTime) {
            $today = CarbonImmutable::today();
            $fromInput = $today->startOfMonth()->format('d-m-Y');
            $toInput = $today->format('d-m-Y');
        }

        $from = $fromInput !== null ? CarbonImmutable::createFromFormat('d-m-Y', $fromInput)->toDateString() : null;
        $to = $toInput !== null ? CarbonImmutable::createFromFormat('d-m-Y', $toInput)->toDateString() : null;

        $sort = isset($validated['sort']) ? (string) $validated['sort'] : 'date';
        $direction = isset($validated['direction']) ? (string) $validated['direction'] : 'desc';

        $baseQuery = Transaction::query()
            ->whereBelongsTo($request->user())
            ->when($accountId !== null, fn (Builder $q) => $q->where('account_id', $accountId))
            ->when($importId !== null, fn (Builder $q) => $q->where('import_id', $importId))
            ->when(
                $from !== null && $to !== null,
                fn (Builder $q) => $q
                    ->whereDate('booked_at', '>=', $from)
                    ->whereDate('booked_at', '<=', $to)
            )
            ->when(
                $from !== null && $to === null,
                fn (Builder $q) => $q->whereDate('booked_at', '>=', $from)
            )
            ->when(
                $to !== null && $from === null,
                fn (Builder $q) => $q->whereDate('booked_at', '<=', $to)
            );

        $transactions = (clone $baseQuery)
            ->with([
                'account:id,name,bank,type,currency_id',
                'account.currency:id,symbol',
                'currency:id,code,symbol,precision',
            ])
            ->when(
                $sort === 'amount',
                fn (Builder $q) => $q->orderBy('amount', $direction)->orderBy('booked_at', 'desc')->orderBy('id', 'desc')
            )
            ->when(
                $sort !== 'amount',
                fn (Builder $q) => $q->orderBy('booked_at', $direction)->orderBy('date', 'desc')->orderBy('id', 'desc')
            )
            ->paginate($perPage, [
                'id',
                'account_id',
                'currency_id',
                'date',
                'booked_at',
                'amount',
                'type',
                'description',
                'subject',
                'transfer_id',
            ])
            ->withQueryString()
            ->through(fn (Transaction $transaction) => (new TransactionResource($transaction))->resolve());

        $transactions->appends($request->query());

        $summary = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS total_income')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) AS total_expense')
            ->first();

        $accounts = AccountResource::collection(
            Account::query()
                ->whereBelongsTo($request->user())
                ->orderBy('name')
                ->with(['currency:id,symbol'])
                ->get(['id', 'name', 'currency_id', 'bank'])
        )->resolve();

        return Inertia::render('transactions/Index', [
            'accounts' => $accounts,
            'filters' => [
                'all_time' => $allTime,
                'account_id' => $accountId,
                'import_id' => $importId,
                'from' => $fromInput,
                'to' => $toInput,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'transactions' => $transactions,
            'summary' => [
                'total_income' => number_format((float) data_get($summary, 'total_income', 0), 2, '.', ''),
                'total_expense' => number_format((float) data_get($summary, 'total_expense', 0), 2, '.', ''),
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
