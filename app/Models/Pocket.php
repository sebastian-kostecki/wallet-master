<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PocketPlanningMode;
use Database\Factories\PocketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $icon
 * @property string $color
 * @property int $sort_order
 * @property int $currency_id
 * @property string $initial_balance
 * @property string|null $target_amount
 * @property PocketPlanningMode|null $planning_mode
 * @property string|null $monthly_contribution
 * @property Carbon|null $target_date
 * @property bool $is_archived
 * @property Currency $currency
 */
final class Pocket extends Model
{
    /** @use HasFactory<PocketFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'icon',
        'color',
        'sort_order',
        'currency_id',
        'initial_balance',
        'target_amount',
        'planning_mode',
        'monthly_contribution',
        'target_date',
        'is_archived',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'sort_order' => 'integer',
            'currency_id' => 'integer',
            'initial_balance' => 'decimal:2',
            'target_amount' => 'decimal:2',
            'planning_mode' => PocketPlanningMode::class,
            'monthly_contribution' => 'decimal:2',
            'target_date' => 'date',
            'is_archived' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function hasLinkedTransactions(): bool
    {
        if (! Schema::hasColumn('transactions', 'pocket_id')) {
            return false;
        }

        return $this->transactions()->exists();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
