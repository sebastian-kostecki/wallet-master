<script setup lang="ts">
import TransactionsIndexHeaderFilters from '@/components/transactions/TransactionsIndexHeaderFilters.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowDownLeft, ArrowRightLeft, ArrowUp, ArrowUpDown, ArrowUpRight, Pencil, PiggyBank, Wallet } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
    type: string;
    type_label_key: string;
    bank_icon_url: string | null;
};

type Currency = {
    id: number;
    code: string;
    symbol: string | null;
    precision: number;
};

type Transaction = {
    id: number;
    date: string; // YYYY-MM-DD
    date_relative: string;
    amount: string | number;
    type: 'income' | 'expense' | string;
    description: string;
    subject: string | null;
    transfer_id: string | null;
    account: Account | null;
    currency: Currency | null;
};

type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatorMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Paginator<T> = {
    data: T[];
    links: PaginatorLink[];
    /**
     * Backend returns a "flattened" paginator shape (current_page/last_page/total on root),
     * but some Laravel pagination payloads also include `meta`.
     */
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    meta?: PaginatorMeta;
};

type Filters = {
    all_time?: boolean;
    account_id: number | null;
    from: string | null; // DD-MM-YYYY
    to: string | null; // DD-MM-YYYY
    sort: 'date' | 'amount' | string;
    direction: 'asc' | 'desc' | string;
};

const props = defineProps<{
    accounts: Account[];
    filters: Filters;
    transactions: Paginator<Transaction>;
    summary: {
        total_income: string;
        total_expense: string;
    };
}>();

const page = usePage<{ errors?: Record<string, string> }>();
const { t, locale } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('transactions.index.title'),
        href: '/transactions',
    },
]);

