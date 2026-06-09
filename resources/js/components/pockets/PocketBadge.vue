<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name?: string;
        icon: string;
        color: string;
        size?: 'sm' | 'md';
        showName?: boolean;
    }>(),
    {
        size: 'md',
        showName: true,
    },
);

const tileSizeClass = computed(() => (props.size === 'sm' ? 'h-6 w-6' : 'h-8 w-8'));
const iconSizeClass = computed(() => (props.size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4'));

const tileStyle = computed(() => ({
    backgroundColor: `${props.color}26`,
    color: props.color,
}));
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-2">
        <span class="inline-flex shrink-0 items-center justify-center rounded-md" :class="tileSizeClass" :style="tileStyle" aria-hidden="true">
            <Icon :name="icon" :class="iconSizeClass" :color="color" />
        </span>
        <span v-if="showName && name" class="truncate text-sm font-medium">{{ name }}</span>
    </span>
</template>
