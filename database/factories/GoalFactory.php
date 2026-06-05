<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GoalPlanningMode;
use App\Models\Currency;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
final class GoalFactory extends Factory
{
    protected $model = Goal::class;

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
                ?: throw new \RuntimeException('Seed CurrencySeeder before GoalFactory.'),
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
            'planning_mode' => GoalPlanningMode::Monthly,
            'monthly_contribution' => $monthly,
            'target_date' => null,
        ]);
    }

    public function withTargetByDate(string $amount, string $date): static
    {
        return $this->state(fn (): array => [
            'target_amount' => $amount,
            'planning_mode' => GoalPlanningMode::ByDate,
            'target_date' => $date,
            'monthly_contribution' => null,
        ]);
    }
}
