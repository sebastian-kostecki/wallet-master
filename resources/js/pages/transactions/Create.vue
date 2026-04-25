<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { normalizeAmount } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
    currency_id: number;
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

const form = useForm<{
    account_id: number | null;
    date: string;
    amount: string;
    description: string;
    subject: string;
}>({
    account_id: props.accounts[0]?.id ?? null,
    date: '',
    amount: '0,00',
    description: '',
    subject: '',
});

function submit() {
    form.amount = normalizeAmount(form.amount);
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
                    <FormField for-id="account_id" :label="t('transactions.form.account')" :error="form.errors.account_id">
                        <DropdownSelect
                            id="account_id"
                            :model-value="form.account_id"
                            :options="accountOptions"
                            :disabled="form.processing || accounts.length === 0"
                            @update:model-value="(value) => (form.account_id = value)"
                        />
                    </FormField>

                    <FormField for-id="date" :label="t('transactions.form.date')" :error="form.errors.date">
                        <Input id="date" v-model="form.date" inputmode="numeric" placeholder="DD-MM-YYYY" :disabled="form.processing" />
                    </FormField>

                    <FormField for-id="amount" :label="t('transactions.form.amount')" :error="form.errors.amount">
                        <Input id="amount" v-model="form.amount" inputmode="decimal" :disabled="form.processing" />
                    </FormField>

                    <FormField for-id="description" :label="t('transactions.form.description')" :error="form.errors.description">
                        <Input id="description" v-model="form.description" :disabled="form.processing" />
                    </FormField>

                    <FormField for-id="subject" :label="t('transactions.form.subject')" :error="form.errors.subject">
                        <Input id="subject" v-model="form.subject" :disabled="form.processing" />
                    </FormField>

                    <Button type="submit" :disabled="form.processing">{{ t('actions.save') }}</Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

