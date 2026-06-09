<?php

declare(strict_types=1);

namespace App\Support\Transactions;

use App\Models\Transaction;
use Carbon\CarbonImmutable;

final class TransactionDateRelative
{
    public static function displayDateIso(Transaction $transaction): string
    {
        $dateIso = $transaction->date->toDateString();
        $bookedAtIso = $transaction->booked_at?->toDateString() ?? $dateIso;

        return $transaction->booked_at !== null
            ? $bookedAtIso
            : $dateIso;
    }

    public static function format(Transaction $transaction): string
    {
        $displayDate = CarbonImmutable::parse(self::displayDateIso($transaction))->startOfDay();
        $today = CarbonImmutable::today();

        if ($displayDate->equalTo($today)) {
            return app()->getLocale() === 'pl' ? 'dzisiaj' : 'today';
        }

        return $displayDate
            ->locale(app()->getLocale())
            ->diffForHumans();
    }
}
