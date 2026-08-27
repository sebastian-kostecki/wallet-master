<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    planIncome: string;
    actualIncome: string;
    planExpense: string;
    actualExpense: string;
}>();

const { t } = useI18n();

function toNumber(value: string): number {
    const parsed = Number(String(value).replace(',', '.'));

    return Number.isNaN(parsed) ? 0 : Math.abs(parsed);
}

function width(value: string, max: number): string {
    if (max <= 0) {
        return '0%';
    }

    return `${Math.min(100, (toNumber(value) / max) * 100)}%`;
}

const groups = computed(() => [
    {
        key: 'income',
        label: t('budget.summary.income'),
        plan: props.planIncome,
        actual: props.actualIncome,
        max: Math.max(toNumber(props.planIncome), toNumber(props.actualIncome)),
        actualClass: 'bg-emerald-500/40',
    },
    {
        key: 'expense',
        label: t('budget.summary.expense'),
        plan: props.planExpense,
        actual: props.actualExpense,
        max: Math.max(toNumber(props.planExpense), toNumber(props.actualExpense)),
        actualClass: 'bg-red-500/40',
    },
]);
</script>

<template>
    <div class="grid gap-4" aria-hidden="true">
        <div v-for="group in groups" :key="group.key" class="grid gap-2">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">{{ group.label }}</p>
            <div class="grid gap-1.5">
                <div class="flex items-center gap-2">
                    <span class="w-20 shrink-0 text-xs text-muted-foreground">{{ t('dashboard.chart.plan') }}</span>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-foreground/[0.06]">
                        <div class="h-full rounded-full bg-foreground/15" :style="{ width: width(group.plan, group.max) }" />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-20 shrink-0 text-xs text-muted-foreground">{{ t('dashboard.chart.actual') }}</span>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-foreground/[0.06]">
                        <div class="h-full rounded-full" :class="group.actualClass" :style="{ width: width(group.actual, group.max) }" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
