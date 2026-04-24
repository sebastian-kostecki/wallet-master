<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\Bank;
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
    /**
     * @return array<string, array<int, array{value: string, label: string, icon_url?: string|null, icon_name?: string|null}>>
     */
    private function enumOptions(): array
    {
        return [
            'banks' => array_map(
                fn (Bank $bank): array => [
                    'value' => $bank->value,
                    'label' => $bank->label(),
                    'icon_url' => $bank === Bank::Cash ? null : asset("icons/banks/{$bank->value}.jpeg"),
                ],
                Bank::cases(),
            ),
            'accountTypes' => array_map(
                fn (AccountType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'icon_name' => match ($type) {
                        AccountType::Ror => 'wallet',
                        AccountType::Savings => 'piggyBank',
                    },
                ],
                AccountType::cases(),
            ),
        ];
    }

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
            ->get(['id', 'currency_id', 'name', 'current_balance', 'bank', 'type']);

        $accounts->each->append('bank_icon_url');
        $accounts->each(function (Account $account): void {
            $type = $account->type;

            $account->setAttribute(
                'type_label',
                $type instanceof AccountType ? $type->label() : (AccountType::tryFrom($type)?->label() ?? $type),
            );
        });

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        $currencies = Currency::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol', 'precision']);

        return Inertia::render('Accounts/Create', [
            'currencies' => $currencies,
            ...$this->enumOptions(),
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

    public function edit(Account $account): Response
    {
        $account->loadMissing(['currency:id,code,symbol,precision']);

        return Inertia::render('Accounts/Edit', [
            'account' => $account->only(['id', 'name', 'opening_balance', 'current_balance', 'currency_id', 'bank', 'type'])
                + [
                    'currency' => $account->currency?->only(['id', 'code', 'symbol', 'precision']),
                ],
            ...$this->enumOptions(),
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
            $locked->bank = $validated['bank'];
            $locked->type = $validated['type'];
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
