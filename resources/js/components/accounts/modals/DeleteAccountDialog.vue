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
        accountId: number | null;
        accountName?: string | null;
        currentBalance?: string | null;
        currencySymbol?: string | null;
        formatMoney?: (value: string) => string;
    }>(),
    {
        disabled: false,
        accountName: null,
        currentBalance: null,
        currencySymbol: null,
        formatMoney: (value: string) => value,
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
    if (props.accountId === null) {
        return;
    }

    form.delete(route('accounts.destroy', props.accountId), {
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
                <DialogTitle>{{ t('accounts.delete.title') }}</DialogTitle>
                <DialogDescription>{{ t('accounts.delete.description') }}</DialogDescription>
            </DialogHeader>

            <div v-if="accountName" class="rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                <p class="font-medium">{{ accountName }}</p>
                <p v-if="currentBalance !== null" class="mt-1 text-muted-foreground">
                    {{
                        t('accounts.delete.currentBalanceLine', {
                            amount: formatMoney(currentBalance),
                            symbol: currencySymbol ?? t('currency.defaultSymbol'),
                        })
                    }}
                </p>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button type="button" variant="secondary">{{ t('actions.cancel') }}</Button>
                </DialogClose>
                <Button type="button" variant="destructive" :disabled="disabled || form.processing" @click="destroy">{{ t('accounts.delete.confirm') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

