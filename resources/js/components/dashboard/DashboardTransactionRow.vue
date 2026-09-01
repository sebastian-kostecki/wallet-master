<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { formatMoney, signedMoneyClass, type CurrencyDisplay } from '@/lib/formatMoney';
import { computed } from 'vue';

type Transaction = {
    id: number;
    booked_at: string;
    date: string;
    date_relative: string;
    description: string;
    amount: string | number;
    category: { name: string; icon: string; color: string } | null;
    currency: { symbol: string | null; precision: number } | null;
};

const props = defineProps<{
    transaction: Transaction;
}>();

const displayDate = computed(() => props.transaction.date_relative || props.transaction.booked_at || props.transaction.date);

const displayCurrency = computed((): CurrencyDisplay | null => {
    const currency = props.transaction.currency;

    if (!currency) {
        return null;
    }

    return {
        symbol: currency.symbol ?? '',
        precision: currency.precision,
    };
});
</script>

<template>
    <li class="flex items-center justify-between gap-3 border-b border-border/50 px-3 py-2.5 last:border-b-0">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium">{{ transaction.description }}</p>
            <div class="mt-1 flex min-w-0 items-center gap-2 text-xs text-muted-foreground">
                <span class="shrink-0">{{ displayDate }}</span>
                <CategoryBadge
                    v-if="transaction.category"
                    :name="transaction.category.name"
                    :icon="transaction.category.icon"
                    :color="transaction.category.color"
                    size="sm"
                />
            </div>
        </div>
        <p class="shrink-0 text-sm font-medium tabular-nums" :class="signedMoneyClass(transaction.amount)">
            {{ formatMoney(transaction.amount, displayCurrency) }}
        </p>
    </li>
</template>
