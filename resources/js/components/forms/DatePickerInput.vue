<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Calendar from '@/components/ui/calendar/Calendar.vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Calendar as CalendarIcon, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    id?: string;
    modelValue: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'change'): void;
    (e: 'blur'): void;
}>();

function ddMmYyyyToIso(value: string): string {
    const trimmed = value.trim();
    if (trimmed === '') {
        return '';
    }

    const m = /^(\d{2})-(\d{2})-(\d{4})$/.exec(trimmed);
    if (!m) {
        return '';
    }

    const [, dd, mm, yyyy] = m;
    return `${yyyy}-${mm}-${dd}`;
}

function isoToDdMmYyyy(value: string): string {
    const trimmed = value.trim();
    if (trimmed === '') {
        return '';
    }

    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(trimmed);
    if (!m) {
        return '';
    }

    const [, yyyy, mm, dd] = m;
    return `${dd}-${mm}-${yyyy}`;
}

const open = ref(false);

const isoValue = computed<string>({
    get() {
        return ddMmYyyyToIso(props.modelValue);
    },
    set(nextIso) {
        emit('update:modelValue', isoToDdMmYyyy(nextIso));
    },
});

const displayValue = computed(() => props.modelValue.trim());

function clear() {
    emit('update:modelValue', '');
    emit('change');
}

function onSelect(nextIso: string) {
    isoValue.value = nextIso;
    emit('change');
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                :disabled="disabled"
                :class="cn('h-10 w-full justify-between px-3 text-left font-normal', !displayValue ? 'text-muted-foreground' : '')"
                @blur="emit('blur')"
            >
                <span class="truncate">
                    {{ displayValue || '—' }}
                </span>
                <span class="ml-2 inline-flex items-center gap-1">
                    <button
                        v-if="displayValue && !disabled"
                        type="button"
                        class="inline-flex h-7 w-7 items-center justify-center rounded hover:bg-muted"
                        :aria-label="'Clear date'"
                        @click.stop="clear"
                    >
                        <X class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <CalendarIcon class="h-4 w-4 opacity-70" aria-hidden="true" />
                </span>
            </Button>
        </PopoverTrigger>

        <PopoverContent class="p-3" align="start">
            <Calendar :model-value="isoValue" @update:model-value="onSelect" />
        </PopoverContent>
    </Popover>
</template>
