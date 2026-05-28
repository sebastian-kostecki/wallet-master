<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $amount
 * @property int $account_id
 * @property int $currency_id
 * @property int $user_id
 * @property int|null $import_id
 * @property string|null $transfer_id
 * @property TransactionType $type
 * @property Carbon|CarbonImmutable|string $date
 * @property Carbon|CarbonImmutable|string $booked_at
 */
final class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * @return Builder<self>
     */
    public static function queryForUser(User $user, array $filters = [], array $sorts = []): Builder
    {
        return self::query()
            ->with('account.currency')
            ->whereBelongsTo($user)
            ->indexFilters($filters)
            ->indexSort($sorts);
    }

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'booked_at' => 'date',
            'amount' => 'decimal:2',
            'type' => TransactionType::class,
            'transfer_id' => 'string',
            'import_id' => 'integer',
            'raw_statement_description' => 'string',
            'account_id' => 'integer',
            'currency_id' => 'integer',
            'user_id' => 'integer',
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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<Import, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeIndexFilters(Builder $query, array $filters): void
    {
        if (isset($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate(
                'date',
                '>=',
                CarbonImmutable::createFromFormat('d-m-Y', (string) $filters['from'])->toDateString(),
            );
        }

        if (! empty($filters['to'])) {
            $query->whereDate(
                'date',
                '<=',
                CarbonImmutable::createFromFormat('d-m-Y', (string) $filters['to'])->toDateString(),
            );
        }
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeIndexSort(Builder $query, array $sorts): void
    {
        $sortBy = $sorts['sort_by'] ?? null;
        $sortDirection = $sorts['sort_direction'] ?? 'desc';

        if ($sortBy === null) {
            return;
        }

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortBy, $sortDirection);
    }
}
