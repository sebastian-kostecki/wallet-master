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
            tileClass: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        };
    }

    if (props.variant === 'expense') {
        return {
            icon: 'arrow-down-left',
            color: '#dc2626',
            tileClass: 'bg-red-500/10 text-red-600 dark:text-red-400',
        };
    }

    return {
        icon: 'scale',
        color: '#71717a',
        tileClass: 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400',
    };
});
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-2">
        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="config.tileClass" aria-hidden="true">
            <Icon :name="config.icon" class="h-4 w-4" :color="config.color" :stroke-width="1.7" />
        </span>
        <span class="font-medium">{{ label }}</span>
    </span>
</template>
