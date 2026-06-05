<?php

declare(strict_types=1);

namespace App\Support\Budgets;

use App\Models\Currency;

final class BudgetCurrency
{
    /**
     * @return array{code: string, symbol: string, precision: int}
     */
    public static function pln(): array
    {
        $currency = Currency::query()->where('code', 'PLN')->firstOrFail();

        return [
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'precision' => (int) $currency->precision,
        ];
    }
}
