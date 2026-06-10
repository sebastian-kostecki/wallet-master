<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { Check, Pencil, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    annualPlan: string | null;
    monthlyTemplate: string | null;
    currency: CurrencyDisplay;
    inputIdPrefix: string;
    annualPlaceholder: string;
    monthlyPlaceholder: string;
    editLabel: string;
    saveLabel: string;
    cancelLabel: string;
    isEditing: boolean;
}>();

const emit = defineEmits<{
    'start-edit': [];
    cancel: [];
    save: [annualRaw: string, monthlyRaw: string];
}>();

const annualDraft = ref('');
const monthlyDraft = ref('');
const error = ref<string | null>(null);

watch(
    () => props.isEditing,
    (editing) => {
        if (editing) {
            annualDraft.value = props.annualPlan ?? '';
            monthlyDraft.value = props.monthlyTemplate ?? '';
            error.value = null;
        }
    },
);

function isValidAmount(raw: string): boolean {
    const trimmed = raw.trim();
    if (trimmed === '') return true;
    return /^\d+([.,]\d{1,2})?$/.test(trimmed);
}

function onSave() {
    if (!isValidAmount(annualDraft.value) || !isValidAmount(monthlyDraft.value)) {
        error.value = 'invalid';
        return;
    }
    error.value = null;
    emit('save', annualDraft.value, monthlyDraft.value);
}

function onCancel() {
    error.value = null;
    emit('cancel');
}

function onInputKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter') {
        event.preventDefault();
        onSave();
    }
    if (event.key === 'Escape') {
        event.preventDefault();
        onCancel();
    }
}
</script>

<template>
    <div v-if="!isEditing" class="flex items-center gap-1">
        <span class="w-28 shrink-0 text-left tabular-nums">{{ formatMoney(annualPlan, currency) }}</span>
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0" :aria-label="editLabel" @click="emit('start-edit')">
            <Pencil class="h-4 w-4 text-muted-foreground" />
        </Button>
    </div>
    <div v-else class="flex items-center gap-1">
        <Input
            :id="`${inputIdPrefix}-annual`"
            v-model="annualDraft"
            type="text"
            inputmode="decimal"
            class="h-8 w-28 tabular-nums"
            :placeholder="annualPlaceholder"
            :aria-invalid="error !== null"
            @keydown="onInputKeydown"
        />
        <Input
            :id="`${inputIdPrefix}-monthly`"
            v-model="monthlyDraft"
            type="text"
            inputmode="decimal"
            class="h-8 w-28 tabular-nums"
            :placeholder="monthlyPlaceholder"
            :aria-invalid="error !== null"
            @keydown="onInputKeydown"
        />
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0" :aria-label="saveLabel" @click="onSave">
            <Check class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
        </Button>
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0" :aria-label="cancelLabel" @click="onCancel">
            <X class="h-4 w-4 text-muted-foreground" />
        </Button>
    </div>
</template>
