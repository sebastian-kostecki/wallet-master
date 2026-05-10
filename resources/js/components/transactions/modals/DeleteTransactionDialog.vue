<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        open: boolean;
        disabled?: boolean;
        transactionId: number | null;
        description?: string | null;
        isTransfer?: boolean;
    }>(),
    {
        disabled: false,
        description: null,
        isTransfer: false,
    },
);

const emit = defineEmits<{
    'update:open': [open: boolean];
    processing: [processing: boolean];
    success: [];
}>();

const { t } = useI18n();

const form = useForm({});

watch(
    () => form.processing,
    (processing) => {
        emit('processing', processing);
    },
    { immediate: true },
);

function destroy() {
    if (props.transactionId === null) {
        return;
    }

    form.delete(route('transactions.destroy', props.transactionId), {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            emit('success');
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('transactions.delete.title') }}</DialogTitle>
                <DialogDescription>
                    {{ isTransfer ? t('transactions.delete.descriptionTransfer') : t('transactions.delete.description') }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="description && description.trim() !== ''"
                class="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
            >
                <p class="font-medium">{{ description }}</p>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button type="button" variant="secondary">{{ t('actions.cancel') }}</Button>
                </DialogClose>
                <Button type="button" variant="destructive" :disabled="disabled || form.processing" @click="destroy">
                    {{ t('transactions.delete.confirm') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
