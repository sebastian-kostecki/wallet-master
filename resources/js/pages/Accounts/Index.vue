<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AccountsSummaryCard from '@/components/accounts/AccountsSummaryCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Currency = {
    id: number;
    code: string;
    symbol: string | null;
    precision: number;
};

type Account = {
    id: number;
    name: string;
    current_balance: string;
    currency: Currency;
};

const props = defineProps<{
    accounts: Account[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Konta',
        href: '/accounts',
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

const adjustingAccountId = ref<number | null>(null);
const selectedAccount = computed(() => props.accounts.find((a) => a.id === adjustingAccountId.value) ?? null);
const adjustmentConfirmed = ref(false);

const adjustForm = useForm<{ new_balance: string }>({
    new_balance: '',
});

const canSubmitAdjustment = computed(
    () => adjustingAccountId.value !== null && adjustForm.new_balance.length > 0 && adjustmentConfirmed.value,
);

function openAdjustBalance(accountId: number) {
    adjustingAccountId.value = accountId;
    adjustmentConfirmed.value = false;
    adjustForm.clearErrors();
    adjustForm.new_balance = selectedAccount.value?.current_balance ?? '';
}

function normalizeAmount(input: string) {
    return input.replace(/\s/g, '').replace(',', '.');
}

function submitAdjustment() {
    if (adjustingAccountId.value === null) {
        return;
    }

    adjustForm.new_balance = normalizeAmount(adjustForm.new_balance);
    adjustForm.patch(route('accounts.balance.update', adjustingAccountId.value), {
        preserveScroll: true,
        onSuccess: () => {
            adjustForm.reset();
            adjustingAccountId.value = null;
            adjustmentConfirmed.value = false;
        },
    });
}

const deleteForm = useForm({});
const deletingAccountId = ref<number | null>(null);
const deletingAccount = computed(() => props.accounts.find((a) => a.id === deletingAccountId.value) ?? null);
const deleteDialogOpen = ref(false);

function openDeleteDialog(accountId: number) {
    deletingAccountId.value = accountId;
    deleteDialogOpen.value = true;
}

function destroyAccount() {
    if (deletingAccountId.value === null) {
        return;
    }

    deleteForm.delete(route('accounts.destroy', deletingAccountId.value), {
        preserveScroll: true,
        onSuccess: () => {
            deletingAccountId.value = null;
            deleteDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Konta" />

        <template #headerActions>
            <Button as-child>
                <Link :href="route('accounts.create')">Dodaj konto</Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <AccountsSummaryCard :accounts="accounts" />

            <div v-if="accounts.length === 0" class="rounded-xl border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">Nie masz jeszcze żadnych kont.</p>
                <Button as-child class="mt-4">
                    <Link :href="route('accounts.create')">Dodaj pierwsze konto</Link>
                </Button>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="account in accounts"
                    :key="account.id"
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ account.name }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ account.currency.code }}</p>
                        </div>

                        <button
                            type="button"
                            class="rounded-md p-2 text-muted-foreground hover:text-foreground focus:outline-hidden focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            :disabled="deleteForm.processing"
                            @click="openDeleteDialog(account.id)"
                            aria-label="Usuń konto"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="mt-4 flex items-end justify-between gap-4">
                        <div>
                            <p class="text-xs text-muted-foreground">Saldo bieżące</p>
                            <p class="mt-1 text-lg font-semibold tabular-nums">
                                {{ formatMoney(account.current_balance) }} {{ account.currency.symbol ?? 'zł' }}
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <Button variant="secondary" as-child>
                                <Link :href="route('accounts.edit', account.id)">Edytuj</Link>
                            </Button>

                            <Dialog>
                                <DialogTrigger as-child>
                                    <Button
                                        variant="outline"
                                        @click="openAdjustBalance(account.id)"
                                        :disabled="adjustForm.processing"
                                    >
                                        Ustaw saldo
                                    </Button>
                                </DialogTrigger>

                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Ustaw saldo</DialogTitle>
                                        <DialogDescription>
                                            Zmiana ustawi saldo bieżące na podaną wartość. Nie zmieniamy historii transakcji.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <form @submit.prevent="submitAdjustment" class="grid gap-4">
                                        <div class="grid gap-2">
                                            <Label for="new_balance">Nowe saldo</Label>
                                            <Input
                                                id="new_balance"
                                                inputmode="decimal"
                                                v-model="adjustForm.new_balance"
                                                placeholder="np. 1234,56"
                                            />
                                            <InputError :message="adjustForm.errors.new_balance" />
                                        </div>

                                        <div class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                                            <Checkbox
                                                :id="`adjustment_confirmed_${adjustingAccountId ?? 'none'}`"
                                                :checked="adjustmentConfirmed"
                                                :disabled="adjustForm.processing"
                                                @update:checked="(value) => (adjustmentConfirmed = value === true)"
                                            />
                                            <div class="grid gap-1 leading-tight">
                                                <Label :for="`adjustment_confirmed_${adjustingAccountId ?? 'none'}`" class="cursor-pointer">
                                                    Rozumiem, że ta operacja nie zmienia historii transakcji.
                                                </Label>
                                                <p class="text-xs text-muted-foreground">
                                                    Używaj tylko do korekty salda bieżącego.
                                                </p>
                                            </div>
                                        </div>

                                        <DialogFooter>
                                            <Button type="submit" :disabled="!canSubmitAdjustment || adjustForm.processing">
                                                Zapisz
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Usunąć konto?</DialogTitle>
                    <DialogDescription>
                        Konto zostanie usunięte z listy. Transakcje pozostaną w historii, ale będą tylko do odczytu.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="deletingAccount" class="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                    <p class="font-medium">{{ deletingAccount.name }}</p>
                    <p class="mt-1 text-muted-foreground">
                        Saldo bieżące: {{ formatMoney(deletingAccount.current_balance) }} {{ deletingAccount.currency.symbol ?? 'zł' }}
                    </p>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Anuluj</Button>
                    </DialogClose>
                    <Button type="button" variant="destructive" :disabled="deleteForm.processing" @click="destroyAccount">Usuń konto</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

