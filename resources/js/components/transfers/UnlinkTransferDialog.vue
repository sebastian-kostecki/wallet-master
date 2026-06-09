<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        open: boolean;
        disabled?: boolean;
        transferId: string | null;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:open': [open: boolean];
    processing: [processing: boolean];
}>();

const { t } = useI18n();
const processing = ref(false);

watch(
    processing,
    (value) => {
        emit('processing', value);
    },
    { immediate: true },
);

function unlink() {
    if (!props.transferId) {
        return;
    }

    processing.value = true;
    router.post(
        route('transfers.unlink', props.transferId),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                emit('update:open', false);
            },
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('transactions.unlink.title') }}</DialogTitle>
                <DialogDescription>{{ t('transactions.unlink.description') }}</DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <DialogClose as-child>
                    <Button type="button" variant="secondary" :disabled="disabled || processing">
                        {{ t('actions.cancel') }}
                    </Button>
                </DialogClose>
                <Button type="button" :disabled="disabled || processing" :aria-busy="processing || undefined" @click="unlink">
                    {{ t('transactions.unlink.confirm') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
