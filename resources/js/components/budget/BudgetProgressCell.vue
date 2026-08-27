<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    percent: number | null;
    categoryType: 'income' | 'expense';
}>();

const tone = computed(() => {
    if (props.percent === null) {
        return 'muted';
    }

    if (props.categoryType === 'income') {
        return props.percent < 100 ? 'bad' : 'good';
    }

    if (props.percent < 100) {
        return 'good';
    }

    if (props.percent === 100) {
        return 'warn';
    }

    return 'bad';
});

const badgeClasses: Record<string, string> = {
    good: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    warn: 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    bad: 'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
    muted: 'border-border/50 bg-muted text-muted-foreground',
};
</script>

<template>
    <span v-if="percent === null" class="text-muted-foreground">—</span>
    <span v-else class="inline-flex w-fit rounded-full border px-2.5 py-0.5 text-xs font-medium tabular-nums" :class="badgeClasses[tone]">
        {{ percent }}%
    </span>
</template>