const money = computed(() => {
    const resolvedLocale = locale.value === 'pl' ? 'pl-PL' : 'en-US';
    return new Intl.NumberFormat(resolvedLocale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
});

function toNumber(value: string | number): number {
    if (typeof value === 'number') {
        return value;
    }

    const parsed = Number(value);
    return Number.isNaN(parsed) ? 0 : parsed;
}

function formatAmount(value: string | number): string {
    return money.value.format(toNumber(value));
}

function formatDateIsoToDots(input: string): string {
    const parts = input.split('-');
    if (parts.length !== 3) {
        return input;
    }

    const [yyyy, mm, dd] = parts;
    if (!yyyy || !mm || !dd) {
        return input;
    }

    return `${dd}.${mm}.${yyyy}`;
}

type TransactionIconVariant = 'internal' | 'expense' | 'income';

function transactionVariant(tx: Transaction): TransactionIconVariant {
    if (tx.transfer_id) {
        return 'internal';
    }

    return tx.type === 'expense' ? 'expense' : 'income';
}

function transactionIcon(tx: Transaction) {
    const variant = transactionVariant(tx);

    if (variant === 'internal') {
        return {
            component: ArrowRightLeft,
            containerClass: 'bg-blue-50 text-blue-600 dark:bg-blue-950/30 dark:text-blue-400',
        } as const;
    }

    if (variant === 'expense') {
        return {
            component: ArrowDownLeft,
            containerClass: 'bg-rose-50 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400',
        } as const;
    }

    return {
        component: ArrowUpRight,
        containerClass: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400',
    } as const;
}

const accountTypeIcons = computed(() => {
    return {
        checking: Wallet,
        savings: PiggyBank,
    } as const;
});

function resolveAccountTypeIcon(type: string) {
    return accountTypeIcons.value[type as keyof typeof accountTypeIcons.value] ?? Wallet;
}

const currentSearch = computed(() => {
    const idx = page.url.indexOf('?');
    return idx >= 0 ? page.url.slice(idx) : '';
});

const hasImportRoute = computed(() => {
    const r = route as any;
    return typeof r?.has === 'function' ? Boolean(r.has('imports.index')) : false;
});

const isLoading = ref(false);
const stopStart = router.on('start', () => {
    isLoading.value = true;
});
const stopFinish = router.on('finish', () => {
    isLoading.value = false;
});
onBeforeUnmount(() => {
    stopStart();
    stopFinish();
});

function clearFilters() {
    router.get(route('transactions.index'), { sort: 'date', direction: 'desc' }, { preserveScroll: true, replace: true });
}

function setSort(sort: 'date' | 'amount') {
    const currentSort = props.filters.sort ?? 'date';
    const currentDirection = props.filters.direction ?? (currentSort === 'date' ? 'desc' : 'asc');

    const wasSame = currentSort === sort;
    const nextDirection = wasSame ? (currentDirection === 'asc' ? 'desc' : 'asc') : sort === 'date' ? 'desc' : 'asc';

    router.get(
        route('transactions.index'),
        {
            account_id: props.filters.account_id ?? undefined,
            from: props.filters.from ?? undefined,
            to: props.filters.to ?? undefined,
            sort,
            direction: nextDirection,
        },
        {
            preserveScroll: true,
            replace: true,
            preserveState: 'errors',
        },
    );
}

const hasActiveFilters = computed(() => {
    return Boolean(props.filters.account_id !== null || (props.filters.from ?? '').trim() !== '' || (props.filters.to ?? '').trim() !== '');
});

const isFirstUseEmpty = computed(() => props.transactions.total === 0 && !hasActiveFilters.value);
const isFilteredEmpty = computed(() => props.transactions.total === 0 && hasActiveFilters.value);

function directionIcon() {
    const currentSort = props.filters.sort ?? 'date';
    const currentDirection = props.filters.direction ?? (currentSort === 'date' ? 'desc' : 'asc');
    return currentDirection === 'asc' ? ArrowUp : ArrowDown;
}

const summaryIncome = computed(() => formatAmount(props.summary.total_income));
const summaryExpense = computed(() => formatAmount(props.summary.total_expense));

const serverErrors = computed<Record<string, string>>(() => page.props.errors ?? {});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('transactions.index.title')" />

        <template #headerActions>
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                <TransactionsIndexHeaderFilters :accounts="accounts" :filters="filters" :server-errors="serverErrors" :is-loading="isLoading" />
                <Button as-child class="sm:shrink-0">
                    <Link :href="route('transactions.create') + currentSearch">{{ t('transactions.index.addTransaction') }}</Link>
                </Button>
            </div>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">{{ t('transactions.index.summary.income') }}</p>
                    <div class="mt-2">
                        <Skeleton v-if="isLoading" class="h-8 w-40" />
                        <p v-else class="text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">+{{ summaryIncome }}</p>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">{{ t('transactions.index.summary.expense') }}</p>
                    <div class="mt-2">
                        <Skeleton v-if="isLoading" class="h-8 w-40" />
                        <p v-else class="text-2xl font-semibold tabular-nums text-rose-600 dark:text-rose-400">-{{ summaryExpense }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border" :aria-busy="isLoading ? 'true' : 'false'">
                <div v-if="isLoading" class="p-6">
                    <div class="grid gap-3">
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                    </div>
                </div>

                <div v-else-if="isFilteredEmpty" class="p-8 text-center">
                    <p class="text-sm text-muted-foreground">{{ t('transactions.index.empty.filtered') }}</p>
                    <div class="mt-4 flex flex-col items-center justify-center gap-2 sm:flex-row">
                        <Button as-child>
                            <Link :href="route('transactions.create') + currentSearch">{{ t('transactions.index.addTransaction') }}</Link>
                        </Button>
                        <Button variant="secondary" @click="clearFilters">{{ t('transactions.index.filters.clear') }}</Button>
                    </div>
                </div>

                <div v-else-if="isFirstUseEmpty" class="p-8 text-center">
                    <p class="text-sm text-muted-foreground">{{ t('transactions.index.empty.firstUse') }}</p>
                    <div class="mt-4 flex flex-col items-center justify-center gap-2 sm:flex-row">
                        <Button as-child>
                            <Link :href="route('transactions.create')">{{ t('transactions.index.addTransaction') }}</Link>
                        </Button>
                        <Button v-if="hasImportRoute" variant="secondary" as-child>
                            <Link :href="route('imports.index')">{{ t('transactions.index.empty.import') }}</Link>
                        </Button>
                    </div>
                </div>

                <div v-else>
                    <div class="hidden md:block">
                        <table class="w-full text-sm">
                            <thead class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground dark:border-sidebar-border">
                                <tr>
                                    <th class="px-6 py-3">
                                        <button
                                            class="inline-flex items-center gap-2 hover:text-foreground"
                                            type="button"
                                            :aria-label="t('transactions.index.a11y.sortByDate')"
                                            @click="setSort('date')"
                                        >
                                            {{ t('transactions.index.table.date') }}
                                            <component
                                                :is="(filters.sort ?? 'date') === 'date' ? directionIcon() : ArrowUpDown"
                                                class="h-4 w-4"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </th>
                                    <th class="px-6 py-3">{{ t('transactions.index.table.description') }}</th>
                                    <th class="px-6 py-3">{{ t('transactions.index.table.account') }}</th>
                                    <th class="px-6 py-3">
                                        <button
                                            class="inline-flex items-center gap-2 hover:text-foreground"
                                            type="button"
                                            :aria-label="t('transactions.index.a11y.sortByAmount')"
                                            @click="setSort('amount')"
                                        >
                                            {{ t('transactions.index.table.amount') }}
                                            <component
                                                :is="(filters.sort ?? 'date') === 'amount' ? directionIcon() : ArrowUpDown"
                                                class="h-4 w-4"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </th>
                                    <th class="px-6 py-3 text-right">{{ t('transactions.index.table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="tx in transactions.data"
                                    :key="tx.id"
                                    class="border-b border-sidebar-border/50 last:border-b-0 dark:border-sidebar-border"
                                >
                                    <td class="whitespace-nowrap px-6 py-4 tabular-nums">
                                        <div class="text-sm font-medium text-foreground">
                                            {{ formatDateIsoToDots(tx.date) }}
                                        </div>
                                        <div class="mt-0.5 text-xs text-muted-foreground">
                                            {{ tx.date_relative }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <div
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                                :class="transactionIcon(tx).containerClass"
                                                aria-hidden="true"
                                            >
                                                <component :is="transactionIcon(tx).component" class="h-4 w-4" />
                                            </div>

                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-foreground" style="max-width: 420px">
                                                    {{ tx.subject ?? '—' }}
                                                </p>
                                                <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                                    {{ tx.description }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <div class="shrink-0">
                                                <img
                                                    v-if="tx.account?.bank_icon_url"
                                                    :src="tx.account.bank_icon_url"
                                                    :alt="tx.account.name"
                                                    class="h-9 w-9 rounded-lg object-contain"
                                                    loading="lazy"
                                                />
                                                <component
                                                    v-else-if="tx.account"
                                                    :is="resolveAccountTypeIcon(tx.account.type)"
                                                    class="h-9 w-9 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                <div v-else class="h-9 w-9 rounded-lg bg-muted" aria-hidden="true" />
                                            </div>

                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <p class="truncate text-sm font-medium">
                                                        {{ tx.account?.name ?? t('transactions.index.readOnly.deletedAccount') }}
                                                    </p>
                                                    <span
                                                        v-if="!tx.account"
                                                        class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                                    >
                                                        {{ t('transactions.index.readOnly.badge') }}
                                                    </span>
                                                </div>
                                                <p v-if="tx.account" class="mt-0.5 truncate text-xs text-muted-foreground">
                                                    {{ t(tx.account.type_label_key) }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 tabular-nums">
                                        <span
                                            :class="
                                                cn(
                                                    'font-medium',
                                                    toNumber(tx.amount) >= 0
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-rose-600 dark:text-rose-400',
                                                )
                                            "
                                        >
                                            {{ toNumber(tx.amount) >= 0 ? '+' : '-' }}{{ formatAmount(Math.abs(toNumber(tx.amount))) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <TooltipProvider v-if="tx.account" :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger>
                                                    <Button variant="ghost" size="icon" as-child>
                                                        <Link
                                                            :href="route('transactions.edit', tx.id) + currentSearch"
                                                            :aria-label="t('transactions.index.a11y.edit', { description: tx.description })"
                                                        >
                                                            <Pencil class="h-4 w-4" aria-hidden="true" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ t('actions.edit') }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <span v-else class="text-xs text-muted-foreground">{{ t('transactions.index.readOnly.noActions') }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="md:hidden">
                        <div class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            <div v-for="tx in transactions.data" :key="tx.id" class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">{{ tx.description }}</p>
                                        <p class="mt-1 text-xs tabular-nums text-muted-foreground">{{ formatDateIsoToDots(tx.date) }}</p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">{{ tx.date_relative }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            {{ tx.account?.name ?? t('transactions.index.readOnly.deletedAccount') }}
                                            <span v-if="!tx.account" class="ml-2 rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">
                                                {{ t('transactions.index.readOnly.badge') }}
                                            </span>
                                        </p>
                                        <p v-if="tx.subject" class="mt-1 text-xs text-muted-foreground">{{ tx.subject }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p
                                            class="text-sm font-semibold tabular-nums"
                                            :class="
                                                toNumber(tx.amount) >= 0
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-rose-600 dark:text-rose-400'
                                            "
                                        >
                                            {{ toNumber(tx.amount) >= 0 ? '+' : '-' }}{{ formatAmount(Math.abs(toNumber(tx.amount))) }}
                                        </p>
                                        <div class="mt-2">
                                            <TooltipProvider v-if="tx.account" :delay-duration="0">
                                                <Tooltip>
                                                    <TooltipTrigger>
                                                        <Button variant="ghost" size="icon" as-child>
                                                            <Link :href="route('transactions.edit', tx.id) + currentSearch" :aria-label="t('actions.edit')">
                                                                <Pencil class="h-4 w-4" aria-hidden="true" />
                                                            </Link>
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>{{ t('actions.edit') }}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <div class="hidden flex-wrap items-center justify-between gap-3 sm:flex">
                            <div class="text-xs text-muted-foreground">
                                {{ t('transactions.index.pagination.pageOf', { page: transactions.current_page, pages: transactions.last_page }) }}
                            </div>
                            <nav class="flex flex-wrap items-center gap-1" :aria-label="t('transactions.index.pagination.aria')">
                                <Link
                                    v-for="link in transactions.links"
                                    :key="link.label"
                                    :href="link.url ?? ''"
                                    :preserve-scroll="true"
                                    :class="
                                        cn(
                                            'rounded-md px-3 py-1 text-sm',
                                            link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted',
                                            !link.url ? 'pointer-events-none opacity-50' : '',
                                        )
                                    "
                                >
                                    <span v-html="link.label" />
                                </Link>
                            </nav>
                        </div>

                        <div class="flex items-center justify-between gap-3 sm:hidden">
                            <Button variant="secondary" size="sm" as-child :disabled="transactions.current_page <= 1">
                                <Link :href="transactions.links[0]?.url ?? ''" :preserve-scroll="true">
                                    {{ t('transactions.index.pagination.prev') }}
                                </Link>
                            </Button>
                            <p class="text-xs text-muted-foreground">
                                {{ t('transactions.index.pagination.pageOf', { page: transactions.current_page, pages: transactions.last_page }) }}
                            </p>
                            <Button variant="secondary" size="sm" as-child :disabled="transactions.current_page >= transactions.last_page">
                                <Link :href="transactions.links[transactions.links.length - 1]?.url ?? ''" :preserve-scroll="true">
                                    {{ t('transactions.index.pagination.next') }}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
