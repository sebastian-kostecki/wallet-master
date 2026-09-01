<?php

declare(strict_types=1);

namespace App\Actions\Pockets;

use App\Models\Pocket;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class DeletePocket
{
    public function handle(Pocket $pocket): void
    {
        DB::transaction(function () use ($pocket): void {
            Transaction::query()
                ->where('user_id', $pocket->user_id)
                ->where('pocket_id', $pocket->id)
                ->update([
                    'pocket_id' => null,
                    'pocket_movement' => null,
                ]);

            $pocket->delete();
        });
    }
}
