<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
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
    amount: string | number;
    type: 'income' | 'expense' | string;
    description: string;
    subject: string | null;
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
    meta: PaginatorMeta;
};

type Filters = {
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

const page = usePage<{ errors: Record<string, string> }>();
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

function formatDateIsoToPl(input: string): string {
    const parts = input.split('-');
    if (parts.length !== 3) {
        return input;
    }

    const [yyyy, mm, dd] = parts;
    if (!yyyy || !mm || !dd) {
        return input;
    }

    return `${dd}-${mm}-${yyyy}`;
}

const currentSearch = computed(() => {
    const url = (usePage() as any).url as string;
    const idx = url.indexOf('?');
    return idx >= 0 ? url.slice(idx) : '';
});

const hasImportRoute = computed(() => {
    const r = route as any;
    return typeof r?.has === 'function' ? Boolean(r.has('imports.index')) : false;
});

const accountOptions = computed<DropdownOption<number | null>[]>(() => [
    { value: null, label: t('transactions.index.filters.account.all') },
    ...props.accounts.map((a) => ({ value: a.id, label: a.name })),
]);

const sortOptions = computed<DropdownOption<string>[]>(() => [
    { value: 'date', label: t('transactions.index.sort.date') },
    { value: 'amount', label: t('transactions.index.sort.amount') },
]);

const directionOptions = computed<DropdownOption<string>[]>(() => [
    { value: 'desc', label: t('transactions.index.sort.desc') },
    { value: 'asc', label: t('transactions.index.sort.asc') },
]);

const localAccountId = ref<number | null>(props.filters.account_id ?? null);
const localFrom = ref<string>(props.filters.from ?? '');
const localTo = ref<string>(props.filters.to ?? '');
const localSort = ref<string>(props.filters.sort ?? 'date');
const localDirection = ref<string>(props.filters.direction ?? (localSort.value === 'date' ? 'desc' : 'asc'));

watch(
    () => props.filters,
    (next) => {
        localAccountId.value = next.account_id ?? null;
        localFrom.value = next.from ?? '';
        localTo.value = next.to ?? '';
        localSort.value = next.sort ?? 'date';
        localDirection.value = next.direction ?? (localSort.value === 'date' ? 'desc' : 'asc');
    },
    { deep: true },
);

const localErrors = ref<Record<string, string>>({});

function isValidDateInput(value: string): boolean {
    if (value.trim() === '') {
        return true;
    }

    if (!/^\d{2}-\d{2}-\d{4}$/.test(value)) {
        return false;
    }

    const [dd, mm, yyyy] = value.split('-').map((p) => Number(p));
    if (!dd || !mm || !yyyy) {
        return false;
    }

    const d = new Date(Date.UTC(yyyy, mm - 1, dd));
    return d.getUTCFullYear() === yyyy && d.getUTCMonth() === mm - 1 && d.getUTCDate() === dd;
}

function compareDatesDdMmYyyy(a: string, b: string): number {
    const [add, amm, ayyyy] = a.split('-').map((p) => Number(p));
    const [bdd, bmm, byyyy] = b.split('-').map((p) => Number(p));

    const ax = Date.UTC(ayyyy, amm - 1, add);
    const bx = Date.UTC(byyyy, bmm - 1, bdd);

    return ax - bx;
}

function buildQuery() {
    const query: Record<string, any> = {
        account_id: localAccountId.value ?? undefined,
        from: localFrom.value.trim() || undefined,
        to: localTo.value.trim() || undefined,
        sort: localSort.value || undefined,
        direction: localDirection.value || undefined,
    };

    return query;
}

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

function applyFilters() {
    localErrors.value = {};

    if (!isValidDateInput(localFrom.value)) {
        localErrors.value.from = t('transactions.index.filters.errors.dateFormat');
    }

    if (!isValidDateInput(localTo.value)) {
        localErrors.value.to = t('transactions.index.filters.errors.dateFormat');
    }

    if (localErrors.value.from || localErrors.value.to) {
        return;
    }

    if (localFrom.value.trim() !== '' && localTo.value.trim() !== '' && compareDatesDdMmYyyy(localFrom.value, localTo.value) > 0) {
        localErrors.value.from = t('transactions.index.filters.errors.dateRange');
        return;
    }

    router.get(route('transactions.index'), buildQuery(), {
        preserveScroll: true,
        replace: true,
        preserveState: 'errors',
    });
}

function clearFilters() {
    localErrors.value = {};
    localAccountId.value = null;
    localFrom.value = '';
    localTo.value = '';
    localSort.value = 'date';
    localDirection.value = 'desc';

    router.get(route('transactions.index'), { sort: 'date', direction: 'desc' }, { preserveScroll: true, replace: true });
}

function setSort(sort: 'date' | 'amount') {
    const wasSame = localSort.value === sort;
    localSort.value = sort;
    localDirection.value = wasSame ? (localDirection.value === 'asc' ? 'desc' : 'asc') : sort === 'date' ? 'desc' : 'asc';
    applyFilters();
}

const hasActiveFilters = computed(() => {
    return Boolean(localAccountId.value !== null || localFrom.value.trim() !== '' || localTo.value.trim() !== '');
});

const isFirstUseEmpty = computed(() => props.transactions.meta?.total === 0 && !hasActiveFilters.value);
const isFilteredEmpty = computed(() => props.transactions.meta?.total === 0 && hasActiveFilters.value);

function directionIcon() {
    return localDirection.value === 'asc' ? ArrowUp : ArrowDown;
}

const summaryIncome = computed(() => formatAmount(props.summary.total_income));
const summaryExpense = computed(() => formatAmount(props.summary.total_expense));

const serverErrors = computed(() => (page.props as any).errors as Record<string, string>);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('transactions.index.title')" />

        <template #headerActions>
            <Button as-child>
                <Link :href="route('transactions.create') + currentSearch">{{ t('transactions.index.addTransaction') }}</Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <div class="flex flex-col gap-4 md:flex-row md:items-end">
                    <div class="grid flex-1 gap-4 md:grid-cols-3">
                        <FormField for-id="account_id" :label="t('transactions.index.filters.account.label')" :error="serverErrors.account_id">
                            <DropdownSelect
                                id="account_id"
                                :model-value="localAccountId"
                                :options="accountOptions"
                                :placeholder="t('transactions.index.filters.account.all')"
                                :disabled="isLoading"
                                @update:model-value="(value) => (localAccountId = value)"
                            />
                        </FormField>

                        <FormField
                            for-id="from"
                            :label="t('transactions.index.filters.from')"
                            :error="localErrors.from ?? serverErrors.from"
                        >
                            <Input
                                id="from"
                                v-model="localFrom"
                                inputmode="numeric"
                                placeholder="DD-MM-YYYY"
                                :disabled="isLoading"
                                @blur="applyFilters"
                            />
                        </FormField>

                        <FormField for-id="to" :label="t('transactions.index.filters.to')" :error="localErrors.to ?? serverErrors.to">
                            <Input
                                id="to"
                                v-model="localTo"
                                inputmode="numeric"
                                placeholder="DD-MM-YYYY"
                                :disabled="isLoading"
                                @blur="applyFilters"
                            />
                        </FormField>
                    </div>

                    <div class="flex flex-col gap-2 md:flex-row md:items-center">
                        <div class="grid gap-4 md:grid-cols-2">
                            <FormField for-id="sort" :label="t('transactions.index.filters.sort')">
                                <DropdownSelect
                                    id="sort"
                                    :model-value="localSort"
                                    :options="sortOptions"
                                    :disabled="isLoading"
                                    @update:model-value="(value) => ((localSort = value), applyFilters())"
                                />
                            </FormField>

                            <FormField for-id="direction" :label="t('transactions.index.filters.direction')">
                                <DropdownSelect
                                    id="direction"
                                    :model-value="localDirection"
                                    :options="directionOptions"
                                    :disabled="isLoading"
                                    @update:model-value="(value) => ((localDirection = value), applyFilters())"
                                />
                            </FormField>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button variant="secondary" :disabled="isLoading" @click="applyFilters">
                                {{ t('transactions.index.filters.apply') }}
                            </Button>
                            <Button variant="ghost" :disabled="isLoading" @click="clearFilters">
                                {{ t('transactions.index.filters.clear') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div v-if="(localErrors.from ?? serverErrors.from) || (localErrors.to ?? serverErrors.to)" class="mt-3">
                    <InputError :message="localErrors.from ?? serverErrors.from ?? localErrors.to ?? serverErrors.to" />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">{{ t('transactions.index.summary.income') }}</p>
                    <div class="mt-2">
                        <Skeleton v-if="isLoading" class="h-8 w-40" />
                        <p v-else class="text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                            +{{ summaryIncome }}
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">{{ t('transactions.index.summary.expense') }}</p>
                    <div class="mt-2">
                        <Skeleton v-if="isLoading" class="h-8 w-40" />
                        <p v-else class="text-2xl font-semibold tabular-nums text-rose-600 dark:text-rose-400">
                            -{{ summaryExpense }}
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
                                                :is="localSort === 'date' ? directionIcon() : ArrowUpDown"
                                                class="h-4 w-4"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </th>
                                    <th class="px-6 py-3">{{ t('transactions.index.table.account') }}</th>
                                    <th class="px-6 py-3">{{ t('transactions.index.table.description') }}</th>
                                    <th class="px-6 py-3">{{ t('transactions.index.table.subject') }}</th>
                                    <th class="px-6 py-3">
                                        <button
                                            class="inline-flex items-center gap-2 hover:text-foreground"
                                            type="button"
                                            :aria-label="t('transactions.index.a11y.sortByAmount')"
                                            @click="setSort('amount')"
                                        >
                                            {{ t('transactions.index.table.amount') }}
                                            <component
                                                :is="localSort === 'amount' ? directionIcon() : ArrowUpDown"
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
                                    <td class="px-6 py-4 whitespace-nowrap tabular-nums">
                                        {{ formatDateIsoToPl(tx.date) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span>{{ tx.account?.name ?? t('transactions.index.readOnly.deletedAccount') }}</span>
                                            <span
                                                v-if="!tx.account"
                                                class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                            >
                                                {{ t('transactions.index.readOnly.badge') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ tx.description }}
                                    </td>
                                    <td class="px-6 py-4 text-muted-foreground">
                                        {{ tx.subject ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap tabular-nums">
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
                                        <Button v-if="tx.account" variant="secondary" size="sm" as-child>
                                            <Link
                                                :href="route('transactions.edit', tx.id) + currentSearch"
                                                :aria-label="t('transactions.index.a11y.edit', { description: tx.description })"
                                            >
                                                {{ t('actions.edit') }}
                                            </Link>
                                        </Button>
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
                                        <p class="mt-1 text-xs text-muted-foreground tabular-nums">{{ formatDateIsoToPl(tx.date) }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            {{ tx.account?.name ?? t('transactions.index.readOnly.deletedAccount') }}
                                            <span
                                                v-if="!tx.account"
                                                class="ml-2 rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground"
                                            >
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
                                            <Button v-if="tx.account" variant="secondary" size="sm" as-child>
                                                <Link :href="route('transactions.edit', tx.id) + currentSearch">{{ t('actions.edit') }}</Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <div class="hidden sm:flex flex-wrap items-center justify-between gap-3">
                            <div class="text-xs text-muted-foreground">
                                {{ t('transactions.index.pagination.pageOf', { page: transactions.meta?.current_page, pages: transactions.meta?.last_page }) }}
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
                                    v-html="link.label"
                                />
                            </nav>
                        </div>

                        <div class="sm:hidden flex items-center justify-between gap-3">
                            <Button variant="secondary" size="sm" as-child :disabled="transactions.meta?.current_page <= 1">
                                <Link :href="transactions.links[0]?.url ?? ''" :preserve-scroll="true">
                                    {{ t('transactions.index.pagination.prev') }}
                                </Link>
                            </Button>
                            <p class="text-xs text-muted-foreground">
                                {{ t('transactions.index.pagination.pageOf', { page: transactions.meta?.current_page, pages: transactions.meta?.last_page }) }}
                            </p>
                            <Button
                                variant="secondary"
                                size="sm"
                                as-child
                                :disabled="transactions.meta?.current_page >= transactions.meta?.last_page"
                            >
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

