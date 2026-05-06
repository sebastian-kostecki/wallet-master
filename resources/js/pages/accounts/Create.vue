<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { normalizeAmount } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Currency = {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    precision: number;
};

type Option = {
    value: string;
    label_key: string;
    icon_url?: string | null;
    icon_name?: string | null;
};

const props = defineProps<{
    currencies: Currency[];
    banks: Option[];
    accountTypes: Option[];
}>();

const { t } = useI18n();

const initialCurrencyId = computed(() => props.currencies[0]?.id ?? null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('accounts.index.title'),
        href: '/accounts',
    },
    {
        title: t('accounts.create.title'),
        href: '/accounts/create',
    },
]);

const form = useForm<{
    name: string;
    bank: string;
    type: string;
    currency_id: number | null;
    opening_balance: string;
}>({
    name: '',
    bank: props.banks[0]?.value ?? '',
    type: props.accountTypes[0]?.value ?? '',
    currency_id: initialCurrencyId.value,
    opening_balance: '0,00',
});

const selectedBank = computed(() => props.banks.find((b) => b.value === form.bank) ?? null);
const selectedAccountType = computed(() => props.accountTypes.find((t) => t.value === form.type) ?? null);

function resolveBankIconUrl(bankValue: string): string | null {
    return props.banks.find((b) => b.value === bankValue)?.icon_url ?? null;
}

const bankOptions = computed<DropdownOption<string>[]>(() => {
    return props.banks.map((b) => ({ value: b.value, label: t(b.label_key) }));
});

const accountTypeOptions = computed<DropdownOption<string>[]>(() => {
    return props.accountTypes.map((a) => ({ value: a.value, label: t(a.label_key) }));
});

const currencyOptions = computed<DropdownOption<number>[]>(() => {
    return props.currencies.map((c) => ({ value: c.id, label: `${c.code} — ${c.name}` }));
});

function submit() {
    form.opening_balance = normalizeAmount(form.opening_balance);
    form.post(route('accounts.store'));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('accounts.create.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <form @submit.prevent="submit" class="grid gap-6">
                    <FormField for-id="name" :label="t('accounts.create.fields.name.label')" :error="form.errors.name">
                        <Input id="name" v-model="form.name" required autofocus :placeholder="t('accounts.create.fields.name.placeholder')" />
                    </FormField>

                    <FormField for-id="bank" :label="t('accounts.create.fields.bank.label')" :error="form.errors.bank">
                        <DropdownSelect
                            id="bank"
                            :model-value="form.bank"
                            :options="bankOptions"
                            :placeholder="t('accounts.create.fields.bank.placeholder')"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.bank = value)"
                        >
                            <template #trigger-leading>
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded"
                                    :class="
                                        selectedBank?.value === 'cash'
                                            ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                            : 'bg-muted'
                                    "
                                    aria-hidden="true"
                                >
                                    <img
                                        v-if="selectedBank?.icon_url"
                                        :src="selectedBank.icon_url"
                                        :alt="t(selectedBank.label_key)"
                                        class="h-5 w-5 object-cover"
                                        loading="lazy"
                                    />
                                    <Icon v-else :name="selectedBank?.value === 'cash' ? 'coins' : 'landmark'" class="h-3.5 w-3.5" aria-hidden="true" />
                                </span>
                            </template>

                            <template #option-leading="{ option }">
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded"
                                    :class="
                                        option.value === 'cash'
                                            ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                            : 'bg-muted'
                                    "
                                    aria-hidden="true"
                                >
                                    <img
                                        v-if="resolveBankIconUrl(option.value)"
                                        :src="resolveBankIconUrl(option.value) ?? undefined"
                                        :alt="option.label"
                                        class="h-5 w-5 object-cover"
                                        loading="lazy"
                                    />
                                    <Icon v-else :name="option.value === 'cash' ? 'coins' : 'landmark'" class="h-3.5 w-3.5" aria-hidden="true" />
                                </span>
                            </template>
                        </DropdownSelect>
                    </FormField>

                    <FormField for-id="type" :label="t('accounts.create.fields.type.label')" :error="form.errors.type">
                        <DropdownSelect
                            id="type"
                            :model-value="form.type"
                            :options="accountTypeOptions"
                            :placeholder="t('accounts.create.fields.type.placeholder')"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.type = value)"
                        >
                            <template #trigger-leading>
                                <Icon
                                    :name="selectedAccountType?.icon_name ?? 'wallet'"
                                    class="h-5 w-5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </template>

                            <template #option-leading="{ option }">
                                <Icon
                                    :name="props.accountTypes.find((a) => a.value === option.value)?.icon_name ?? 'wallet'"
                                    class="h-5 w-5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </template>
                        </DropdownSelect>
                    </FormField>

                    <FormField for-id="currency" :label="t('accounts.create.fields.currency.label')" :error="form.errors.currency_id">
                        <DropdownSelect
                            id="currency"
                            :model-value="form.currency_id"
                            :options="currencyOptions"
                            :placeholder="t('accounts.create.fields.currency.placeholder')"
                            :disabled="form.processing || currencies.length === 0"
                            @update:model-value="(value) => (form.currency_id = value)"
                        >
                            <template #trigger-leading>
                                <Icon name="coins" class="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                            </template>
                        </DropdownSelect>
                    </FormField>

                    <FormField
                        for-id="opening_balance"
                        :label="t('accounts.create.fields.openingBalance.label')"
                        :error="form.errors.opening_balance"
                    >
                        <Input
                            id="opening_balance"
                            inputmode="decimal"
                            v-model="form.opening_balance"
                            :placeholder="t('accounts.create.fields.openingBalance.placeholder')"
                        />
                    </FormField>

                    <div class="grid gap-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <Button variant="secondary" as-child>
                                <Link :href="route('accounts.index')">{{ t('accounts.create.back') }}</Link>
                            </Button>

                            <Button type="submit" :disabled="form.processing">{{ t('actions.save') }}</Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
