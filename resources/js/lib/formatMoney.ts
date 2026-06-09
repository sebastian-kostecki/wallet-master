export type CurrencyDisplay = {
    symbol: string;
    precision?: number;
};

export function formatMoney(value: string | number | null | undefined, currency?: CurrencyDisplay | null, locale = 'pl-PL'): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const parsed = typeof value === 'number' ? value : Number(String(value).replace(',', '.'));

    if (Number.isNaN(parsed)) {
        return String(value);
    }

    const precision = currency?.precision ?? 2;
    const formatted = new Intl.NumberFormat(locale, {
        minimumFractionDigits: precision,
        maximumFractionDigits: precision,
    }).format(parsed);

    const symbol = currency?.symbol?.trim();

    return symbol ? `${formatted} ${symbol}` : formatted;
}
