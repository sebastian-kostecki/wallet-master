<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

final class ImportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Import $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function commit(User $user, Import $import): bool
    {
        if ($import->user_id !== $user->id) {
            return false;
        }

        $import->loadMissing(['account' => fn ($q) => $q->withTrashed()]);

        return ! $import->account?->trashed();
    }
}
