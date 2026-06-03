<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $goal_id
 * @property int $year
 * @property int $month
 * @property numeric-string|null $amount
 */
final class GoalMonthlyEstimate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'goal_id',
        'year',
        'month',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'goal_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
