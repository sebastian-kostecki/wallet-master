<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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

function destroyAccount() {
    if (!confirm('Czy na pewno chcesz usunąć to konto? Transakcje pozostaną, ale będą tylko do odczytu.')) {
        return;
    }

    deleteForm.delete(route('accounts.destroy', props.account.id));
}

const adjustForm = useForm<{ new_balance: string }>({
    new_balance: displayAmount(props.account.current_balance),
});

const canSubmitAdjustment = computed(() => adjustForm.new_balance.length > 0);

function submitAdjustment() {
    adjustForm.new_balance = normalizeAmount(adjustForm.new_balance);
    adjustForm.patch(route('accounts.balance.update', props.account.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Edytuj konto" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <Heading title="Edytuj konto" :description="`Waluta: ${account.currency?.code ?? 'PLN'}`" />

                <div class="flex gap-2">
                    <Button variant="secondary" as-child>
                        <Link :href="route('accounts.index')">Wróć</Link>
                    </Button>
                    <Button variant="destructive" :disabled="deleteForm.processing" @click="destroyAccount">Usuń konto</Button>
                </div>
            </div>

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
                            <Button class="mt-4" variant="outline" :disabled="adjustForm.processing">Ustaw saldo</Button>
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

