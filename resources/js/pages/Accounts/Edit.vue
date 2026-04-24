<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AdjustAccountBalanceDialog from '@/components/accounts/modals/AdjustAccountBalanceDialog.vue';
import DeleteAccountDialog from '@/components/accounts/modals/DeleteAccountDialog.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Konta',
        href: '/accounts',
    },
    {
        title: 'Edytuj konto',
        href: `/accounts/${props.account.id}/edit`,
    },
];

const money = new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function formatMoney(value: string) {
    const parsed = Number(value);
    if (Number.isNaN(parsed)) {
        return value;
    }

    return money.format(parsed);
}

function normalizeAmount(input: string) {
    return input.replace(/\s/g, '').replace(',', '.');
}

function displayAmount(input: string) {
    return input.replace('.', ',');
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
        <Head title="Edytuj konto" />

        <template #headerActions>
            <Button variant="secondary" as-child>
                <Link :href="route('accounts.index')">Wróć</Link>
            </Button>

            <Button variant="destructive" :disabled="deleteProcessing" @click="deleteDialogOpen = true">Usuń konto</Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <form @submit.prevent="submit" class="grid gap-6">
                        <div class="grid gap-2">
                            <Label for="name">Nazwa</Label>
                            <Input id="name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="bank">Bank</Label>
                            <select
                                id="bank"
                                v-model="form.bank"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                required
                            >
                                <option v-for="bank in banks" :key="bank.value" :value="bank.value">
                                    {{ bank.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.bank" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="type">Typ konta</Label>
                            <select
                                id="type"
                                v-model="form.type"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                required
                            >
                                <option v-for="accountType in accountTypes" :key="accountType.value" :value="accountType.value">
                                    {{ accountType.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.type" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="opening_balance">Saldo początkowe</Label>
                            <Input id="opening_balance" inputmode="decimal" v-model="form.opening_balance" />
                            <InputError :message="form.errors.opening_balance" />
                            <p class="text-xs text-muted-foreground">
                                Zmiana salda początkowego przeliczy saldo bieżące o różnicę.
                            </p>
                        </div>

                        <Button type="submit" :disabled="form.processing">Zapisz zmiany</Button>
                    </form>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <p class="text-xs text-muted-foreground">Saldo bieżące</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums">
                        {{ formatMoney(account.current_balance) }} {{ account.currency?.symbol ?? 'zł' }}
                    </p>

                    <Button class="mt-4" variant="outline" :disabled="adjustProcessing" @click="adjustDialogOpen = true">Ustaw saldo</Button>
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

