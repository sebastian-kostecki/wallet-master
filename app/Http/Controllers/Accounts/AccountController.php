<?php

namespace App\Http\Controllers\Accounts;

use App\Actions\Accounts\StoreAccount;
use App\Actions\Accounts\UpdateAccountDetails;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Http\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Models\Currency;
use App\Data\Accounts\AccountFormOptions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Account::class, 'account');
    }

    public function index(): Response
    {
        $accounts = AccountResource::collection(
            Account::query()
                ->whereBelongsTo(auth()->user())
                ->with(['currency:id,code,symbol,precision'])
                ->orderBy('name')
                ->get(['id', 'currency_id', 'name', 'current_balance', 'bank', 'type'])
        )->resolve();

        return Inertia::render('accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(AccountFormOptions $options): Response
    {
        $currencies = Currency::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol', 'precision']);

        return Inertia::render('accounts/Create', [
            'currencies' => $currencies,
            ...$options->toArray(),
        ]);
    }

    public function store(StoreAccountRequest $request, StoreAccount $storeAccount): RedirectResponse
    {
        $storeAccount->handle($request->user(), $request->validated());

        return to_route('accounts.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'accounts.toast.created',
        ]);
    }

    public function edit(Account $account, AccountFormOptions $options): Response
    {
        $account->loadMissing(['currency:id,code,symbol,precision']);

        return Inertia::render('accounts/Edit', [
            'account' => (new AccountResource($account))->resolve(),
            ...$options->toArray(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccountDetails $updater): RedirectResponse
    {
        $updater->handle($account, $request->validated());

        return to_route('accounts.index')->with('toast', [
            'type' => 'success',
            'message_key' => 'accounts.toast.updated',
        ]);
    }

    public function destroy(Account $account): RedirectResponse
    {
        $account->delete();

        return to_route('accounts.index');
    }
}
