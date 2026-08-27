<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositStatus;
use Database\Factories\DepositFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $currency_id
 * @property int $source_account_id
 * @property string|null $name
 * @property numeric-string $principal
 * @property Carbon $opened_at
 * @property Carbon|null $matures_at
 * @property DepositStatus $status
 * @property Carbon|null $closed_at
 * @property User $user
 * @property Currency $currency
 * @property Account $sourceAccount
 */
final class Deposit extends Model
{
    /** @use HasFactory<DepositFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'currency_id',
        'source_account_id',
        'name',
        'principal',
        'opened_at',
        'matures_at',
        'status',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'currency_id' => 'integer',
            'source_account_id' => 'integer',
            'principal' => 'decimal:2',
            'opened_at' => 'date',
            'matures_at' => 'date',
            'status' => DepositStatus::class,
            'closed_at' => 'date',
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
     * @return BelongsTo<Account, $this>
     */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    /**
     * @return HasMany<DepositMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(DepositMovement::class);
    }
}
