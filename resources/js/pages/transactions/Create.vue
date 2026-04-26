<script setup lang="ts">
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { normalizeAmount } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
    currency_id: number;
    bank_icon_url: string | null;
};

const props = defineProps<{
    accounts: Account[];
}>();

const { t } = useI18n();
const page = usePage() as any;
const currentSearch = computed(() => {
    const url = page.url as string;
    const idx = url.indexOf('?');
    return idx >= 0 ? url.slice(idx) : '';
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('transactions.index.title'),
        href: '/transactions',
    },
    {
        title: t('transactions.create.title'),
        href: '/transactions/create',
    },
]);

const accountOptions = computed<DropdownOption<number>[]>(() => props.accounts.map((a) => ({ value: a.id, label: a.name })));
const accountsById = computed(() => new Map(props.accounts.map((a) => [a.id, a])));

const form = useForm<{
    account_id: number | null;
    date: string;
    amount: string;
    description: string;
    subject: string;
}>({
    account_id: props.accounts[0]?.id ?? null,
    date: todayDdMmYyyy(),
    amount: '0,00',
    description: '',
    subject: '',
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

const transactionKind = ref<'income' | 'expense'>('expense');

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
    form.post(route('transactions.store'));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('transactions.create.title')" />

        <template #headerActions>
            <Button variant="secondary" as-child>
                <Link :href="route('transactions.index') + currentSearch">{{ t('transactions.create.back') }}</Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <div class="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <form @submit.prevent="submit" class="grid gap-6">
                    <div class="grid gap-2">
                        <span class="text-sm font-medium">{{ t('transactions.form.type') }}</span>
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
                    </div>

                    <FormField for-id="account_id" :label="t('transactions.form.account')" :error="form.errors.account_id">
                        <DropdownSelect
                            id="account_id"
                            :model-value="form.account_id"
                            :options="accountOptions"
                            :placeholder="t('transactions.form.account')"
                            :disabled="form.processing || accounts.length === 0"
                            @update:model-value="(value) => (form.account_id = value)"
                        >
                            <template #trigger-leading>
                                <span v-if="selectedAccount" class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded bg-muted">
                                    <img
                                        v-if="selectedAccount.bank_icon_url"
                                        :src="selectedAccount.bank_icon_url"
                                        :alt="selectedAccount.name"
                                        class="h-5 w-5 object-cover"
                                    />
                                    <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                        {{ selectedAccount.name.charAt(0).toUpperCase() }}
                                    </span>
                                </span>
                            </template>

                            <template #option-leading="{ option }">
                                <span class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded bg-muted">
                                    <img
                                        v-if="accountsById.get(option.value)?.bank_icon_url"
                                        :src="accountsById.get(option.value)?.bank_icon_url ?? ''"
                                        :alt="accountsById.get(option.value)?.name ?? ''"
                                        class="h-5 w-5 object-cover"
                                    />
                                    <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                        {{ (accountsById.get(option.value)?.name ?? '?').charAt(0).toUpperCase() }}
                                    </span>
                                </span>
                            </template>
                        </DropdownSelect>
                    </FormField>

                    <FormField for-id="date" :label="t('transactions.form.date')" :error="form.errors.date">
                        <DatePickerInput
                            id="date"
                            :model-value="form.date"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.date = value)"
                        />
                    </FormField>

                    <FormField for-id="amount" :label="t('transactions.form.amount')" :error="form.errors.amount">
                        <Input id="amount" v-model="form.amount" inputmode="decimal" :disabled="form.processing" />
                    </FormField>

                    <FormField for-id="subject" :label="t('transactions.form.subject')" :error="form.errors.subject">
                        <Input id="subject" v-model="form.subject" :disabled="form.processing" />
                    </FormField>

                    <FormField for-id="description" :label="t('transactions.form.description')" :error="form.errors.description">
                        <textarea
                            id="description"
                            v-model="form.description"
                            :disabled="form.processing"
                            rows="4"
                            class="flex min-h-[96px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        />
                    </FormField>

                    <Button type="submit" :disabled="form.processing">{{ t('actions.save') }}</Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
