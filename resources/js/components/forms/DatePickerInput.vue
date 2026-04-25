<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { computed } from 'vue';

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

const isoValue = computed<string>({
    get() {
        return ddMmYyyyToIso(props.modelValue);
    },
    set(nextIso) {
        emit('update:modelValue', isoToDdMmYyyy(nextIso));
    },
});
</script>

<template>
    <Input
        :id="id"
        v-model="isoValue"
        type="date"
        :disabled="disabled"
        @change="emit('change')"
        @blur="emit('blur')"
    />
</template>
