<script setup lang="ts" generic="TValue extends string | number">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Check, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

export type DropdownOption<TValue extends string | number> = {
    value: TValue;
    label: string;
    disabled?: boolean;
};

const props = withDefaults(
    defineProps<{
        id: string;
        modelValue: TValue | null;
        options: DropdownOption<TValue>[];
        placeholder: string;
        disabled?: boolean;
        size?: 'sm' | 'md';
        ariaInvalid?: boolean;
        ariaDescribedby?: string;
        ariaLabelledby?: string;
    }>(),
    {
        disabled: false,
        size: 'md',
        ariaInvalid: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: TValue];
}>();

const selected = computed(() => {
    return props.options.find((o) => o.value === props.modelValue) ?? null;
});

const triggerClass = computed(() => {
    return props.size === 'sm' ? 'h-9 px-2 text-sm' : 'h-10 px-3';
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                :id="props.id"
                type="button"
                variant="outline"
                :class="['w-full justify-between', triggerClass]"
                :disabled="props.disabled"
                :aria-invalid="props.ariaInvalid ? 'true' : undefined"
                :aria-describedby="props.ariaDescribedby"
                :aria-labelledby="props.ariaLabelledby"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <slot name="trigger-leading" :selected="selected" />
                    <span class="truncate text-left">
                        {{ selected?.label ?? props.placeholder }}
                    </span>
                </span>
                <ChevronDown class="h-4 w-4 shrink-0 opacity-60" aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" class="w-[--radix-dropdown-menu-trigger-width] min-w-56">
            <DropdownMenuItem
                v-for="option in props.options"
                :key="String(option.value)"
                class="cursor-pointer justify-between"
                :disabled="Boolean(option.disabled)"
                @select="() => emit('update:modelValue', option.value)"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <slot name="option-leading" :option="option" />
                    <span class="truncate">{{ option.label }}</span>
                </span>
                <Check v-if="props.modelValue === option.value" class="h-4 w-4 opacity-70" aria-hidden="true" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

