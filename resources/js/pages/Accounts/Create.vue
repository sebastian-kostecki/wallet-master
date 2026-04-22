<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Currency = {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    precision: number;
};

const props = defineProps<{
    currencies: Currency[];
}>();

const pln = computed(() => props.currencies[0]);

const form = useForm<{
    name: string;
    currency_id: number | null;
    opening_balance: string;
}>({
    name: '',
    currency_id: pln.value?.id ?? null,
    opening_balance: '0,00',
});

function normalizeAmount(input: string) {
    return input.replace(/\s/g, '').replace(',', '.');
}

function submit() {
    form.opening_balance = normalizeAmount(form.opening_balance);
    form.post(route('accounts.store'));
}
</script>

<template>
    <AppLayout>
        <Head title="Dodaj konto" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex items-center justify-between gap-4">
                <Heading title="Dodaj konto" description="Utwórz nowe konto w aplikacji." />

                <Button variant="secondary" as-child>
                    <Link :href="route('accounts.index')">Wróć</Link>
                </Button>
            </div>

            <div class="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <form @submit.prevent="submit" class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name">Nazwa</Label>
                        <Input id="name" v-model="form.name" required autofocus placeholder="np. Konto główne" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="currency">Waluta (MVP)</Label>
                        <Input id="currency" :model-value="pln?.code ?? 'PLN'" disabled />
                        <InputError :message="form.errors.currency_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="opening_balance">Saldo początkowe</Label>
                        <Input id="opening_balance" inputmode="decimal" v-model="form.opening_balance" placeholder="np. 1000,00" />
                        <InputError :message="form.errors.opening_balance" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="form.processing">Zapisz</Button>
                        <p class="text-sm text-muted-foreground">W MVP saldo bieżące startuje od salda początkowego.</p>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

