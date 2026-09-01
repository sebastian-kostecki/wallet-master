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
        $fillable = [
            'name',
            'icon',
            'color',
            'target_amount',
            'planning_mode',
            'monthly_contribution',
            'target_date',
            'sort_order',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $validated)) {
                $pocket->{$field} = $validated[$field];
            }
        }

        $pocket->save();

        Event::record('pocket_updated', ['pocket_id' => $pocket->id], $pocket->user_id);

        return $pocket;
    }
}
