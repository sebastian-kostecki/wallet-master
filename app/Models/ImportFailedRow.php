<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportFailedRowReason;
use Database\Factories\ImportFailedRowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ImportFailedRowReason $reason_code
 * @property Carbon|null $dismissed_at
 */
final class ImportFailedRow extends Model
{
    /** @use HasFactory<ImportFailedRowFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_id',
        'user_id',
        'account_id',
        'row_number',
        'reason_code',
        'date_raw',
        'amount_raw',
        'description_raw',
        'subject_raw',
        'dismissed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason_code' => ImportFailedRowReason::class,
            'row_number' => 'integer',
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at');
    }

    /**
     * @return BelongsTo<Import, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
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
}
