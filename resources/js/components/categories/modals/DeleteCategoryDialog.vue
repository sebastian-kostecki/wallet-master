<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        open: boolean;
        disabled?: boolean;
        categoryId: number | null;
        categoryName?: string | null;
        icon?: string | null;
        color?: string | null;
    }>(),
    {
        disabled: false,
        categoryName: null,
        icon: null,
        color: null,
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
    if (props.categoryId === null) {
        return;
    }

    form.delete(route('categories.destroy', props.categoryId), {
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
                <DialogTitle>{{ t('categories.delete.title') }}</DialogTitle>
                <DialogDescription>{{ t('categories.delete.description') }}</DialogDescription>
            </DialogHeader>

            <div
                v-if="categoryName && icon && color"
                class="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
            >
                <CategoryBadge :name="categoryName" :icon="icon" :color="color" size="md" />
            </div>
            <div v-else-if="categoryName" class="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                <p class="font-medium">{{ categoryName }}</p>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button type="button" variant="secondary">{{ t('actions.cancel') }}</Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="destructive"
                    :disabled="disabled || form.processing"
                    :aria-busy="form.processing || undefined"
                    @click="destroy"
                >
                    {{ t('categories.delete.confirm') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
