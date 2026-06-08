<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PocketPlanningMode;
use App\Models\Currency;
use App\Models\Pocket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pocket>
 */
final class PocketFactory extends Factory
{
    protected $model = Pocket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->word(),
            'icon' => 'target',
            'color' => '#6366f1',
            'sort_order' => 10,
            'currency_id' => fn () => (int) Currency::query()->where('code', 'PLN')->value('id')
                ?: throw new \RuntimeException('Seed CurrencySeeder before PocketFactory.'),
            'initial_balance' => '0.00',
            'target_amount' => null,
            'planning_mode' => null,
            'monthly_contribution' => null,
            'target_date' => null,
            'is_archived' => false,
        ];
    }

    public function withTargetMonthly(string $amount, string $monthly): static
    {
        return $this->state(fn (): array => [
            'target_amount' => $amount,
            'planning_mode' => PocketPlanningMode::Monthly,
            'monthly_contribution' => $monthly,
            'target_date' => null,
        ]);
    }

    public function withTargetByDate(string $amount, string $date): static
    {
        return $this->state(fn (): array => [
            'target_amount' => $amount,
            'planning_mode' => PocketPlanningMode::ByDate,
            'target_date' => $date,
            'monthly_contribution' => null,
        ]);
    }
}
