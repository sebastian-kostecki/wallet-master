<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Currency = {
    code: string;
};

type Account = {
    type: string;
    current_balance: string;
    currency: Currency;
};

const props = defineProps<{
    accounts: Account[];
}>();

const { t } = useI18n();

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

const savingsAccountsInPln = computed(() => accountsInPln.value.filter((a) => a.type === 'savings'));

const parsedBalances = computed(() =>
    accountsInPln.value.map((a) => ({
        value: parseAmount(a.current_balance),
    })),
);

const parsedSavingsBalances = computed(() =>
    savingsAccountsInPln.value.map((a) => ({
        value: parseAmount(a.current_balance),
    })),
);

const invalidBalancesCount = computed(() => parsedBalances.value.filter((b) => b.value === null).length);

const totalBalance = computed(() => parsedBalances.value.reduce((sum, b) => sum + (b.value ?? 0), 0));

const savingsTotalBalance = computed(() => parsedSavingsBalances.value.reduce((sum, b) => sum + (b.value ?? 0), 0));

const formattedTotal = computed(() => `${money.format(totalBalance.value)} ${t('currency.defaultSymbol')}`);

const formattedSavingsTotal = computed(() => `${money.format(savingsTotalBalance.value)} ${t('currency.defaultSymbol')}`);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('accounts.summary.title') }}</CardTitle>
            <CardDescription>{{ t('accounts.summary.description') }}</CardDescription>
        </CardHeader>

        <CardContent class="grid gap-3">
            <div>
                <p class="text-xs text-muted-foreground">{{ t('accounts.summary.totalPln') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ formattedTotal }}</p>
            </div>

            <div>
                <p class="text-xs text-muted-foreground">{{ t('accounts.summary.savingsPln') }}</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">{{ formattedSavingsTotal }}</p>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
                <p>{{ t('accounts.summary.countPln', { count: accountsInPln.length }) }}</p>
                <p v-if="invalidBalancesCount > 0">{{ t('accounts.summary.invalidSkipped') }}</p>
                <p v-else-if="accountsInPln.length === 0">{{ t('accounts.summary.addToSeeSummary') }}</p>
            </div>
        </CardContent>
    </Card>
</template>
