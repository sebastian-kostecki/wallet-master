<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AdjustAccountBalanceDialog from '@/components/accounts/modals/AdjustAccountBalanceDialog.vue';
import DeleteAccountDialog from '@/components/accounts/modals/DeleteAccountDialog.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { displayAmount, normalizeAmount } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Option = {
    value: string;
    label: string;
};

type Currency = {
    id: number;
    code: string;
    symbol: string | null;
    precision: number;
};

type Account = {
    id: number;
    name: string;
    bank: string;
    type: string;
    currency_id: number;
    opening_balance: string;
    current_balance: string;
    currency: Currency | null;
};

const props = defineProps<{
    account: Account;
    banks: Option[];
    accountTypes: Option[];
}>();

const { t, locale } = useI18n();

const bankOptions = computed<DropdownOption<string>[]>(() => {
    return props.banks.map((b) => ({ value: b.value, label: b.label }));
});

const accountTypeOptions = computed<DropdownOption<string>[]>(() => {
    return props.accountTypes.map((a) => ({ value: a.value, label: a.label }));
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('accounts.index.title'),
        href: '/accounts',
    },
    {
        title: t('accounts.edit.title'),
        href: `/accounts/${props.account.id}/edit`,
    },
]);

const money = computed(() => {
    const resolvedLocale = locale.value === 'pl' ? 'pl-PL' : 'en-US';

    return new Intl.NumberFormat(resolvedLocale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
});

function formatMoney(value: string) {
    const parsed = Number(value);
    if (Number.isNaN(parsed)) {
        return value;
    }

    return money.value.format(parsed);
}

const form = useForm({
    name: props.account.name,
    bank: props.account.bank,
    type: props.account.type,
    opening_balance: displayAmount(props.account.opening_balance),
});

function submit() {
    form.opening_balance = normalizeAmount(form.opening_balance);
    form.patch(route('accounts.update', props.account.id));
}

const deleteDialogOpen = ref(false);
const deleteProcessing = ref(false);

const adjustDialogOpen = ref(false);
const adjustProcessing = ref(false);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('accounts.edit.title')" />

        <template #headerActions>
            <Button variant="secondary" as-child>
                <Link :href="route('accounts.index')">{{ t('accounts.edit.back') }}</Link>
            </Button>

            <Button variant="destructive" :disabled="deleteProcessing" @click="deleteDialogOpen = true">
                {{ t('accounts.edit.deleteAction') }}
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <form @submit.prevent="submit" class="grid gap-6">
                        <FormField for-id="name" :label="t('accounts.create.fields.name.label')" :error="form.errors.name">
                            <Input id="name" v-model="form.name" required />
                        </FormField>

                        <FormField for-id="bank" :label="t('accounts.create.fields.bank.label')" :error="form.errors.bank">
                            <DropdownSelect
                                id="bank"
                                :model-value="form.bank"
                                :options="bankOptions"
                                :placeholder="t('accounts.create.fields.bank.placeholder')"
                                :disabled="form.processing"
                                @update:model-value="(value) => (form.bank = value)"
                            />
                        </FormField>

                        <FormField for-id="type" :label="t('accounts.create.fields.type.label')" :error="form.errors.type">
                            <DropdownSelect
                                id="type"
                                :model-value="form.type"
                                :options="accountTypeOptions"
                                :placeholder="t('accounts.create.fields.type.placeholder')"
                                :disabled="form.processing"
                                @update:model-value="(value) => (form.type = value)"
                            />
                        </FormField>

                        <FormField
                            for-id="opening_balance"
                            :label="t('accounts.create.fields.openingBalance.label')"
                            :error="form.errors.opening_balance"
                        >
                            <Input id="opening_balance" inputmode="decimal" v-model="form.opening_balance" />
                            <p class="text-xs text-muted-foreground">
                                {{ t('accounts.edit.openingBalanceHint') }}
                            </p>
                        </FormField>

                        <Button type="submit" :disabled="form.processing">{{ t('actions.save') }}</Button>
                    </form>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">{{ t('accounts.card.currentBalance') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums">
                        {{ formatMoney(account.current_balance) }} {{ account.currency?.symbol ?? t('currency.defaultSymbol') }}
                    </p>

                    <Button class="mt-4" variant="outline" :disabled="adjustProcessing" @click="adjustDialogOpen = true">
                        {{ t('actions.setBalance') }}
                    </Button>
                </div>
            </div>
        </div>

        <AdjustAccountBalanceDialog
            v-model:open="adjustDialogOpen"
            :account-id="account.id"
            :initial-new-balance="displayAmount(account.current_balance)"
            @processing="(value) => (adjustProcessing = value)"
        />

        <DeleteAccountDialog
            v-model:open="deleteDialogOpen"
            :account-id="account.id"
            :account-name="account.name"
            :current-balance="account.current_balance"
            :currency-symbol="account.currency?.symbol ?? null"
            :format-money="formatMoney"
            @processing="(value) => (deleteProcessing = value)"
        />
    </AppLayout>
</template>

