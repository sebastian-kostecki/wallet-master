<script setup lang="ts">
import ImportDialog from '@/components/import/ImportDialog.vue';
import ImportFailedRowsBanner, { type ImportFailedRow } from '@/components/import/ImportFailedRowsBanner.vue';
import TablePage from '@/components/page/TablePage.vue';
import DeleteTransactionDialog from '@/components/transactions/modals/DeleteTransactionDialog.vue';
import TransactionFormDialog from '@/components/transactions/modals/TransactionFormDialog.vue';
import TransactionRow from '@/components/transactions/TransactionRow.vue';
import TransactionsIndexHeaderFilters from '@/components/transactions/TransactionsIndexHeaderFilters.vue';
import TransferCandidatesBanner, { type TransferCandidatePair } from '@/components/transfers/TransferCandidatesBanner.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { useTransactionFormDialog } from '@/composables/useTransactionFormDialog';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { track } from '@/lib/telemetry';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowRightLeft, ArrowUp, ArrowUpDown, ChevronDown, Plus, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
    sort_order: number;
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
    account_id: number;
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
    pockets: { id: number; name: string }[];
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

const deletingTransactionId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const deleteProcessing = ref(false);
const deletingTransaction = computed(() => props.transactions.data.find((t) => t.id === deletingTransactionId.value) ?? null);

function openDeleteDialog(transactionId: number) {
    deletingTransactionId.value = transactionId;
    deleteDialogOpen.value = true;
}

const { createRequested } = useTransactionFormDialog();

const formDialogOpen = ref(false);
const formDialogMode = ref<'create' | 'edit'>('create');
const formResetKey = ref(0);
const editingTransactionId = ref<number | null>(null);

const editingTransaction = computed(() => props.transactions.data.find((tx) => tx.id === editingTransactionId.value) ?? null);

function openCreateDialog(): void {
    const alreadyOpenCreate = formDialogOpen.value && formDialogMode.value === 'create';

    formDialogMode.value = 'create';
    editingTransactionId.value = null;

    if (alreadyOpenCreate) {
        formResetKey.value++;
    }

    formDialogOpen.value = true;
}

function openEditDialog(tx: Transaction): void {
    formDialogMode.value = 'edit';
    editingTransactionId.value = tx.id;
    formDialogOpen.value = true;
}

function consumeNewQuery(): void {
    const [path, query = ''] = page.url.split('?');
    const params = new URLSearchParams(query);

    if (params.get('new') !== '1') {
        return;
    }

    openCreateDialog();
    params.delete('new');
    const next = params.toString() === '' ? path : `${path}?${params.toString()}`;
    router.replace(next, { preserveState: true, preserveScroll: true });
}

watch(createRequested, (requested) => {
    if (!requested) {
        return;
    }

    openCreateDialog();
    createRequested.value = false;
});

watch(editingTransaction, (tx) => {
    if (formDialogOpen.value && formDialogMode.value === 'edit' && tx === null) {
        formDialogOpen.value = false;
    }
});

onMounted(() => {
    consumeNewQuery();
});

