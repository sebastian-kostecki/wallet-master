<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportFailedRowReason: string
{
    case MissingFields = 'missing_fields';

    case InvalidDate = 'invalid_date';

    case InvalidAmount = 'invalid_amount';

    case ZeroAmount = 'zero_amount';

    case InvalidRow = 'invalid_row';

    public static function fromException(\Throwable $exception): self
    {
        return match ($exception->getMessage()) {
            'Required import columns are empty.' => self::MissingFields,
            'Invalid transaction date.' => self::InvalidDate,
            'Invalid transaction amount.' => self::InvalidAmount,
            'Transaction amount cannot be zero.', 'Amount cannot be zero.' => self::ZeroAmount,
            default => self::InvalidRow,
        };
    }

    public function labelKey(): string
    {
        return 'imports.failed_rows.reasons.'.$this->value;
    }
}
