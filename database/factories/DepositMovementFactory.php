<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DepositMovementKind;
use App\Models\Deposit;
use App\Models\DepositMovement;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepositMovement>
 */
final class DepositMovementFactory extends Factory
{
    protected $model = DepositMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deposit_id' => Deposit::factory(),
            'transaction_id' => Transaction::factory(),
            'kind' => DepositMovementKind::Open,
        ];
    }
}