watch(
    () => page.url,
    () => {
        consumeNewQuery();
    },
);

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
    <AppLayout>
        <template #headerActions>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button class="sm:shrink-0">
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        {{ t('transactions.index.actionsMenu.label') }}
                        <ChevronDown class="ml-1 h-4 w-4" aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56 rounded-2xl">
                    <DropdownMenuItem @select="openCreateDialog">
                        <Plus />
                        {{ t('transactions.index.addTransaction') }}
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
        </template>

        <div class="flex flex-col gap-6">
            <TransactionsIndexHeaderFilters
                :accounts="accounts"
                :categories="categories"
                :filters="filters"
                :server-errors="serverErrors"
                :is-loading="isLoading"
            />

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
                <div class="rounded-2xl border border-transparent bg-card p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                        {{ t('transactions.index.summary.income') }}
                    </p>
                    <div class="mt-2">
                        <Skeleton v-if="isLoading" class="h-8 w-40" />
                        <p v-else class="text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                            +{{ summaryIncome }} {{ summaryCurrencySymbol }}
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-transparent bg-card p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                        {{ t('transactions.index.summary.expense') }}
                    </p>
                    <div class="mt-2">
                        <Skeleton v-if="isLoading" class="h-8 w-40" />
                        <p v-else class="text-2xl font-semibold tabular-nums text-red-600 dark:text-red-400">
                            -{{ summaryExpense }} {{ summaryCurrencySymbol }}
                        </p>
                    </div>
                </div>
            </div>

            <div :aria-busy="isLoading ? 'true' : 'false'">
                <div v-if="isLoading" class="overflow-hidden rounded-2xl border border-transparent bg-card p-5">
                    <div class="grid gap-3">
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                        <Skeleton class="h-10 w-full" />
                    </div>
                </div>

                <TablePage
                    v-else
                    :is-empty="isFilteredEmpty || isFirstUseEmpty"
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
                >
                    <template #empty>
                        <section
                            v-if="isFilteredEmpty"
                            role="region"
                            aria-labelledby="transactions-empty-heading"
                            class="flex flex-col items-center py-16 text-center"
                        >
                            <h2 id="transactions-empty-heading" class="text-2xl font-medium tracking-tight">
                                {{ t('transactions.index.empty.filtered.heading') }}
                            </h2>
                            <p class="mt-2 max-w-sm text-sm text-muted-foreground">
                                {{ t('transactions.index.empty.filtered.message') }}
                            </p>
                            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Button type="button" @click="openCreateDialog">{{ t('transactions.index.addTransaction') }}</Button>
                                <Button variant="ghost" type="button" @click="clearFilters">{{ t('transactions.index.filters.clear') }}</Button>
                            </div>
                        </section>

                        <section
                            v-else
                            role="region"
                            aria-labelledby="transactions-empty-heading"
                            class="flex flex-col items-center py-16 text-center"
                        >
                            <h2 id="transactions-empty-heading" class="text-2xl font-medium tracking-tight">
                                {{ t('transactions.index.empty.firstUse.heading') }}
                            </h2>
                            <p class="mt-2 max-w-sm text-sm text-muted-foreground">
                                {{ t('transactions.index.empty.firstUse.message') }}
                            </p>
                            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Button type="button" @click="openCreateDialog">{{ t('transactions.index.addTransaction') }}</Button>
                                <Button v-if="hasImportRoute" variant="ghost" as-child>
                                    <Link :href="route('imports.index')">{{ t('transactions.index.empty.import') }}</Link>
                                </Button>
                            </div>
                        </section>
                    </template>

                    <div class="overflow-hidden rounded-2xl border border-transparent bg-card">
                        <div class="hidden md:block">
                            <table class="w-full table-fixed text-sm">
                                <caption class="sr-only">
                                    {{
                                        t('transactions.index.a11y.tableCaption')
                                    }}
                                </caption>
                                <thead
                                    class="border-b border-border/50 text-left text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60"
                                >
                                    <tr>
                                        <th class="w-36 px-3 py-2" scope="col" :aria-sort="ariaSortFor('date')">
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
                                        <th class="px-3 py-2" scope="col">{{ t('transactions.index.table.description') }}</th>
                                        <th class="w-56 px-3 py-2 xl:w-64 2xl:w-72" scope="col">{{ t('transactions.index.table.category') }}</th>
                                        <th class="w-64 px-3 py-2" scope="col">{{ t('transactions.index.table.account') }}</th>
                                        <th class="w-44 px-3 py-2" scope="col" :aria-sort="ariaSortFor('amount')">
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
                                        <th class="w-28 px-3 py-2 text-right" scope="col">{{ t('transactions.index.table.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <TransactionRow
                                        v-for="tx in transactions.data"
                                        :key="tx.id"
                                        :transaction="tx"
                                        variant="desktop"
                                        :delete-processing="deleteProcessing"
                                        :format-amount="formatAmount"
                                        :format-currency-symbol="formatCurrencySymbol"
                                        @edit="openEditDialog"
                                        @delete="openDeleteDialog"
                                    />
                                </tbody>
                            </table>
                        </div>

                        <div class="md:hidden">
                            <TransactionRow
                                v-for="tx in transactions.data"
                                :key="tx.id"
                                :transaction="tx"
                                variant="mobile"
                                :delete-processing="deleteProcessing"
                                :format-amount="formatAmount"
                                :format-currency-symbol="formatCurrencySymbol"
                                @edit="openEditDialog"
                                @delete="openDeleteDialog"
                            />
                        </div>
                    </div>
                </TablePage>
            </div>
        </div>

        <ImportDialog
            v-model:open="importDialogOpen"
            :accounts="accounts as any"
            :preselected-account-id="filters.account_id ?? null"
            :disabled="isLoading"
            :current-search="currentSearch"
        />

        <TransactionFormDialog
            v-model:open="formDialogOpen"
            :mode="formDialogMode"
            :form-reset-key="formResetKey"
            :transaction="editingTransaction"
            :accounts="accounts"
            :categories="categories"
            :pockets="pockets"
            :account-id-filter="filters.account_id"
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
