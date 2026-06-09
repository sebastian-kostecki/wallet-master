<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import ImportDialog from '@/components/import/ImportDialog.vue';
import ImportFailedRowsBanner, { type ImportFailedRow } from '@/components/import/ImportFailedRowsBanner.vue';
import PaginationBar from '@/components/pagination/PaginationBar.vue';
import DeleteTransactionDialog from '@/components/transactions/modals/DeleteTransactionDialog.vue';
import TransactionsIndexHeaderFilters from '@/components/transactions/TransactionsIndexHeaderFilters.vue';
import TransferCandidatesBanner, { type TransferCandidatePair } from '@/components/transfers/TransferCandidatesBanner.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { track } from '@/lib/telemetry';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowDownLeft,
    ArrowRightLeft,
    ArrowUp,
    ArrowUpDown,
    ArrowUpRight,
    ChevronDown,
    Pencil,
    PiggyBank,
    Plus,
    Scale,
    Trash2,
    Upload,
    Wallet,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type Account = {
    id: number;
    name: string;
    type: string;
    type_label_key: string;
    bank_icon_url: string | null;
    bank?: string;
    currency?: {
        symbol: string | null;
    } | null;
};

type Currency = {
    id: number;
    code: string;
    symbol: string | null;
    precision: number;
};

type Category = {
    id: number;
    name: string;
    type: string;
    icon: string;
    color: string;
};

type TransactionCategory = {
    id: number;
    name: string;
    type: string;
    type_label_key: string;
    icon: string;
    color: string;
};

type Transaction = {
    id: number;
    date: string; // YYYY-MM-DD
    booked_at: string; // YYYY-MM-DD
    date_relative: string;
    amount: string | number;
    type: 'income' | 'expense' | string;
    description: string;
    subject: string | null;
    raw_statement_description: string | null;
    transfer_id: string | null;
    category_id: number | null;
    category: TransactionCategory | null;
    pocket_id: number | null;
    pocket: { id: number; name: string } | null;
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
    links?: PaginatorLink[];
};

type ResourceLinksObject = {
    first?: string | null;
    last?: string | null;
    prev?: string | null;
    next?: string | null;
};

type FlattenedPaginator<T> = {
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

type ResourcePaginator<T> = {
    data: T[];
    links: ResourceLinksObject;
    meta: PaginatorMeta & { links: PaginatorLink[] };
};

type Paginator<T> = FlattenedPaginator<T> | ResourcePaginator<T>;

type Filters = {
    account_id: number | null;
    category_id: number | null;
    from: string | null; // DD-MM-YYYY
    to: string | null; // DD-MM-YYYY
    sort: 'date' | 'amount' | string;
    direction: 'asc' | 'desc' | string;
    per_page?: number;
};

const props = defineProps<{
    accounts: Account[];
    categories: Category[];
    filters: Filters;
    transactions: Paginator<Transaction>;
    summary: {
        total_income: string | number;
        total_expense: string | number;
    };
    unresolved_import_failed_rows?: ImportFailedRow[];
    pending_transfer_candidates?: TransferCandidatePair[];
}>();

const page = usePage<{ errors?: Record<string, string> }>();
const { t, locale } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('transactions.index.title'),
        href: route('transactions.index'),
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

function formatCurrencySymbol(account: Account | null): string {
    return account?.currency?.symbol ?? t('currency.defaultSymbol');
}

function normalizeDateIso(input: string): string {
    const trimmed = input.trim();
    const match = trimmed.match(/^(\d{4}-\d{2}-\d{2})/);

    return match?.[1] ?? trimmed;
}

function formatDateIsoToDots(input: string): string {
    const iso = normalizeDateIso(input);
    const parts = iso.split('-');
    if (parts.length !== 3) {
        return input;
    }

    const [yyyy, mm, dd] = parts;
    if (!yyyy || !mm || !dd) {
        return input;
    }

    return `${dd}.${mm}.${yyyy}`;
}

function transactionDisplayDateIso(tx: Transaction): string {
    const booked = normalizeDateIso(tx.booked_at ?? '');
    if (booked !== '') {
        return booked;
    }

    return normalizeDateIso(tx.date);
}

/** Relative label for the displayed date (`booked_at ?? date`), aligned with UI locale */
function operationDateRelative(dateIso: string): string {
    const iso = normalizeDateIso(dateIso);
    const parts = iso.split('-');
    if (parts.length !== 3) {
        return '';
    }

    const y = Number(parts[0]);
    const m = Number(parts[1]);
    const d = Number(parts[2]);
    if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) {
        return '';
    }

    const target = new Date(y, m - 1, d);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    target.setHours(0, 0, 0, 0);
    const diffDays = Math.round((target.getTime() - today.getTime()) / 86400000);

    const lng = locale.value === 'pl' ? 'pl' : 'en';
    const rtf = new Intl.RelativeTimeFormat(lng, { numeric: 'auto' });

    return rtf.format(diffDays, 'day');
}

