<script setup lang="ts">
import AdvancedSectionCard from '@/components/forms/AdvancedSectionCard.vue';
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { filterCategoriesByType, firstCategoryId, type CategoryOption } from '@/lib/categories';
import { normalizeAmount } from '@/lib/money';
import { track } from '@/lib/telemetry';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Coins } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
    currency_id: number;
    bank: string;
    bank_icon_url: string | null;
};

const props = defineProps<{
    accounts: Account[];
    categories: CategoryOption[];
}>();

const { t } = useI18n();
const { transactionsIndexSearch, transactionsIndexHref } = useTransactionsIndexSearch();

onMounted(() => {
    track('transaction_create_opened');
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('transactions.index.title'),
        href: transactionsIndexHref.value,
    },
    {
        title: t('transactions.create.title'),
        href: '/transactions/create',
    },
]);

const accountOptions = computed<DropdownOption<number>[]>(() => props.accounts.map((a) => ({ value: a.id, label: a.name })));
const accountsById = computed(() => new Map(props.accounts.map((a) => [a.id, a])));

const transactionKind = ref<'income' | 'expense'>('expense');

const form = useForm<{
    account_id: number | null;
    category_id: number | null;
    date: string;
    booked_at: string;
    amount: string;
    description: string;
    subject: string;
}>({
    account_id: props.accounts[0]?.id ?? null,
    category_id: firstCategoryId(filterCategoriesByType(props.categories, transactionKind.value)),
    date: todayDdMmYyyy(),
    booked_at: '',
    amount: '0,00',
    description: '',
    subject: '',
});

const categoryOptions = computed<DropdownOption<number>[]>(() =>
    filterCategoriesByType(props.categories, transactionKind.value).map((c) => ({
        value: c.id,
        label: c.name,
    })),
);

watch(transactionKind, (kind) => {
    const filtered = filterCategoriesByType(props.categories, kind);
    if (!filtered.some((c) => c.id === form.category_id)) {
        form.category_id = firstCategoryId(filtered);
    }
});

function todayDdMmYyyy(): string {
    const now = new Date();
    const d = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));

    const dd = String(d.getUTCDate()).padStart(2, '0');
    const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
    const yyyy = d.getUTCFullYear();

    return `${dd}-${mm}-${yyyy}`;
}

const selectedAccount = computed(() => {
    if (form.account_id === null) {
        return null;
    }
    return accountsById.value.get(form.account_id) ?? null;
});

function applyAmountSign(amount: string): string {
    const trimmed = amount.trim();
    if (trimmed === '') {
        return amount;
    }

    if (transactionKind.value === 'expense') {
        return trimmed.startsWith('-') ? amount : `-${amount}`;
    }

    return trimmed.startsWith('-') ? amount.replace(/^\s*-+/, '') : amount;
}

