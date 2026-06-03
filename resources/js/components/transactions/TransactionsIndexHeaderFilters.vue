<script setup lang="ts">
import DateRangePickerInput from '@/components/forms/DateRangePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import InputError from '@/components/InputError.vue';
import { track } from '@/lib/telemetry';
import { router } from '@inertiajs/vue3';
import { Coins } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
    bank_icon_url: string | null;
    bank?: string;
};

type Filters = {
    account_id: number | null;
    from: string | null; // DD-MM-YYYY
    to: string | null; // DD-MM-YYYY
    sort: 'date' | 'amount' | string;
    direction: 'asc' | 'desc' | string;
    per_page?: number;
};

const props = defineProps<{
    accounts: Account[];
    filters: Filters;
    serverErrors: Record<string, string>;
    isLoading: boolean;
}>();

const { t } = useI18n();

const accountOptions = computed<DropdownOption<number | null>[]>(() => [
    { value: null, label: t('transactions.index.filters.account.all') },
    ...props.accounts.map((a) => ({ value: a.id, label: a.name })),
]);

const localAccountId = ref<number | null>(props.filters.account_id ?? null);
const accountsById = computed(() => new Map(props.accounts.map((a) => [a.id, a])));

const selectedAccount = computed(() => {
    if (localAccountId.value === null) {
        return null;
    }

    return accountsById.value.get(localAccountId.value) ?? null;
});
const localFrom = ref<string>(props.filters.from ?? '');
const localTo = ref<string>(props.filters.to ?? '');

watch(
    () => props.filters,
    (next) => {
        localAccountId.value = next.account_id ?? null;
        localFrom.value = next.from ?? '';
        localTo.value = next.to ?? '';
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
    const trimmedFrom = localFrom.value.trim();
    const trimmedTo = localTo.value.trim();

    return {
        account_id: localAccountId.value ?? undefined,
        from: trimmedFrom || undefined,
        to: trimmedTo || undefined,
        sort: props.filters.sort ?? 'date',
        direction: props.filters.direction ?? 'desc',
        per_page: props.filters.per_page ?? undefined,
        page: undefined,
    };
}

function applyFiltersNow() {
    localErrors.value = {};

    if (props.isLoading) {
        return;
    }

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

    const query = buildQuery();

    track('transactions_filtered', {
        account_id: query.account_id ?? null,
        from: query.from ?? null,
        to: query.to ?? null,
    });

    router.get(route('transactions.index'), query, {
        preserveScroll: true,
        replace: true,
        preserveState: 'errors',
    });
}

const applyTimeout = ref<number | null>(null);
const lastApplied = ref<string>('');

function scheduleApply() {
    if (applyTimeout.value !== null) {
        window.clearTimeout(applyTimeout.value);
    }

    applyTimeout.value = window.setTimeout(() => {
        const nextKey = JSON.stringify(buildQuery());
        if (nextKey === lastApplied.value) {
            return;
        }

        lastApplied.value = nextKey;
        applyFiltersNow();
    }, 300);
}

onBeforeUnmount(() => {
    if (applyTimeout.value !== null) {
        window.clearTimeout(applyTimeout.value);
    }
});

watch([localAccountId, localFrom, localTo], () => {
    scheduleApply();
});

const serverFromError = computed(() => props.serverErrors.from);
const serverToError = computed(() => props.serverErrors.to);

const errorId = 'transactions-filters-error';
const errorMessage = computed(() => localErrors.value.from ?? serverFromError.value ?? localErrors.value.to ?? serverToError.value ?? '');
const hasError = computed(() => errorMessage.value.trim() !== '');
</script>

<template>
    <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
        <div class="grid gap-2 sm:flex sm:items-center">
            <div class="min-w-56 sm:min-w-64">
                <DropdownSelect
                    id="account_id"
                    :model-value="localAccountId"
                    :options="accountOptions"
                    :placeholder="t('transactions.index.filters.account.all')"
                    :disabled="isLoading"
                    :aria-label="t('transactions.index.filters.account.label')"
                    :aria-invalid="hasError"
                    :aria-describedby="hasError ? errorId : undefined"
                    @update:model-value="(value: any) => (localAccountId = value)"
                >
                    <template #trigger-leading>
                        <span
                            v-if="selectedAccount"
                            class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                            :class="
                                selectedAccount.bank === 'cash'
                                    ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                    : 'bg-muted'
                            "
                            aria-hidden="true"
                        >
                            <img
                                v-if="selectedAccount.bank_icon_url"
                                :src="selectedAccount.bank_icon_url"
                                :alt="selectedAccount.name"
                                class="h-5 w-5 object-cover"
                            />
                            <Coins v-else-if="selectedAccount.bank === 'cash'" class="h-3.5 w-3.5" />
                            <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                {{ selectedAccount.name.charAt(0).toUpperCase() }}
                            </span>
                        </span>
                    </template>

                    <template #option-leading="{ option }">
                        <span
                            v-if="option.value !== null"
                            class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                            :class="
                                accountsById.get(option.value)?.bank === 'cash'
                                    ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                    : 'bg-muted'
                            "
                            aria-hidden="true"
                        >
                            <img
                                v-if="accountsById.get(option.value)?.bank_icon_url"
                                :src="accountsById.get(option.value)?.bank_icon_url ?? ''"
                                :alt="accountsById.get(option.value)?.name ?? ''"
                                class="h-5 w-5 object-cover"
                            />
                            <Coins v-else-if="accountsById.get(option.value)?.bank === 'cash'" class="h-3.5 w-3.5" />
                            <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                {{ (accountsById.get(option.value)?.name ?? '?').charAt(0).toUpperCase() }}
                            </span>
                        </span>
                    </template>
                </DropdownSelect>
            </div>

            <div class="min-w-56 sm:min-w-64">
                <DateRangePickerInput
                    id="date_range"
                    v-model:from="localFrom"
                    v-model:to="localTo"
                    :disabled="isLoading"
                    :aria-label="`${t('transactions.index.filters.from')} / ${t('transactions.index.filters.to')}`"
                    :aria-invalid="hasError"
                    :aria-describedby="hasError ? errorId : undefined"
                    @change="applyFiltersNow"
                />
            </div>
        </div>

        <div v-if="hasError" class="sm:ml-auto">
            <div :id="errorId">
                <InputError :message="errorMessage" />
            </div>
        </div>
    </div>
</template>
