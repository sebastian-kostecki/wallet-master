<?php

declare(strict_types=1);

namespace App\Actions\Goals;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ListGoals
{
    /** @var Collection<int, Goal> */
    private Collection $goals;

    public function handle(User $user, ?int $year = null): void
    {
        $query = Goal::query()
            ->where('user_id', $user->id)
            ->ordered();

        if ($year !== null) {
            $query->with([
                'annualEstimates' => fn ($estimateQuery) => $estimateQuery->where('year', $year),
            ]);
        }

        $this->goals = $query->get();
    }

    /**
     * @return Collection<int, Goal>
     */
    public function getGoals(): Collection
    {
        return $this->goals;
    }
}
