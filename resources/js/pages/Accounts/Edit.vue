<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    currency_id: number;
    opening_balance: string;
    current_balance: string;
    currency: Currency | null;
};

const props = defineProps<{
    account: Account;
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
    opening_balance: displayAmount(props.account.opening_balance),
});

function submit() {
    form.opening_balance = normalizeAmount(form.opening_balance);
    form.patch(route('accounts.update', props.account.id));
}

const deleteForm = useForm({});
const deleteDialogOpen = ref(false);

function destroyAccount() {
    deleteForm.delete(route('accounts.destroy', props.account.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
        },
    });
}

const adjustForm = useForm<{ new_balance: string }>({
    new_balance: displayAmount(props.account.current_balance),
});

const adjustmentConfirmed = ref(false);
const canSubmitAdjustment = computed(() => adjustForm.new_balance.length > 0 && adjustmentConfirmed.value);

function submitAdjustment() {
    adjustForm.new_balance = normalizeAmount(adjustForm.new_balance);
    adjustForm.patch(route('accounts.balance.update', props.account.id), {
        preserveScroll: true,
        onSuccess: () => {
            adjustmentConfirmed.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edytuj konto" />

        <template #headerActions>
            <Button variant="secondary" as-child>
                <Link :href="route('accounts.index')">Wróć</Link>
            </Button>

            <Dialog v-model:open="deleteDialogOpen">
                <DialogTrigger as-child>
                    <Button variant="destructive" :disabled="deleteForm.processing">Usuń konto</Button>
                </DialogTrigger>

                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Usunąć konto?</DialogTitle>
                        <DialogDescription>
                            Konto zostanie usunięte z listy. Transakcje pozostaną w historii, ale będą tylko do odczytu.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                        <p class="font-medium">{{ account.name }}</p>
                        <p class="mt-1 text-muted-foreground">
                            Saldo bieżące: {{ formatMoney(account.current_balance) }} {{ account.currency?.symbol ?? 'zł' }}
                        </p>
                    </div>

                    <DialogFooter>
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">Anuluj</Button>
                        </DialogClose>
                        <Button type="button" variant="destructive" :disabled="deleteForm.processing" @click="destroyAccount">
                            Usuń konto
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
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

                    <Dialog>
                        <DialogTrigger as-child>
                            <Button class="mt-4" variant="outline" :disabled="adjustForm.processing" @click="adjustmentConfirmed = false">
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
                                    <Input id="new_balance" inputmode="decimal" v-model="adjustForm.new_balance" />
                                    <InputError :message="adjustForm.errors.new_balance" />
                                </div>

                                <div class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                                    <Checkbox
                                        id="adjustment_confirmed"
                                        :checked="adjustmentConfirmed"
                                        :disabled="adjustForm.processing"
                                        @update:checked="(value) => (adjustmentConfirmed = value === true)"
                                    />
                                    <div class="grid gap-1 leading-tight">
                                        <Label for="adjustment_confirmed" class="cursor-pointer">
                                            Rozumiem, że ta operacja nie zmienia historii transakcji.
                                        </Label>
                                        <p class="text-xs text-muted-foreground">
                                            Używaj tylko do korekty salda bieżącego.
                                        </p>
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button type="submit" :disabled="!canSubmitAdjustment || adjustForm.processing">Zapisz</Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

