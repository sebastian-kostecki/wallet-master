<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositMovementKind;
use Database\Factories\DepositMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $deposit_id
 * @property int $transaction_id
 * @property DepositMovementKind $kind
 * @property Deposit $deposit
 * @property Transaction $transaction
 */
final class DepositMovement extends Model
{
    /** @use HasFactory<DepositMovementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'deposit_id',
        'transaction_id',
        'kind',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deposit_id' => 'integer',
            'transaction_id' => 'integer',
            'kind' => DepositMovementKind::class,
        ];
    }

    /**
     * @return BelongsTo<Deposit, $this>
     */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
