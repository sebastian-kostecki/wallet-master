<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Models\ImportFailedRow;
use App\Models\User;

final class DismissImportFailedRow
{
    public function handle(User $user, ImportFailedRow $importFailedRow): void
    {
        if ($importFailedRow->user_id !== $user->id || $importFailedRow->dismissed_at !== null) {
            return;
        }

        $importFailedRow->dismissed_at = now();
        $importFailedRow->save();
    }
}
