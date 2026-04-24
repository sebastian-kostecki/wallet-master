<script setup lang="ts" generic="TValue extends string | number">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Check, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

export type DropdownOption<TValue extends string | number> = {
    value: TValue;
    label: string;
};

const props = withDefaults(
    defineProps<{
        id: string;
        modelValue: TValue | null;
        options: DropdownOption<TValue>[];
        placeholder: string;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: TValue];
}>();

const selected = computed(() => {
    return props.options.find((o) => o.value === props.modelValue) ?? null;
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button :id="props.id" type="button" variant="outline" class="h-10 w-full justify-between px-3" :disabled="props.disabled">
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

