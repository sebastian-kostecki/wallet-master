<?php

namespace Database\Factories;

use App\Models\Pocket;
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
            'pocket_id' => null,
        ];
    }

    public function forPocket(Pocket $pocket): static
    {
        return $this->state(fn (): array => [
            'pocket_id' => $pocket->id,
            'user_id' => $pocket->user_id,
        ]);
    }
}
