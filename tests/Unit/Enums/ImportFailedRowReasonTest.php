<?php

use App\Enums\ImportFailedRowReason;

test('import failed row reason maps known parser exceptions', function (string $message, ImportFailedRowReason $expected) {
    expect(ImportFailedRowReason::fromException(new RuntimeException($message)))->toBe($expected);
})->with([
    ['Required import columns are empty.', ImportFailedRowReason::MissingFields],
    ['Invalid transaction date.', ImportFailedRowReason::InvalidDate],
    ['Invalid transaction amount.', ImportFailedRowReason::InvalidAmount],
    ['Transaction amount cannot be zero.', ImportFailedRowReason::ZeroAmount],
    ['Amount cannot be zero.', ImportFailedRowReason::ZeroAmount],
]);
