<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import AdvancedSectionCard from '@/components/forms/AdvancedSectionCard.vue';
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import DeleteTransactionDialog from '@/components/transactions/modals/DeleteTransactionDialog.vue';
import UnlinkTransferDialog from '@/components/transfers/UnlinkTransferDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { categoriesByIdMap, filterCategoriesByType, type CategoryOption } from '@/lib/categories';
import { displayAmount, normalizeAmount } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Coins } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
    currency_id: number;
    bank: string;
    bank_icon_url: string | null;
};

type Transaction = {
    id: number;
    account_id: number;
    date: string; // YYYY-MM-DD from backend
    booked_at: string; // YYYY-MM-DD from backend
    amount: string;
    type: string;
    description: string;
    subject: string | null;
    import_id: number | null;
    raw_statement_description: string | null;
    transfer_id: string | null;
    category_id: number;
    goal_id: number | null;
};

const props = defineProps<{
    transaction: Transaction;
    accounts: Account[];
    categories: CategoryOption[];
    goals: { id: number; name: string }[];
}>();

const { t } = useI18n();
const { transactionsIndexSearch, transactionsIndexHref } = useTransactionsIndexSearch();

function isoToDdMmYyyy(input: string): string {
    const trimmed = input.trim();
    const datePart = /^\d{4}-\d{2}-\d{2}/.test(trimmed) ? trimmed.slice(0, 10) : trimmed;

    const parts = datePart.split('-');
    if (parts.length !== 3) {
        return input;
    }

    const [yyyy, mm, dd] = parts;
    return `${dd}-${mm}-${yyyy}`;
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('transactions.index.title'),
        href: transactionsIndexHref.value,
    },
    {
        title: t('transactions.edit.title'),
        href: `/transactions/${props.transaction.id}/edit`,
    },
]);

const accountOptions = computed<DropdownOption<number>[]>(() => props.accounts.map((a) => ({ value: a.id, label: a.name })));
const accountsById = computed(() => new Map(props.accounts.map((a) => [a.id, a])));
const categoriesById = computed(() => categoriesByIdMap(props.categories));

const transactionType = computed(() => (props.transaction.type === 'income' ? 'income' : 'expense') as 'income' | 'expense');

const categoryOptions = computed<DropdownOption<number>[]>(() =>
    filterCategoriesByType(props.categories, transactionType.value).map((c) => ({
        value: c.id,
        label: c.name,
    })),
);

const goalOptions = computed<DropdownOption<number | null>[]>(() => [
    { value: null, label: t('transactions.fields.goalNone') },
    ...props.goals.map((g) => ({
        value: g.id,
        label: g.name,
    })),
]);

const form = useForm<{
    account_id: number;
    category_id: number;
    goal_id: number | null;
    date: string;
    booked_at: string;
    amount: string;
    description: string;
    subject: string;
}>({
    account_id: props.transaction.account_id,
    category_id: props.transaction.category_id,
    goal_id: props.transaction.goal_id,
    date: isoToDdMmYyyy(props.transaction.date),
    booked_at: props.transaction.booked_at === props.transaction.date ? '' : isoToDdMmYyyy(props.transaction.booked_at),
    amount: displayAmount(props.transaction.amount),
    description: props.transaction.description,
    subject: props.transaction.subject ?? '',
});

const selectedAccount = computed(() => {
    return accountsById.value.get(form.account_id) ?? null;
});

const isLinkedTransfer = computed(() => Boolean(props.transaction.transfer_id));

function submit() {
    form.amount = normalizeAmount(form.amount);
    if ((form.booked_at ?? '').trim() === '') {
        form.booked_at = form.date;
    }
    form.put(route('transactions.update', props.transaction.id) + transactionsIndexSearch.value, {
        onSuccess: () => {},
        onError: () => {},
    });
}

const deleteDialogOpen = ref(false);
const deleteProcessing = ref(false);
const unlinkDialogOpen = ref(false);
const unlinkProcessing = ref(false);

function onDeleted() {
    router.visit(transactionsIndexHref.value);
}

