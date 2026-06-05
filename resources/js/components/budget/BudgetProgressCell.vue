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
    good: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
    warn: 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
    bad: 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300',
    muted: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <span v-if="percent === null" class="text-muted-foreground">—</span>
    <span
        v-else
        class="inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
        :class="badgeClasses[tone]"
    >
        {{ percent }}%
    </span>
</template>
