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

    public function handle(User $user, ?string $filter = 'active'): void
    {
        $query = Goal::query()->forUser($user->id)->ordered();

        match ($filter) {
            'archived' => $query->where('is_archived', true),
            'active' => $query->where('is_archived', false),
            default => null,
        };

        $this->goals = $query->with('currency')->get();
    }

    /**
     * @return Collection<int, Goal>
     */
    public function getGoals(): Collection
    {
        return $this->goals;
    }
}
