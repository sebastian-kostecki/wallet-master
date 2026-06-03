<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\DomainException;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';

    /**
     * Infer income vs expense from a signed amount. {@see self::Transfer} and {@see self::Adjustment} are not derivable from amount alone.
     *
     * @param  numeric-string  $amountDecimal
     */
    public static function fromAmount(string $amountDecimal): self
    {
        $cmp = bccomp($amountDecimal, '0', 2);

        if ($cmp === 0) {
            throw new DomainException('Amount cannot be zero.');
        }

        return $cmp === -1 ? self::Expense : self::Income;
    }
}
