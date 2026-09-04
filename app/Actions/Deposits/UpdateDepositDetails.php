<?php

declare(strict_types=1);

namespace App\Actions\Deposits;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Telemetry\Event;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class UpdateDepositDetails
{
    /**
     * @param  array{
     *   name?: ?string,
     *   matures_at?: ?string,
     * }  $validated
     */
    public function handle(Deposit $deposit, array $validated): Deposit
    {
        if ($deposit->status !== DepositStatus::Active) {
            throw ValidationException::withMessages([
                'deposit' => 'Only active deposits can be updated.',
            ]);
        }

        if (array_key_exists('name', $validated)) {
            $name = isset($validated['name']) ? trim($validated['name']) : null;
            $deposit->name = $name === '' ? null : $name;
        }

        if (array_key_exists('matures_at', $validated)) {
            $deposit->matures_at = ! empty($validated['matures_at'])
                ? Carbon::createFromFormat('d-m-Y', $validated['matures_at'])
                : null;
        }

        $deposit->save();

        Event::record('deposit_updated', [
            'deposit_id' => $deposit->id,
        ], $deposit->user_id);

        return $deposit;
    }
}
