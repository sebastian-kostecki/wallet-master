import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

function formatDdMmYyyy(d: Date): string {
    return `${pad2(d.getDate())}-${pad2(d.getMonth() + 1)}-${d.getFullYear()}`;
}

export function defaultMonthSearch(): string {
    const now = new Date();
    const from = new Date(now.getFullYear(), now.getMonth(), 1);
    const to = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    return `?from=${encodeURIComponent(formatDdMmYyyy(from))}&to=${encodeURIComponent(formatDdMmYyyy(to))}`;
}

export function useTransactionsIndexSearch() {
    const page = usePage<{ transactionsIndexSearch?: string }>();

    const currentSearch = computed(() => {
        const url = page.url;
        const idx = url.indexOf('?');

        return idx >= 0 ? url.slice(idx) : '';
    });

    const transactionsIndexSearch = computed(() => {
        if (currentSearch.value !== '') {
            return currentSearch.value;
        }

        const shared = page.props.transactionsIndexSearch ?? '';

        return shared !== '' ? shared : '';
    });

    const transactionsIndexHref = computed(() => {
        const search = transactionsIndexSearch.value !== '' ? transactionsIndexSearch.value : defaultMonthSearch();

        return route('transactions.index') + search;
    });

    return {
        currentSearch,
        transactionsIndexSearch,
        transactionsIndexHref,
        defaultMonthSearch,
    };
}
