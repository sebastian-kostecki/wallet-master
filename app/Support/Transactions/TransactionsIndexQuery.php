<?php

declare(strict_types=1);

namespace App\Support\Transactions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TransactionsIndexQuery
{
    private const SESSION_KEY = 'transactions.index.query';

    /** @var list<string> */
    private const ALLOWED_KEYS = [
        'account_id',
        'category_id',
        'from',
        'to',
        'sort',
        'direction',
        'per_page',
    ];

    public static function sessionKey(): string
    {
        return self::SESSION_KEY;
    }

    public static function remember(Request $request): void
    {
        session([self::SESSION_KEY => self::extract($request)]);
    }

    /**
     * @return array<string, int|string>
     */
    public static function params(): array
    {
        /** @var array<string, int|string>|null $stored */
        $stored = session(self::SESSION_KEY);

        return $stored ?? [];
    }

    public static function toQueryString(): string
    {
        $params = self::params();

        if ($params === []) {
            return '';
        }

        return '?'.http_build_query($params);
    }

    public static function redirect(Request $request): RedirectResponse
    {
        $fromRequest = self::extract($request);
        $params = $fromRequest !== [] ? $fromRequest : self::params();

        return to_route('transactions.index', $params);
    }

    /**
     * @return array<string, int|string>
     */
    private static function extract(Request $request): array
    {
        $filtered = [];

        foreach (self::ALLOWED_KEYS as $key) {
            $value = $request->query($key);

            if ($value === null || $value === '') {
                continue;
            }

            $filtered[$key] = $key === 'account_id' || $key === 'category_id' || $key === 'per_page'
                ? (int) $value
                : (string) $value;
        }

        return $filtered;
    }
}
