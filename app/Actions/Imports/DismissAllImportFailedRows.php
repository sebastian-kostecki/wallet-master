<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Models\ImportFailedRow;
use App\Models\User;

final class DismissAllImportFailedRows
{
    public function handle(User $user, ?int $accountId = null): int
    {
        return ImportFailedRow::query()
            ->where('user_id', $user->id)
            ->unresolved()
            ->when($accountId !== null, fn ($query) => $query->where('account_id', $accountId))
            ->update([
                'dismissed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
