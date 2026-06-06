<script setup lang="ts">
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        open: boolean;
        disabled?: boolean;
        pocketId: number | null;
        pocketName?: string | null;
        icon?: string | null;
        color?: string | null;
        isArchived?: boolean;
    }>(),
    {
        disabled: false,
        pocketName: null,
        icon: null,
        color: null,
        isArchived: false,
    },
);

const emit = defineEmits<{
    'update:open': [open: boolean];
    processing: [processing: boolean];
    success: [];
}>();

const { t } = useI18n();

const form = useForm({ is_archived: false });

const dialogKey = computed(() => (props.isArchived ? 'unarchive' : 'archive'));

watch(
    () => form.processing,
    (processing) => {
        emit('processing', processing);
    },
    { immediate: true },
);

function submit(): void {
    if (props.pocketId === null) {
        return;
    }

    form.is_archived = !props.isArchived;
    form.patch(route('pockets.update', props.pocketId), {
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
                <DialogTitle>{{ t(`pockets.${dialogKey}.title`) }}</DialogTitle>
                <DialogDescription>{{ t(`pockets.${dialogKey}.description`) }}</DialogDescription>
            </DialogHeader>

            <div
                v-if="pocketName && icon && color"
                class="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
            >
                <PocketBadge :name="pocketName" :icon="icon" :color="color" />
            </div>
            <div v-else-if="pocketName" class="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                <p class="font-medium">{{ pocketName }}</p>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button type="button" variant="secondary">{{ t('actions.cancel') }}</Button>
                </DialogClose>
                <Button
                    type="button"
                    :variant="isArchived ? 'secondary' : 'default'"
                    :disabled="disabled || form.processing"
                    :aria-busy="form.processing || undefined"
                    @click="submit"
                >
                    {{ t(`pockets.${dialogKey}.confirm`) }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
