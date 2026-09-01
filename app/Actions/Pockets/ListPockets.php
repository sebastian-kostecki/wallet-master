<?php

declare(strict_types=1);

namespace App\Actions\Pockets;

use App\Models\Pocket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ListPockets
{
    /** @var Collection<int, Pocket> */
    private Collection $pockets;

    public function handle(User $user): void
    {
        $this->pockets = Pocket::query()
            ->forUser($user->id)
            ->ordered()
            ->with('currency')
            ->get();
    }

    /**
     * @return Collection<int, Pocket>
     */
    public function getPockets(): Collection
    {
        return $this->pockets;
    }
}
