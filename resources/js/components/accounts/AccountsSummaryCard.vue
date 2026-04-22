<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';

type Currency = {
    code: string;
};

type Account = {
    current_balance: string;
    currency: Currency;
};

const props = defineProps<{
    accounts: Account[];
}>();

const money = new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function parseAmount(input: string): number | null {
    const normalized = input.trim().replace(/\s/g, '').replace(',', '.');
    const parsed = Number(normalized);

    if (Number.isNaN(parsed)) {
        return null;
    }

    return parsed;
}

const accountsInPln = computed(() => props.accounts.filter((a) => a.currency.code === 'PLN'));

const parsedBalances = computed(() =>
    accountsInPln.value.map((a) => ({
        value: parseAmount(a.current_balance),
    })),
);

const invalidBalancesCount = computed(() => parsedBalances.value.filter((b) => b.value === null).length);

const totalBalance = computed(() => parsedBalances.value.reduce((sum, b) => sum + (b.value ?? 0), 0));

const formattedTotal = computed(() => `${money.format(totalBalance.value)} zł`);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Podsumowanie</CardTitle>
            <CardDescription>Suma sald wszystkich kont w PLN.</CardDescription>
        </CardHeader>

        <CardContent class="grid gap-3">
            <div>
                <p class="text-xs text-muted-foreground">Suma sald (PLN)</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ formattedTotal }}</p>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
                <p>Liczba kont (PLN): {{ accountsInPln.length }}</p>
                <p v-if="invalidBalancesCount > 0">Niektóre salda pominięto w sumie.</p>
                <p v-else-if="accountsInPln.length === 0">Dodaj konto, aby zobaczyć podsumowanie.</p>
            </div>
        </CardContent>
    </Card>
</template>