function submit() {
    form.amount = normalizeAmount(form.amount);
    form.amount = applyAmountSign(form.amount);
    if ((form.booked_at ?? '').trim() === '') {
        form.booked_at = form.date;
    }
    form.post(route('transactions.store') + transactionsIndexSearch.value, {
        onSuccess: () => {},
        onError: () => {},
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('transactions.create.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
                <div class="flex flex-col gap-6">
                    <form id="transaction-form" class="flex flex-col gap-6" @submit.prevent="submit" :aria-busy="form.processing ? 'true' : 'false'">
                        <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                            <div class="grid gap-6">
                                <fieldset class="grid gap-2 border-0 p-0" :disabled="form.processing">
                                    <legend class="text-sm font-medium">{{ t('transactions.form.type') }}</legend>
                                    <div class="grid grid-cols-2 gap-1 rounded-lg border border-input bg-muted/30 p-1">
                                        <Button
                                            type="button"
                                            :variant="transactionKind === 'income' ? 'secondary' : 'ghost'"
                                            class="h-9 justify-center"
                                            :aria-pressed="transactionKind === 'income'"
                                            :disabled="form.processing"
                                            @click="transactionKind = 'income'"
                                        >
                                            {{ t('transactions.types.income') }}
                                        </Button>
                                        <Button
                                            type="button"
                                            :variant="transactionKind === 'expense' ? 'secondary' : 'ghost'"
                                            class="h-9 justify-center"
                                            :aria-pressed="transactionKind === 'expense'"
                                            :disabled="form.processing"
                                            @click="transactionKind = 'expense'"
                                        >
                                            {{ t('transactions.types.expense') }}
                                        </Button>
                                    </div>
                                </fieldset>

                                <FormField for-id="account_id" :label="t('transactions.form.account')" :error="form.errors.account_id">
                                    <template #default="{ errorId, hasError }">
                                        <DropdownSelect
                                            id="account_id"
                                            :aria-invalid="hasError"
                                            :aria-describedby="hasError ? errorId : undefined"
                                            :model-value="form.account_id"
                                            :options="accountOptions"
                                            :placeholder="t('transactions.form.account')"
                                            :disabled="form.processing || accounts.length === 0"
                                            @update:model-value="(value) => (form.account_id = value)"
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
                                    </template>
                                </FormField>

                                <FormField for-id="category_id" :label="t('transactions.fields.category')" :error="form.errors.category_id">
                                    <template #default="{ errorId, hasError }">
                                        <DropdownSelect
                                            id="category_id"
                                            :aria-invalid="hasError"
                                            :aria-describedby="hasError ? errorId : undefined"
                                            :model-value="form.category_id"
                                            :options="categoryOptions"
                                            :placeholder="t('transactions.fields.category')"
                                            :disabled="form.processing || categoryOptions.length === 0"
                                            @update:model-value="(value) => (form.category_id = value)"
                                        />
                                    </template>
                                </FormField>

                                <FormField for-id="date" :label="t('transactions.form.date')" :error="form.errors.date">
                                    <template #default="{ errorId, hasError }">
                                        <DatePickerInput
                                            id="date"
                                            :aria-invalid="hasError"
                                            :aria-describedby="hasError ? errorId : undefined"
                                            :model-value="form.date"
                                            :disabled="form.processing"
                                            @update:model-value="(value) => (form.date = value)"
                                        />
                                    </template>
                                </FormField>

                                <FormField for-id="amount" :label="t('transactions.form.amount')" :error="form.errors.amount">
                                    <template #default="{ errorId, hasError }">
                                        <Input
                                            id="amount"
                                            v-model="form.amount"
                                            inputmode="decimal"
                                            :disabled="form.processing"
                                            :aria-invalid="hasError ? true : undefined"
                                            :aria-describedby="hasError ? errorId : undefined"
                                        />
                                    </template>
                                </FormField>

                                <FormField for-id="subject" :label="t('transactions.form.subject')" :error="form.errors.subject">
                                    <template #default="{ errorId, hasError }">
                                        <Input
                                            id="subject"
                                            v-model="form.subject"
                                            :disabled="form.processing"
                                            :aria-invalid="hasError ? true : undefined"
                                            :aria-describedby="hasError ? errorId : undefined"
                                        />
                                    </template>
                                </FormField>

                                <FormField for-id="description" :label="t('transactions.form.description')" :error="form.errors.description">
                                    <template #default="{ errorId, hasError }">
                                        <textarea
                                            id="description"
                                            v-model="form.description"
                                            :disabled="form.processing"
                                            rows="4"
                                            :aria-invalid="hasError ? true : undefined"
                                            :aria-describedby="hasError ? errorId : undefined"
                                            class="flex min-h-[96px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        />
                                    </template>
                                </FormField>

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <Button variant="secondary" as-child>
                                        <Link :href="transactionsIndexHref">{{ t('actions.cancel') }}</Link>
                                    </Button>

                                    <Button type="submit" :disabled="form.processing" :aria-busy="form.processing || undefined">
                                        {{ t('actions.save') }}
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <AdvancedSectionCard :disabled="form.processing">
                            <template #title>{{ t('advancedSection.toggle') }}</template>
                            <template #hint>{{ t('transactions.form.advancedDatesHint') }}</template>
                            <FormField for-id="booked_at" :label="t('transactions.form.booked_at')" :error="form.errors.booked_at">
                                <template #default="{ errorId, hasError }">
                                    <DatePickerInput
                                        id="booked_at"
                                        :aria-invalid="hasError"
                                        :aria-describedby="hasError ? errorId : undefined"
                                        :model-value="form.booked_at"
                                        :disabled="form.processing"
                                        @update:model-value="(value) => (form.booked_at = value)"
                                    />
                                </template>
                            </FormField>
                        </AdvancedSectionCard>
                    </form>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h2 class="text-base font-semibold">{{ t('transactions.create.hints.title') }}</h2>
                    <div class="mt-3 grid gap-3 text-sm text-muted-foreground">
                        <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                            {{ t('transactions.create.hints.type') }}
                        </div>
                        <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                            {{ t('transactions.create.hints.amount') }}
                        </div>
                        <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                            {{ t('transactions.create.hints.description') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
