<script setup lang="ts">
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { computed } from 'vue';

const props = defineProps<{
    percent: number | null;
    balance: string;
    targetAmount: string | null;
    currency: CurrencyDisplay;
}>();

const normalizedPercent = computed(() => {
    const raw = props.percent ?? 0;

    return Math.max(0, Math.min(100, raw));
});
</script>

<template>
    <div v-if="targetAmount !== null" class="grid gap-1">
        <div
            class="h-2 w-full overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="Math.round(normalizedPercent)"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full rounded-full bg-emerald-500 transition-[width] duration-200 ease-out"
                :style="{ width: `${normalizedPercent}%` }"
            />
        </div>
        <p class="text-xs text-muted-foreground">
            {{ formatMoney(balance, currency) }} / {{ formatMoney(targetAmount, currency) }}
        </p>
    </div>
</template>
