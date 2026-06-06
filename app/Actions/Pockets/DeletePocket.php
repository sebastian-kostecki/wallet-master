<?php

declare(strict_types=1);

namespace App\Actions\Pockets;

use App\Models\Pocket;
use Illuminate\Validation\ValidationException;

final class DeletePocket
{
    public function handle(Pocket $pocket): void
    {
        if ($pocket->hasLinkedTransactions()) {
            throw ValidationException::withMessages([
                'pocket' => 'Cannot delete pocket with linked transactions.',
            ]);
        }

        $pocket->delete();
    }
}
