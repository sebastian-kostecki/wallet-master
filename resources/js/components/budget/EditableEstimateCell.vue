<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { ArrowRightLeft, Check, Pencil, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        plan: string | null;
        currency: CurrencyDisplay;
        inputId: string;
        placeholder: string;
        editLabel: string;
        alignLabel: string;
        saveLabel: string;
        cancelLabel: string;
        isEditing: boolean;
        mode?: 'plan' | 'align' | null;
        alignValue?: string | null;
        showAlignButton?: boolean;
    }>(),
    {
        mode: null,
        alignValue: null,
        showAlignButton: false,
    },
);

const emit = defineEmits<{
    'start-edit': [];
    'start-align': [];
    cancel: [];
    save: [rawValue: string];
}>();

const draft = ref('');
const error = ref<string | null>(null);

watch(
    () => [props.isEditing, props.mode] as const,
    ([editing, mode]) => {
        if (editing) {
            draft.value = mode === 'align' ? (props.alignValue ?? '') : (props.plan ?? '');
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
        <span class="w-28 shrink-0 text-left tabular-nums">{{ formatMoney(plan, currency) }}</span>
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0" :aria-label="editLabel" @click="emit('start-edit')">
            <Pencil class="h-4 w-4 text-muted-foreground" />
        </Button>
        <Button
            v-if="showAlignButton"
            type="button"
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0"
            :aria-label="alignLabel"
            @click="emit('start-align')"
        >
            <ArrowRightLeft class="h-4 w-4 text-muted-foreground" />
        </Button>
    </div>
    <div v-else class="flex items-center gap-1">
        <Input
            :id="inputId"
            v-model="draft"
            type="text"
            inputmode="decimal"
            class="h-8 w-28 tabular-nums"
            :placeholder="placeholder"
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