function fieldDescribedBy(errorId: string, hasError: boolean, hintId: string, includeHint: boolean): string | undefined {
    const ids: string[] = [];

    if (hasError) {
        ids.push(errorId);
    }

    if (includeHint) {
        ids.push(hintId);
    }

    return ids.length > 0 ? ids.join(' ') : undefined;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('transactions.edit.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
                <div class="flex flex-col gap-6">
                    <div
                        v-if="isLinkedTransfer"
                        class="rounded-xl border border-primary/30 bg-primary/5 p-4 dark:border-primary/40 dark:bg-primary/10"
                    >
                        <p class="text-sm font-semibold text-foreground">{{ t('transactions.edit.transfer.bannerTitle') }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">{{ t('transactions.edit.transfer.bannerDescription') }}</p>
                        <Button class="mt-3" type="button" variant="secondary" :disabled="unlinkProcessing" @click="unlinkDialogOpen = true">
                            {{ t('transactions.edit.transfer.unlinkAction') }}
                        </Button>
                    </div>

                    <form
                        id="transaction-edit-form"
                        class="flex flex-col gap-6"
                        @submit.prevent="submit"
                        :aria-busy="form.processing ? 'true' : 'false'"
                    >
                        <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                            <div class="grid gap-6">
                                <FormField
                                    for-id="account_id"
                                    :label="t('transactions.form.account')"
                                    :error="form.errors.account_id"
                                    :hint="isLinkedTransfer ? t('transactions.edit.transfer.accountHint') : null"
                                >
                                    <template #default="{ errorId, hintId, hasError }">
                                        <DropdownSelect
                                            id="account_id"
                                            :aria-invalid="hasError"
                                            :aria-disabled="isLinkedTransfer"
                                            :aria-describedby="fieldDescribedBy(errorId, hasError, hintId, isLinkedTransfer)"
                                            :model-value="form.account_id"
                                            :options="accountOptions"
                                            :placeholder="t('transactions.form.account')"
                                            :disabled="form.processing || accounts.length === 0 || isLinkedTransfer"
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
                                        >
                                            <template #trigger-leading>
                                                <CategoryBadge
                                                    v-if="categoriesById.get(form.category_id)"
                                                    :icon="categoriesById.get(form.category_id)!.icon"
                                                    :color="categoriesById.get(form.category_id)!.color"
                                                    size="sm"
                                                    :show-name="false"
                                                />
                                            </template>
                                            <template #option-leading="{ option }">
                                                <CategoryBadge
                                                    v-if="categoriesById.get(option.value)"
                                                    :icon="categoriesById.get(option.value)!.icon"
                                                    :color="categoriesById.get(option.value)!.color"
                                                    size="sm"
                                                    :show-name="false"
                                                />
                                            </template>
                                        </DropdownSelect>
                                    </template>
                                </FormField>

                                <FormField for-id="goal_id" :label="t('transactions.fields.goal')" :error="form.errors.goal_id">
                                    <template #default="{ errorId, hasError }">
                                        <DropdownSelect
                                            id="goal_id"
                                            :aria-invalid="hasError"
                                            :aria-describedby="hasError ? errorId : undefined"
                                            :model-value="form.goal_id"
                                            :options="goalOptions"
                                            :placeholder="t('transactions.fields.goal')"
                                            :disabled="form.processing || goalOptions.length <= 1"
                                            @update:model-value="(value) => (form.goal_id = value)"
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

                                <FormField
                                    for-id="amount"
                                    :label="t('transactions.form.amount')"
                                    :error="form.errors.amount"
                                    :hint="isLinkedTransfer ? t('transactions.edit.transfer.amountHint') : null"
                                >
                                    <template #default="{ errorId, hintId, hasError }">
                                        <Input
                                            id="amount"
                                            v-model="form.amount"
                                            inputmode="decimal"
                                            :disabled="form.processing || isLinkedTransfer"
                                            :aria-disabled="isLinkedTransfer ? true : undefined"
                                            :aria-invalid="hasError ? true : undefined"
                                            :aria-describedby="fieldDescribedBy(errorId, hasError, hintId, isLinkedTransfer)"
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

                <div class="flex flex-col gap-6">
                    <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="text-base font-semibold">{{ t('transactions.edit.hints.title') }}</h2>
                        <div class="mt-3 grid gap-3 text-sm text-muted-foreground">
                            <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                                {{ t('transactions.edit.hints.scope') }}
                            </div>
                            <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                                {{ isLinkedTransfer ? t('transactions.edit.hints.amountTransfer') : t('transactions.edit.hints.amount') }}
                            </div>
                            <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                                {{ t('transactions.edit.hints.save') }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="transaction.raw_statement_description && transaction.raw_statement_description.trim() !== ''"
                        class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
                    >
                        <h2 class="text-base font-semibold">{{ t('transactions.edit.statement.title') }}</h2>
                        <p class="mt-2 text-sm text-muted-foreground">{{ t('transactions.edit.statement.description') }}</p>
                        <div class="mt-4 rounded-lg border border-sidebar-border/70 bg-muted/20 p-4 text-sm dark:border-sidebar-border">
                            <p class="whitespace-pre-wrap break-words text-foreground">
                                {{ transaction.raw_statement_description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-destructive/30 bg-destructive/5 p-6 dark:border-destructive/40 dark:bg-destructive/10">
                <p class="text-sm font-semibold text-destructive">{{ t('transactions.edit.dangerZone.title') }}</p>
                <p class="mt-2 text-sm text-muted-foreground">{{ t('transactions.edit.dangerZone.description') }}</p>

                <Button class="mt-4" variant="destructive" :disabled="deleteProcessing" @click="deleteDialogOpen = true">
                    {{ t('transactions.edit.deleteAction') }}
                </Button>
            </div>
        </div>

        <UnlinkTransferDialog
            v-model:open="unlinkDialogOpen"
            :transfer-id="transaction.transfer_id"
            :disabled="unlinkProcessing"
            @processing="(value: boolean) => (unlinkProcessing = value)"
        />

        <DeleteTransactionDialog
            v-model:open="deleteDialogOpen"
            :transaction-id="transaction.id"
            :description="transaction.description"
            :is-transfer="Boolean(transaction.transfer_id)"
            :disabled="deleteProcessing"
            :return-search="transactionsIndexSearch"
            @processing="(value: any) => (deleteProcessing = value)"
            @success="onDeleted"
        />
    </AppLayout>
</template>
