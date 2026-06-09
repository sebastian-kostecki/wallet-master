<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    variant: 'income' | 'expense' | 'balance';
}>();

const config = computed(() => {
    if (props.variant === 'income') {
        return {
            icon: 'arrow-up-right',
            color: '#10b981',
            tileClass: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400',
        };
    }

    if (props.variant === 'expense') {
        return {
            icon: 'arrow-down-left',
            color: '#e11d48',
            tileClass: 'bg-rose-50 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400',
        };
    }

    return {
        icon: 'scale',
        color: '#6366f1',
        tileClass: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/30 dark:text-indigo-400',
    };
});
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-2">
        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md" :class="config.tileClass" aria-hidden="true">
            <Icon :name="config.icon" class="h-4 w-4" :color="config.color" />
        </span>
        <span class="font-medium">{{ label }}</span>
    </span>
</template>
