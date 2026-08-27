<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DepositStatus;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deposit>
 */
final class DepositFactory extends Factory
{
    protected $model = Deposit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'currency_id' => fn () => (int) Currency::query()->where('code', 'PLN')->value('id')
                ?: throw new \RuntimeException('Seed CurrencySeeder before DepositFactory.'),
            'source_account_id' => Account::factory(),
            'name' => fake()->words(2, true),
            'principal' => '1000.00',
            'opened_at' => now()->toDateString(),
            'matures_at' => null,
            'status' => DepositStatus::Active,
            'closed_at' => null,
        ];
    }
}
