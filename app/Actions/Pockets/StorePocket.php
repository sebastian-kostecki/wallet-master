<?php

declare(strict_types=1);

namespace App\Actions\Pockets;

use App\Models\Pocket;
use App\Models\User;
use App\Telemetry\Event;

final class StorePocket
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(User $user, array $validated): Pocket
    {
        $maxSort = (int) Pocket::query()
            ->where('user_id', $user->id)
            ->max('sort_order');

        $pocket = Pocket::query()->create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'icon' => $validated['icon'],
            'color' => $validated['color'],
            'sort_order' => $maxSort + 10,
            'currency_id' => (int) $validated['currency_id'],
            'initial_balance' => $validated['initial_balance'] ?? 0,
            'target_amount' => $validated['target_amount'] ?? null,
            'planning_mode' => $validated['planning_mode'] ?? null,
            'monthly_contribution' => $validated['monthly_contribution'] ?? null,
            'target_date' => $validated['target_date'] ?? null,
            'is_archived' => false,
        ]);

        Event::record('pocket_created', ['pocket_id' => $pocket->id], $user->id);

        return $pocket;
    }
}
