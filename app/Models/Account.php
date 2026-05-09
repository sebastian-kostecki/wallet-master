<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\Bank;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Bank|null $bank
 * @property AccountType|null $type
 * @property numeric-string $opening_balance
 * @property numeric-string $current_balance
 */
final class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    public function getBankIconUrlAttribute(): ?string
    {
        $bank = $this->bank;

        if ($bank === null) {
            return null;
        }

        return $bank->bankIconUrl();
    }

    public function getTypeLabelKeyAttribute(): string
    {
        $type = $this->type;

        if ($type === null) {
            return '';
        }

        return $type->labelKey();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bank' => Bank::class,
            'type' => AccountType::class,
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
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
     * @return HasMany<Import, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<AccountBalanceAdjustment, $this>
     */
    public function balanceAdjustments(): HasMany
    {
        return $this->hasMany(AccountBalanceAdjustment::class);
    }
}
