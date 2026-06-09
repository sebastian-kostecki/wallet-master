<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ImportFailedRow;
use App\Models\User;

final class ImportFailedRowPolicy
{
    public function dismiss(User $user, ImportFailedRow $importFailedRow): bool
    {
        return $importFailedRow->user_id === $user->id
            && $importFailedRow->dismissed_at === null;
    }

    public function dismissAll(User $user): bool
    {
        return true;
    }
}
