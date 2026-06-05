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

const barWidth = computed(() => {
    if (props.percent === null) {
        return 0;
    }

    return Math.max(0, Math.min(100, props.percent));
});

const badgeClasses: Record<string, string> = {
    good: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
    warn: 'bg-amber-100 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
    bad: 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300',
    muted: 'bg-muted text-muted-foreground',
};

const barClasses: Record<string, string> = {
    good: 'bg-emerald-500',
    warn: 'bg-amber-500',
    bad: 'bg-red-500',
    muted: 'bg-muted-foreground',
};
</script>

<template>
    <div v-if="percent === null" class="text-muted-foreground">—</div>
    <div v-else class="grid min-w-[5.5rem] gap-1">
        <span
            class="inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
            :class="badgeClasses[tone]"
        >
            {{ percent }}%
        </span>
        <div
            class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="percent"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full rounded-full transition-[width]"
                :class="barClasses[tone]"
                :style="{ width: `${barWidth}%` }"
            />
        </div>
    </div>
</template>
