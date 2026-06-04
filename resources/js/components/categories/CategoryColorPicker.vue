<script setup lang="ts">
import { cn } from '@/lib/utils';

type ColorOption = {
    value: string;
};

const props = defineProps<{
    colors: ColorOption[];
    modelValue: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div class="grid grid-cols-6 gap-2 sm:grid-cols-8" role="listbox" aria-label="Category color">
        <button
            v-for="color in props.colors"
            :key="color.value"
            type="button"
            role="option"
            :aria-selected="props.modelValue === color.value"
            class="h-8 w-8 rounded-full border-2 transition-shadow focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :class="cn(props.modelValue === color.value ? 'border-foreground ring-2 ring-ring ring-offset-2' : 'border-transparent')"
            :style="{ backgroundColor: color.value }"
            @click="emit('update:modelValue', color.value)"
        />
    </div>
</template>