type TransactionIconVariant = 'internal' | 'expense' | 'income' | 'adjustment';

function transactionVariant(tx: Transaction): TransactionIconVariant {
    if (tx.type === 'adjustment') {
        return 'adjustment';
    }

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

    if (variant === 'adjustment') {
        return {
            component: Scale,
            containerClass: 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400',
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

const { currentSearch, transactionsIndexSearch } = useTransactionsIndexSearch();

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

    track('transactions_sorted', { sort, direction: nextDirection });

    router.get(
        route('transactions.index'),
        {
            account_id: props.filters.account_id ?? undefined,
            category_id: props.filters.category_id ?? undefined,
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
    return Boolean(
        props.filters.account_id !== null ||
            props.filters.category_id !== null ||
            (props.filters.from ?? '').trim() !== '' ||
            (props.filters.to ?? '').trim() !== '',
    );
});

const transactionTotal = computed(() => {
    const paginator = props.transactions as FlattenedPaginator<Transaction> | ResourcePaginator<Transaction>;

    if ('total' in paginator && typeof paginator.total === 'number') {
        return paginator.total;
    }

    return paginator.meta?.total ?? 0;
});

const isFirstUseEmpty = computed(() => transactionTotal.value === 0 && !hasActiveFilters.value);
const isFilteredEmpty = computed(() => transactionTotal.value === 0 && hasActiveFilters.value);

function directionIcon() {
    const currentSort = props.filters.sort ?? 'date';
    const currentDirection = props.filters.direction ?? (currentSort === 'date' ? 'desc' : 'asc');
    return currentDirection === 'asc' ? ArrowUp : ArrowDown;
}

const summaryIncome = computed(() => formatAmount(props.summary.total_income));
const summaryExpense = computed(() => formatAmount(Math.abs(toNumber(props.summary.total_expense))));

const selectedAccount = computed(() => {
    const accountId = props.filters.account_id;
    if (accountId === null) {
        return null;
    }

    return props.accounts.find((a) => a.id === accountId) ?? null;
});

const summaryCurrencySymbol = computed(() => {
    if (props.filters.account_id === null) {
        return t('currency.defaultSymbol');
    }

    return formatCurrencySymbol(selectedAccount.value);
});

const serverErrors = computed<Record<string, string>>(() => page.props.errors ?? {});

const importDialogOpen = ref(false);

function hasRawStatementDescription(tx: Transaction): boolean {
    return (tx.raw_statement_description ?? '').trim() !== '';
}

function truncateText(input: string | null | undefined, maxLength: number): { text: string; isTruncated: boolean } {
    const value = (input ?? '').trim();
    if (value === '') {
        return { text: '—', isTruncated: false };
    }

    if (value.length <= maxLength) {
        return { text: value, isTruncated: false };
    }

    const slice = value.slice(0, Math.max(0, maxLength));
    const lastSpace = slice.lastIndexOf(' ');
    const trimmed = (lastSpace >= Math.floor(maxLength * 0.6) ? slice.slice(0, lastSpace) : slice).trimEnd();

    return { text: `${trimmed}…`, isTruncated: true };
}

const deletingTransactionId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const deleteProcessing = ref(false);
const deletingTransaction = computed(() => props.transactions.data.find((t) => t.id === deletingTransactionId.value) ?? null);

function openDeleteDialog(transactionId: number) {
    deletingTransactionId.value = transactionId;
    deleteDialogOpen.value = true;
}

function ariaSortFor(column: 'date' | 'amount'): 'ascending' | 'descending' | 'none' {
    const currentSort = props.filters.sort ?? 'date';
    if (currentSort !== column) {
        return 'none';
    }

    const currentDirection = props.filters.direction ?? (currentSort === 'date' ? 'desc' : 'asc');

    return currentDirection === 'asc' ? 'ascending' : 'descending';
}

function sortButtonAriaLabel(column: 'date' | 'amount'): string {
    const isActive = (props.filters.sort ?? 'date') === column;
    const columnLabel = column === 'date' ? t('transactions.index.sort.date') : t('transactions.index.sort.amount');

    if (!isActive) {
        return t('transactions.index.a11y.sortInactive', { column: columnLabel });
    }

    const direction =
        (props.filters.direction ?? (column === 'date' ? 'desc' : 'asc')) === 'asc'
            ? t('transactions.index.sort.asc')
            : t('transactions.index.sort.desc');

    return t('transactions.index.a11y.sortActive', { column: columnLabel, direction });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('transactions.index.title')" />

        <template #headerActions>
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                <TransactionsIndexHeaderFilters
                    :accounts="accounts"
                    :categories="categories"
                    :filters="filters"
                    :server-errors="serverErrors"
                    :is-loading="isLoading"
                />
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button class="sm:shrink-0">
                            <Plus class="h-4 w-4" aria-hidden="true" />
                            {{ t('transactions.index.actionsMenu.label') }}
                            <ChevronDown class="ml-1 h-4 w-4" aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuItem as-child>
                            <Link :href="route('transactions.create') + currentSearch">
                                <Plus />
                                {{ t('transactions.index.addTransaction') }}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link :href="route('transfers.create') + currentSearch">
                                <ArrowRightLeft />
                                {{ t('transactions.index.addTransfer') }}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="importDialogOpen = true">
                            <Upload />
                            {{ t('imports.cta') }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <ImportFailedRowsBanner
                v-if="(unresolved_import_failed_rows ?? []).length > 0"
                :rows="unresolved_import_failed_rows ?? []"
                :accounts="accounts"
                :account-filter-id="filters.account_id"
            />

            <TransferCandidatesBanner
                v-if="(pending_transfer_candidates ?? []).length > 0"
                :pairs="pending_transfer_candidates ?? []"
                :accounts="accounts"
                :account-filter-id="filters.account_id"
            />

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">{{ t('transactions.index.summary.income') }}</p>
                    <div class="mt-2">
                        <Skeleton v-if="isLoading" class="h-8 w-40" />
                        <p v-else class="text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                            +{{ summaryIncome }} {{ summaryCurrencySymbol }}
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">{{ t('transactions.index.summary.expense') }}</p>
                    <div class="mt-2">
                        <Skeleton v-if="isLoading" class="h-8 w-40" />
                        <p v-else class="text-2xl font-semibold tabular-nums text-rose-600 dark:text-rose-400">
                            -{{ summaryExpense }} {{ summaryCurrencySymbol }}
                        </p>
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
                        <table class="w-full table-fixed text-sm">
                            <caption class="sr-only">
                                {{
                                    t('transactions.index.a11y.tableCaption')
                                }}
                            </caption>
                            <thead class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground dark:border-sidebar-border">
                                <tr>
                                    <th class="w-36 px-6 py-3" scope="col" :aria-sort="ariaSortFor('date')">
                                        <button
                                            class="inline-flex items-center gap-2 hover:text-foreground"
                                            type="button"
                                            :aria-label="sortButtonAriaLabel('date')"
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
                                    <th class="px-6 py-3" scope="col">{{ t('transactions.index.table.description') }}</th>
                                    <th class="w-56 px-6 py-3" scope="col">{{ t('transactions.index.table.category') }}</th>
                                    <th class="w-64 px-6 py-3" scope="col">{{ t('transactions.index.table.account') }}</th>
                                    <th class="w-44 px-6 py-3" scope="col" :aria-sort="ariaSortFor('amount')">
                                        <button
                                            class="inline-flex items-center gap-2 hover:text-foreground"
                                            type="button"
                                            :aria-label="sortButtonAriaLabel('amount')"
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
                                    <th class="w-28 px-6 py-3 text-right" scope="col">{{ t('transactions.index.table.actions') }}</th>
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
                                            {{ formatDateIsoToDots(transactionDisplayDateIso(tx)) }}
                                        </div>
                                        <div class="mt-0.5 text-xs text-muted-foreground">
                                            {{ tx.date_relative || operationDateRelative(transactionDisplayDateIso(tx)) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <TooltipProvider v-if="hasRawStatementDescription(tx)" :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <div
                                                        class="flex min-w-0 cursor-pointer items-center gap-3"
                                                        :aria-label="t('transactions.index.a11y.showStatementDescription')"
                                                    >
                                                        <div
                                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                                            :class="transactionIcon(tx).containerClass"
                                                            aria-hidden="true"
                                                        >
                                                            <component :is="transactionIcon(tx).component" class="h-4 w-4" />
                                                        </div>

                                                        <div class="w-full min-w-0">
                                                            <div v-if="tx.type === 'adjustment'" class="mb-1">
                                                                <span
                                                                    class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-400"
                                                                >
                                                                    {{ t('transactions.index.badges.adjustment') }}
                                                                </span>
                                                            </div>
                                                            <p class="truncate text-sm font-medium text-foreground">
                                                                {{ truncateText(tx.subject, 80).text }}
                                                            </p>
                                                            <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                                                {{ truncateText(tx.description, 120).text }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <div class="space-y-3">
                                                        <div>
                                                            <p class="text-xs font-medium text-muted-foreground">
                                                                {{ t('transactions.edit.statement.title') }}
                                                            </p>
                                                            <p class="mt-1 max-w-md whitespace-pre-wrap break-words">
                                                                {{ tx.raw_statement_description }}
                                                            </p>
                                                        </div>
                                                        <div v-if="truncateText(tx.subject, 80).isTruncated && (tx.subject ?? '').trim() !== ''">
                                                            <p class="text-xs font-medium text-muted-foreground">
                                                                {{ t('transactions.index.table.subject') }}
                                                            </p>
                                                            <p class="mt-1 max-w-md break-words">{{ tx.subject }}</p>
                                                        </div>
                                                        <div v-if="truncateText(tx.description, 120).isTruncated">
                                                            <p class="text-xs font-medium text-muted-foreground">
                                                                {{ t('transactions.index.table.description') }}
                                                            </p>
                                                            <p class="mt-1 max-w-md break-words">{{ tx.description }}</p>
                                                        </div>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>

                                        <div v-else class="flex min-w-0 items-center gap-3">
                                            <div
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                                :class="transactionIcon(tx).containerClass"
                                                aria-hidden="true"
                                            >
                                                <component :is="transactionIcon(tx).component" class="h-4 w-4" />
                                            </div>

                                            <div class="w-full min-w-0">
                                                <div v-if="tx.type === 'adjustment'" class="mb-1">
                                                    <span
                                                        class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-400"
                                                    >
                                                        {{ t('transactions.index.badges.adjustment') }}
                                                    </span>
                                                </div>
                                                <TooltipProvider :delay-duration="0">
                                                    <Tooltip v-if="truncateText(tx.subject, 80).isTruncated">
                                                        <TooltipTrigger as-child>
                                                            <p class="truncate text-sm font-medium text-foreground" :title="tx.subject ?? undefined">
                                                                {{ truncateText(tx.subject, 80).text }}
                                                            </p>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p class="max-w-sm break-words">{{ tx.subject }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    <p v-else class="truncate text-sm font-medium text-foreground" :title="tx.subject ?? undefined">
                                                        {{ truncateText(tx.subject, 80).text }}
                                                    </p>
                                                </TooltipProvider>

                                                <TooltipProvider :delay-duration="0">
                                                    <Tooltip v-if="truncateText(tx.description, 120).isTruncated">
                                                        <TooltipTrigger as-child>
                                                            <p class="mt-0.5 truncate text-xs text-muted-foreground" :title="tx.description">
                                                                {{ truncateText(tx.description, 120).text }}
                                                            </p>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p class="max-w-sm break-words">{{ tx.description }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    <p v-else class="mt-0.5 truncate text-xs text-muted-foreground" :title="tx.description">
                                                        {{ truncateText(tx.description, 120).text }}
                                                    </p>
                                                </TooltipProvider>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="overflow-hidden px-6 py-4">
                                        <div v-if="tx.transfer_id" class="flex flex-col gap-0.5">
                                            <span class="text-sm text-muted-foreground">
                                                {{ t('transactions.index.table.transfer') }}
                                            </span>
                                            <span v-if="tx.pocket" class="text-xs text-muted-foreground">
                                                {{ tx.pocket.name }}
                                            </span>
                                        </div>
                                        <div v-else-if="tx.category" class="min-w-0 max-w-full">
                                            <CategoryBadge
                                                :name="tx.category.name"
                                                :icon="tx.category.icon"
                                                :color="tx.category.color"
                                                size="md"
                                            />
                                        </div>
                                        <span v-else class="text-sm text-muted-foreground">—</span>
                                    </td>
                                    <td class="overflow-hidden px-6 py-4">
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
                                                    <span v-if="!tx.account" class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
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
                                            {{ formatCurrencySymbol(tx.account) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <TooltipProvider v-if="tx.account" :delay-duration="0">
                                            <div class="inline-flex w-full items-center justify-end gap-1">
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

                                                <Tooltip>
                                                    <TooltipTrigger>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            type="button"
                                                            :disabled="deleteProcessing"
                                                            :aria-label="t('transactions.delete.iconLabel', { description: tx.description })"
                                                            @click="openDeleteDialog(tx.id)"
                                                        >
                                                            <Trash2 class="h-4 w-4" aria-hidden="true" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>{{ t('transactions.delete.action') }}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
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
                                        <div v-if="tx.type === 'adjustment'" class="mb-1">
                                            <span
                                                class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-400"
                                            >
                                                {{ t('transactions.index.badges.adjustment') }}
                                            </span>
                                        </div>

                                        <TooltipProvider v-if="hasRawStatementDescription(tx)" :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <div class="cursor-pointer" :aria-label="t('transactions.index.a11y.showStatementDescription')">
                                                        <p class="text-sm font-medium">
                                                            {{ truncateText(tx.description, 90).text }}
                                                        </p>
                                                        <p v-if="(tx.subject ?? '').trim() !== ''" class="mt-1 text-xs text-muted-foreground">
                                                            {{ truncateText(tx.subject, 70).text }}
                                                        </p>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <div class="space-y-3">
                                                        <div>
                                                            <p class="text-xs font-medium text-muted-foreground">
                                                                {{ t('transactions.edit.statement.title') }}
                                                            </p>
                                                            <p class="mt-1 max-w-md whitespace-pre-wrap break-words">
                                                                {{ tx.raw_statement_description }}
                                                            </p>
                                                        </div>
                                                        <div v-if="truncateText(tx.subject, 70).isTruncated && (tx.subject ?? '').trim() !== ''">
                                                            <p class="text-xs font-medium text-muted-foreground">
                                                                {{ t('transactions.index.table.subject') }}
                                                            </p>
                                                            <p class="mt-1 max-w-md break-words">{{ tx.subject }}</p>
                                                        </div>
                                                        <div v-if="truncateText(tx.description, 90).isTruncated">
                                                            <p class="text-xs font-medium text-muted-foreground">
                                                                {{ t('transactions.index.table.description') }}
                                                            </p>
                                                            <p class="mt-1 max-w-md break-words">{{ tx.description }}</p>
                                                        </div>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>

                                        <template v-else>
                                            <TooltipProvider :delay-duration="0">
                                                <Tooltip v-if="truncateText(tx.description, 90).isTruncated">
                                                    <TooltipTrigger as-child>
                                                        <p class="text-sm font-medium" :title="tx.description">
                                                            {{ truncateText(tx.description, 90).text }}
                                                        </p>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p class="max-w-sm break-words">{{ tx.description }}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                                <p v-else class="text-sm font-medium" :title="tx.description">
                                                    {{ truncateText(tx.description, 90).text }}
                                                </p>
                                            </TooltipProvider>
                                        </template>

                                        <p class="mt-1 text-xs tabular-nums text-muted-foreground">
                                            {{ formatDateIsoToDots(transactionDisplayDateIso(tx)) }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">
                                            {{ tx.date_relative || operationDateRelative(transactionDisplayDateIso(tx)) }}
                                        </p>
                                        <div v-if="tx.category" class="mt-1">
                                            <CategoryBadge :name="tx.category.name" :icon="tx.category.icon" :color="tx.category.color" size="sm" />
                                        </div>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            {{ tx.account?.name ?? t('transactions.index.readOnly.deletedAccount') }}
                                            <span v-if="!tx.account" class="ml-2 rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">
                                                {{ t('transactions.index.readOnly.badge') }}
                                            </span>
                                        </p>

                                        <TooltipProvider v-if="!hasRawStatementDescription(tx) && tx.subject" :delay-duration="0">
                                            <Tooltip v-if="truncateText(tx.subject, 70).isTruncated">
                                                <TooltipTrigger as-child>
                                                    <p class="mt-1 text-xs text-muted-foreground" :title="tx.subject ?? undefined">
                                                        {{ truncateText(tx.subject, 70).text }}
                                                    </p>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p class="max-w-sm break-words">{{ tx.subject }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                            <p v-else class="mt-1 text-xs text-muted-foreground" :title="tx.subject ?? undefined">
                                                {{ truncateText(tx.subject, 70).text }}
                                            </p>
                                        </TooltipProvider>
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
                                            {{ formatCurrencySymbol(tx.account) }}
                                        </p>
                                        <div class="mt-2">
                                            <TooltipProvider v-if="tx.account" :delay-duration="0">
                                                <div class="inline-flex items-center gap-1">
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

                                                    <Tooltip>
                                                        <TooltipTrigger>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                type="button"
                                                                :disabled="deleteProcessing"
                                                                :aria-label="t('transactions.delete.iconLabel', { description: tx.description })"
                                                                @click="openDeleteDialog(tx.id)"
                                                            >
                                                                <Trash2 class="h-4 w-4" aria-hidden="true" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>{{ t('transactions.delete.action') }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </div>
                                            </TooltipProvider>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <PaginationBar
                            :paginator="transactions"
                            :query="{
                                account_id: filters.account_id ?? undefined,
                                category_id: filters.category_id ?? undefined,
                                from: filters.from ?? undefined,
                                to: filters.to ?? undefined,
                                sort: filters.sort ?? 'date',
                                direction: filters.direction ?? 'desc',
                                per_page: filters.per_page ?? undefined,
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <ImportDialog
            v-model:open="importDialogOpen"
            :accounts="accounts as any"
            :preselected-account-id="filters.account_id ?? null"
            :disabled="isLoading"
            :current-search="currentSearch"
        />

        <DeleteTransactionDialog
            v-model:open="deleteDialogOpen"
            :transaction-id="deletingTransactionId"
            :description="deletingTransaction?.description ?? null"
            :is-transfer="Boolean(deletingTransaction?.transfer_id)"
            :disabled="deleteProcessing"
            :return-search="transactionsIndexSearch"
            @processing="(value: any) => (deleteProcessing = value)"
            @success="deletingTransactionId = null"
        />
    </AppLayout>
</template>
