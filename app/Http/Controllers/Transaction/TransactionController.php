<?php

namespace App\Http\Controllers\Transaction;

use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\StoreTransaction;
use App\Actions\Transactions\UpdateTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\TransactionIndexRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

        $transactionsModels = (clone $baseQuery)
            ->with([
                'account:id,name,bank,type,currency_id',
                'account.currency:id,symbol',
                'currency:id,code,symbol,precision',
            ])
            ->when($sort === 'amount', fn (Builder $q) => $q->orderBy('amount', $direction)->orderBy('date', 'desc')->orderBy('id', 'desc'))
            ->when($sort !== 'amount', fn (Builder $q) => $q->orderBy('date', $direction)->orderBy('id', 'desc'))
            ->paginate($perPage, [
                'id',
                'account_id',
                'currency_id',
                'date',
                'amount',
                'type',
                'description',
                'subject',
                'transfer_id',
            ])
            ->withQueryString();

        $transactionItems = $transactionsModels->getCollection()->map(function (Transaction $transaction): array {
            $dateIso = (string) $transaction->date;

            $dateForHumans = CarbonImmutable::parse($dateIso)->locale(app()->getLocale());
            $dateRelative = is_string($dateForHumans)
                ? CarbonImmutable::parse($dateIso)->diffForHumans()
                : $dateForHumans->diffForHumans();

            $account = $transaction->account;
            $currency = $transaction->currency;

            return [
                'id' => $transaction->id,
                'account_id' => $transaction->account_id,
                'currency_id' => $transaction->currency_id,
                'date' => $dateIso,
                'date_relative' => $dateRelative,
                'amount' => $transaction->amount,
                'type' => $transaction->type,
                'description' => $transaction->description,
                'subject' => $transaction->subject,
                'transfer_id' => $transaction->transfer_id,
                'account' => $account !== null ? [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type?->value,
                    'type_label_key' => $account->type_label_key,
                    'bank_icon_url' => $account->bank_icon_url,
                    'currency' => $account->currency !== null ? [
                        'symbol' => $account->currency->symbol,
                    ] : null,
                ] : null,
                'currency' => $currency !== null ? [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'symbol' => $currency->symbol,
                    'precision' => $currency->precision,
                ] : null,
            ];
        });

        $transactions = new LengthAwarePaginator(
            items: $transactionItems,
            total: $transactionsModels->total(),
            perPage: $transactionsModels->perPage(),
            currentPage: $transactionsModels->currentPage(),
            options: [
                'path' => $transactionsModels->path(),
                'pageName' => $transactionsModels->getPageName(),
            ],
        );

        $transactions->appends($request->query());

        $summary = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS total_income')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) AS total_expense')
            ->first();

        $accounts = Account::query()
            ->whereBelongsTo($request->user())
            ->orderBy('name')
            ->with(['currency:id,symbol'])
            ->get(['id', 'name', 'currency_id', 'bank'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'currency_id' => $account->currency_id,
                'bank' => $account->bank?->value,
                'bank_icon_url' => $account->bank_icon_url,
                'currency' => $account->currency !== null ? [
                    'symbol' => $account->currency->symbol,
                ] : null,
            ]);

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
        $accounts = Account::query()
            ->whereBelongsTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id', 'bank'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'currency_id' => $account->currency_id,
                'bank' => $account->bank?->value,
                'bank_icon_url' => $account->bank_icon_url,
            ]);

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

        $accounts = Account::query()
            ->whereBelongsTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id', 'bank'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'currency_id' => $account->currency_id,
                'bank' => $account->bank?->value,
                'bank_icon_url' => $account->bank_icon_url,
            ]);

        return Inertia::render('transactions/Edit', [
            'transaction' => $transaction->only(['id', 'account_id', 'date', 'amount', 'description', 'subject', 'import_id', 'raw_statement_description', 'transfer_id'])
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
