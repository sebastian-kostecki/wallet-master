<script setup lang="ts">
import DateRangePickerInput from '@/components/forms/DateRangePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import InputError from '@/components/InputError.vue';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
};

type Filters = {
    all_time?: boolean;
    account_id: number | null;
    import_id?: number | null;
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
const localFrom = ref<string>(props.filters.from ?? '');
const localTo = ref<string>(props.filters.to ?? '');
const isAllTime = ref<boolean>(Boolean(props.filters.all_time));

watch(
    () => props.filters,
    (next) => {
        localAccountId.value = next.account_id ?? null;
        localFrom.value = next.from ?? '';
        localTo.value = next.to ?? '';
        isAllTime.value = Boolean(next.all_time);
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
        import_id: props.filters.import_id ?? undefined,
        all_time: isAllTime.value ? 1 : undefined,
        from: isAllTime.value ? undefined : trimmedFrom || undefined,
        to: isAllTime.value ? undefined : trimmedTo || undefined,
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

    if (isAllTime.value) {
        router.get(route('transactions.index'), buildQuery(), {
            preserveScroll: true,
            replace: true,
            preserveState: 'errors',
        });
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

    router.get(route('transactions.index'), buildQuery(), {
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

watch(
    [localFrom, localTo],
    ([from, to]) => {
        const noRange = from.trim() === '' && to.trim() === '';
        if (noRange) {
            isAllTime.value = true;
        } else if (isAllTime.value) {
            isAllTime.value = false;
        }
    },
    { flush: 'sync' },
);

const serverFromError = computed(() => props.serverErrors.from);
const serverToError = computed(() => props.serverErrors.to);

const errorId = 'transactions-filters-error';
const errorMessage = computed(() => localErrors.value.from ?? serverFromError.value ?? localErrors.value.to ?? serverToError.value ?? '');
const hasError = computed(() => errorMessage.value.trim() !== '');
</script>

<template>
    <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
        <div v-if="filters.import_id" class="flex flex-wrap items-center gap-2 sm:mr-auto">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-1 text-xs text-foreground hover:bg-muted/70"
                :aria-label="t('transactions.index.importPreset.clearAria')"
                @click="
                    () => {
                        router.get(
                            route('transactions.index'),
                            {
                                account_id: localAccountId ?? undefined,
                                all_time: isAllTime ? 1 : undefined,
                                from: isAllTime ? undefined : localFrom.trim() || undefined,
                                to: isAllTime ? undefined : localTo.trim() || undefined,
                                sort: filters.sort ?? 'date',
                                direction: filters.direction ?? 'desc',
                                per_page: filters.per_page ?? undefined,
                                import_id: undefined,
                                page: undefined,
                            },
                            { preserveScroll: true, replace: true, preserveState: 'errors' },
                        );
                    }
                "
            >
                <span class="font-medium">{{ t('transactions.index.importPreset.label') }}</span>
                <span class="text-muted-foreground">×</span>
            </button>
        </div>

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
                />
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

