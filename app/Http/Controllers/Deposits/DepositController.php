<?php

declare(strict_types=1);

namespace App\Http\Controllers\Deposits;

use App\Actions\Deposits\CloseDeposit;
use App\Actions\Deposits\CreateDeposit;
use App\Actions\Deposits\UpdateDepositDetails;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deposits\CloseDepositRequest;
use App\Http\Requests\Deposits\StoreDepositRequest;
use App\Http\Requests\Deposits\UpdateDepositRequest;
use App\Models\Deposit;
use Illuminate\Http\RedirectResponse;

final class DepositController extends Controller
{
    public function create(): RedirectResponse
    {
        $this->authorize('create', Deposit::class);

        return to_route('accounts.index', ['kind' => 'deposits']);
    }

    public function store(StoreDepositRequest $request, CreateDeposit $createDeposit): RedirectResponse
    {
        $this->authorize('create', Deposit::class);

        $createDeposit->handle($request->user(), $request->validated());

        return to_route('accounts.index', ['kind' => 'deposits'])->with('toast', [
            'type' => 'success',
            'message_key' => 'deposits.toast.created',
        ]);
    }

    public function edit(Deposit $deposit): RedirectResponse
    {
        $this->authorize('update', $deposit);

        return to_route('accounts.index', ['kind' => 'deposits']);
    }

    public function update(
        UpdateDepositRequest $request,
        Deposit $deposit,
        UpdateDepositDetails $updateDepositDetails,
    ): RedirectResponse {
        $this->authorize('update', $deposit);

        $updateDepositDetails->handle($deposit, $request->validated());

        return to_route('accounts.index', ['kind' => 'deposits'])->with('toast', [
            'type' => 'success',
            'message_key' => 'deposits.toast.updated',
        ]);
    }

    public function close(
        CloseDepositRequest $request,
        Deposit $deposit,
        CloseDeposit $closeDeposit,
    ): RedirectResponse {
        $this->authorize('update', $deposit);

        $closeDeposit->handle($request->user(), $deposit, $request->validated());

        return to_route('accounts.index', ['kind' => 'deposits'])->with('toast', [
            'type' => 'success',
            'message_key' => 'deposits.toast.closed',
        ]);
    }
}
