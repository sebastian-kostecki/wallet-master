<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
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
    serverErrors: Record<string, string>;
    isLoading: boolean;
}>();

const { t } = useI18n();

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

const serverFromError = computed(() => props.serverErrors.from);
const serverToError = computed(() => props.serverErrors.to);
</script>

<template>
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
                        @update:model-value="(value: any) => (localAccountId = value)"
                    />
                </FormField>

                <FormField for-id="from" :label="t('transactions.index.filters.from')" :error="localErrors.from ?? serverFromError">
                    <DatePickerInput
                        id="from"
                        v-model="localFrom"
                        :disabled="isLoading"
                        @change="applyFilters"
                        @blur="applyFilters"
                    />
                </FormField>

                <FormField for-id="to" :label="t('transactions.index.filters.to')" :error="localErrors.to ?? serverToError">
                    <DatePickerInput
                        id="to"
                        v-model="localTo"
                        :disabled="isLoading"
                        @change="applyFilters"
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
                            @update:model-value="(value: any) => ((localSort = value), applyFilters())"
                        />
                    </FormField>

                    <FormField for-id="direction" :label="t('transactions.index.filters.direction')">
                        <DropdownSelect
                            id="direction"
                            :model-value="localDirection"
                            :options="directionOptions"
                            :disabled="isLoading"
                            @update:model-value="(value: any) => ((localDirection = value), applyFilters())"
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

        <div v-if="(localErrors.from ?? serverFromError) || (localErrors.to ?? serverToError)" class="mt-3">
            <InputError :message="localErrors.from ?? serverFromError ?? localErrors.to ?? serverToError" />
        </div>
    </div>
</template>
