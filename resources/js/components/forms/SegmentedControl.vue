<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { computed } from 'vue';

export type SegmentedControlOption<T extends string | number = string | number> = {
    value: T;
    label: string;
};

const props = withDefaults(
    defineProps<{
        id?: string;
        modelValue: string | number;
        options: SegmentedControlOption[];
        disabled?: boolean;
        ariaLabel?: string;
        columns?: 2 | 3 | 4;
    }>(),
    {
        disabled: false,
        columns: 2,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number];
}>();

const gridClass = computed(() => {
    const columnMap = {
        2: 'grid-cols-2',
        3: 'grid-cols-3',
        4: 'grid-cols-4',
    } as const;

    return columnMap[props.columns];
});
</script>

<template>
    <div
        :id="id"
        role="group"
        :aria-label="ariaLabel"
        :class="cn('grid gap-1 rounded-lg border border-input bg-muted/30 p-1', gridClass)"
    >
        <Button
            v-for="option in options"
            :key="String(option.value)"
            type="button"
            :variant="modelValue === option.value ? 'secondary' : 'ghost'"
            class="h-9 justify-center"
            :aria-pressed="modelValue === option.value"
            :disabled="disabled"
            @click="emit('update:modelValue', option.value)"
        >
            {{ option.label }}
        </Button>
    </div>
</template>
