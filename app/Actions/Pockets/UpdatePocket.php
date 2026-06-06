<?php

declare(strict_types=1);

namespace App\Actions\Pockets;

use App\Models\Pocket;
use App\Telemetry\Event;

final class UpdatePocket
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(Pocket $pocket, array $validated): Pocket
    {
        $wasArchived = $pocket->is_archived;

        $fillable = [
            'name',
            'icon',
            'color',
            'target_amount',
            'planning_mode',
            'monthly_contribution',
            'target_date',
            'is_archived',
            'sort_order',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $validated)) {
                $pocket->{$field} = $validated[$field];
            }
        }

        $pocket->save();

        Event::record('pocket_updated', ['pocket_id' => $pocket->id], $pocket->user_id);

        if (array_key_exists('is_archived', $validated) && (bool) $validated['is_archived'] !== $wasArchived) {
            Event::record(
                (bool) $validated['is_archived'] ? 'pocket_archived' : 'pocket_unarchived',
                ['pocket_id' => $pocket->id],
                $pocket->user_id,
            );
        }

        return $pocket;
    }
}
