<script setup lang="ts">
import { Landmark } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        variant: 'capital' | 'interest';
        size?: 'sm' | 'md';
    }>(),
    {
        size: 'md',
    },
);

const { t } = useI18n();

const tileSizeClass = computed(() => (props.size === 'sm' ? 'h-6 w-6' : 'h-8 w-8'));
const iconSizeClass = computed(() => (props.size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4'));
const labelClass = computed(() =>
    props.variant === 'interest'
        ? 'inline-flex items-center rounded-full border border-border/40 bg-muted/60 px-1.5 py-0.5 text-[9px] font-medium text-muted-foreground'
        : 'truncate text-sm font-medium',
);

const label = computed(() => (props.variant === 'interest' ? t('deposits.badge.interest') : t('deposits.badge.capital')));
</script>

<template>
    <span v-if="variant === 'interest'" :class="labelClass">{{ label }}</span>
    <span v-else class="inline-flex min-w-0 max-w-full items-center gap-2">
        <span
            class="inline-flex shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
            :class="tileSizeClass"
            aria-hidden="true"
        >
            <Landmark :class="iconSizeClass" stroke-width="1.7" />
        </span>
        <span :class="labelClass">{{ label }}</span>
    </span>
</template>
