<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
            ->whereNull('deleted_at')
            ->with(['currency:id,code,symbol,precision'])
            ->orderBy('name')
            ->get(['id', 'currency_id', 'name', 'current_balance']);

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        $pln = Currency::query()
            ->where('code', 'PLN')
            ->firstOrFail(['id', 'code', 'name', 'symbol', 'precision']);

        return Inertia::render('Accounts/Create', [
            'currencies' => [$pln],
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $plnId = Currency::query()
            ->where('code', 'PLN')
            ->firstOrFail(['id'])
            ->id;

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['currency_id'] = $plnId;

        $account = Account::query()->create([
            'user_id' => $data['user_id'],
            'currency_id' => $data['currency_id'],
            'name' => $data['name'],
            'opening_balance' => $data['opening_balance'],
            'current_balance' => $data['opening_balance'],
        ]);

        return to_route('accounts.edit', $account);
    }

    public function edit(Account $account): Response
    {
        $account->loadMissing(['currency:id,code,symbol,precision']);

        return Inertia::render('Accounts/Edit', [
            'account' => $account->only(['id', 'name', 'opening_balance', 'current_balance', 'currency_id'])
                + [
                    'currency' => $account->currency?->only(['id', 'code', 'symbol', 'precision']),
                ],
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validated();

        DB::transaction(function () use ($account, $validated): void {
            $locked = Account::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldOpening = (string) $locked->opening_balance;
            $newOpening = (string) $validated['opening_balance'];

            $delta = bcsub($newOpening, $oldOpening, 2);

            $locked->name = $validated['name'];
            $locked->opening_balance = $validated['opening_balance'];
            $locked->current_balance = bcadd((string) $locked->current_balance, $delta, 2);
            $locked->save();
        });

        return to_route('accounts.edit', $account);
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        $account->delete();

        return to_route('accounts.index');
    }
}
