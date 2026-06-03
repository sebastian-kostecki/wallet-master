<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => null,
        ];
    }

    public function forGoal(Goal $goal): static
    {
        return $this->state(fn (): array => [
            'goal_id' => $goal->id,
            'user_id' => $goal->user_id,
        ]);
    }
}
