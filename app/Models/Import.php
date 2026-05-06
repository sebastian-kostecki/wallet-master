<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $rows_total
 * @property int $rows_imported
 * @property int $rows_skipped_duplicate
 * @property int $rows_failed_validation
 * @property array<string, mixed>|null $mapping
 * @property array<string, mixed>|null $details
 * @property Carbon|null $committed_at
 */
final class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rows_total' => 'integer',
            'rows_imported' => 'integer',
            'rows_skipped_duplicate' => 'integer',
            'rows_failed_validation' => 'integer',
            'mapping' => 'array',
            'details' => 'array',
            'committed_at' => 'datetime',
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
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
