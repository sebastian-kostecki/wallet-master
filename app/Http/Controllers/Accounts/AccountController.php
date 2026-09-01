<?php

namespace App\Http\Controllers\Accounts;

use App\Actions\Accounts\StoreAccount;
use App\Actions\Accounts\UpdateAccountDetails;
use App\Actions\Deposits\ListDeposits;
use App\Data\Accounts\AccountFormOptions;
use App\Enums\Bank;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Categories\CategoryResource;
use App\Http\Resources\Deposits\DepositResource;
use App\Models\Account;
use App\Models\Currency;
use App\Telemetry\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Account::class, 'account');
    }

    public function index(Request $request, AccountFormOptions $options, ListDeposits $listDeposits): Response
    {
        $currencies = Currency::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol', 'precision']);

        if ($request->query('kind') === 'deposits') {
            $listDeposits->handle($request->user());

            return Inertia::render('accounts/Index', [
                'accounts' => [],
                'deposits' => DepositResource::collection($listDeposits->getDeposits())->resolve(),
                'bank_accounts' => AccountResource::collection($listDeposits->getBankAccounts())->resolve(),
                'income_categories' => CategoryResource::collection($listDeposits->getIncomeCategories())->resolve(),
                'currencies' => $currencies,
                ...$options->toArray(),
            ]);
        }

        $accounts = AccountResource::collection(
            Account::query()
                ->whereBelongsTo(auth()->user())
                ->with(['currency:id,code,symbol,precision'])
                ->orderBy('name')
                ->get(['id', 'currency_id', 'name', 'current_balance', 'opening_balance', 'bank', 'type'])
        )->resolve();

        return Inertia::render('accounts/Index', [
            'accounts' => $accounts,
            'deposits' => [],
            'bank_accounts' => [],
            'income_categories' => [],
            'currencies' => $currencies,
            ...$options->toArray(),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $kind = $request->query('kind');

        return to_route(
            'accounts.index',
            is_string($kind) && in_array($kind, ['accounts', 'wallets', 'deposits'], true)
                ? ['kind' => $kind]
                : [],
        );
    }

    public function store(StoreAccountRequest $request, StoreAccount $storeAccount): RedirectResponse
    {
        $account = $storeAccount->handle($request->user(), $request->validated());

        return to_route('accounts.index', $this->accountsIndexQuery($account))->with('toast', [
            'type' => 'success',
            'message_key' => 'accounts.toast.created',
        ]);
    }

    public function edit(Account $account): RedirectResponse
    {
        return to_route('accounts.index', $this->accountsIndexQuery($account));
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccountDetails $updater): RedirectResponse
    {
        $updater->handle($account, $request->validated());
        $account->refresh();

        return to_route('accounts.index', $this->accountsIndexQuery($account))->with('toast', [
            'type' => 'success',
            'message_key' => 'accounts.toast.updated',
        ]);
    }

    public function destroy(Account $account): RedirectResponse
    {
        $transactionCount = $account->transactions()->count();

        Event::record('account_deleted', ['account_id' => $account->id]);
        Event::record('account_deleted_with_transactions', [
            'account_id' => $account->id,
            'transaction_count' => $transactionCount,
        ]);

        $account->delete();

        return to_route('accounts.index', $this->accountsIndexQuery($account));
    }

    /**
     * @return array{kind: string}
     */
    private function accountsIndexQuery(Account $account): array
    {
        return [
            'kind' => $account->bank === Bank::Cash ? 'wallets' : 'accounts',
        ];
    }
}
