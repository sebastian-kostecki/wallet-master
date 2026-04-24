<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\UpdateAccountDetails;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Currency;
use App\ViewModels\Accounts\AccountFormOptions;
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
        $accounts = Account::query()
            ->whereBelongsTo(auth()->user())
            ->with(['currency:id,code,symbol,precision'])
            ->orderBy('name')
            ->get(['id', 'currency_id', 'name', 'current_balance', 'bank', 'type']);

        $accounts->each->append(['bank_icon_url', 'type_label_key']);

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

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $account = Account::query()->create([
            'user_id' => $data['user_id'],
            'currency_id' => $data['currency_id'],
            'name' => $data['name'],
            'bank' => $data['bank'],
            'type' => $data['type'],
            'opening_balance' => $data['opening_balance'],
            'current_balance' => $data['opening_balance'],
        ]);

        return to_route('accounts.edit', $account);
    }

    public function edit(Account $account, AccountFormOptions $options): Response
    {
        $account->loadMissing(['currency:id,code,symbol,precision']);

        return Inertia::render('accounts/Edit', [
            'account' => $account->only(['id', 'name', 'opening_balance', 'current_balance', 'currency_id', 'bank', 'type'])
                + [
                    'currency' => $account->currency?->only(['id', 'code', 'symbol', 'precision']),
                ],
            ...$options->toArray(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccountDetails $updater): RedirectResponse
    {
        $updater->handle($account, $request->validated());

        return to_route('accounts.edit', $account);
    }

    public function destroy(Account $account): RedirectResponse
    {
        $account->delete();

        return to_route('accounts.index');
    }
}
