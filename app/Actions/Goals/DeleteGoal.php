<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Models\Goal;
use Illuminate\Validation\ValidationException;

final class DeleteGoal
{
    public function handle(Goal $goal): void
    {
        if ($goal->hasLinkedTransactions()) {
            throw ValidationException::withMessages([
                'goal' => 'Cannot delete goal with linked transactions.',
            ]);
        }

        $goal->delete();
    }
}
