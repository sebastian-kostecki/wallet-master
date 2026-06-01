<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
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
 * @property Carbon $date
 * @property Carbon|null $booked_at
 */
final class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'currency_id',
        'import_id',
        'date',
        'booked_at',
        'amount',
        'type',
        'description',
        'subject',
        'normalized_description',
        'raw_statement_description',
        'dedupe_hash',
        'transfer_id',
    ];

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
}
