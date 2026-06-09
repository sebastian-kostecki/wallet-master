<?php

declare(strict_types=1);

namespace App\Data\Pockets;

use App\Models\Currency;
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;

final class PocketFormOptions
{
    /**
     * @return array{
     *   icons: list<array{value: string, label_key: string}>,
     *   colors: list<array{value: string}>,
     *   currencies: list<array{id: int, code: string, name: string, symbol: string, precision: int}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'icons' => array_map(
                fn (string $icon): array => [
                    'value' => $icon,
                    'label_key' => 'categories.icons.'.$icon,
                ],
                CategoryIcons::values(),
            ),
            'colors' => array_map(
                fn (string $hex): array => ['value' => $hex],
                CategoryColors::values(),
            ),
            'currencies' => Currency::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'symbol', 'precision'])
                ->map(fn (Currency $currency): array => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'precision' => $currency->precision,
                ])
                ->all(),
        ];
    }
}
