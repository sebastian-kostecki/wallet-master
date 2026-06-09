<?php

namespace Database\Factories;

use App\Enums\ImportFailedRowReason;
use App\Models\Account;
use App\Models\Import;
use App\Models\ImportFailedRow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportFailedRow>
 */
class ImportFailedRowFactory extends Factory
{
    protected $model = ImportFailedRow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_id' => Import::factory(),
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'row_number' => fake()->numberBetween(1, 100),
            'reason_code' => ImportFailedRowReason::InvalidDate,
            'date_raw' => 'invalid-date',
            'amount_raw' => '-12,34',
            'description_raw' => fake()->sentence(),
            'subject_raw' => null,
            'dismissed_at' => null,
        ];
    }

    public function dismissed(): static
    {
        return $this->state(fn (): array => [
            'dismissed_at' => now(),
        ]);
    }
}
