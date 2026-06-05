<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { Pencil } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    plan: string | null;
    currency: CurrencyDisplay;
    inputId: string;
    placeholder: string;
    editLabel: string;
    saveLabel: string;
    cancelLabel: string;
    isEditing: boolean;
}>();

const emit = defineEmits<{
    'start-edit': [];
    cancel: [];
    save: [rawValue: string];
}>();

const draft = ref('');
const error = ref<string | null>(null);

watch(
    () => props.isEditing,
    (editing) => {
        if (editing) {
            draft.value = props.plan ?? '';
            error.value = null;
        }
    },
);

function isValidAmount(raw: string): boolean {
    const trimmed = raw.trim();

    if (trimmed === '') {
        return true;
    }

    return /^\d+([.,]\d{1,2})?$/.test(trimmed);
}

function onSave() {
    if (!isValidAmount(draft.value)) {
        error.value = 'invalid';
        return;
    }

    error.value = null;
    emit('save', draft.value);
}

function onCancel() {
    error.value = null;
    emit('cancel');
}
</script>

<template>
    <div v-if="!isEditing" class="flex items-center gap-2">
        <span class="tabular-nums">{{ formatMoney(plan, currency) }}</span>
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0"
            :aria-label="editLabel"
            @click="emit('start-edit')"
        >
            <Pencil class="h-4 w-4" />
        </Button>
    </div>
    <div v-else class="flex flex-wrap items-center gap-2">
        <Input
            :id="inputId"
            v-model="draft"
            type="text"
            inputmode="decimal"
            class="h-8 w-28 tabular-nums"
            :placeholder="placeholder"
            :aria-invalid="error !== null"
        />
        <Button type="button" size="sm" @click="onSave">{{ saveLabel }}</Button>
        <Button type="button" size="sm" variant="outline" @click="onCancel">{{ cancelLabel }}</Button>
    </div>
</template>
