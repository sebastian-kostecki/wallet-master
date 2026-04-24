<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        open: boolean;
        disabled?: boolean;
        accountId: number | null;
        initialNewBalance: string | null;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:open': [open: boolean];
    processing: [processing: boolean];
    success: [];
}>();

const { t } = useI18n();

const form = useForm<{ new_balance: string }>({
    new_balance: '',
});

const adjustmentConfirmed = ref(false);

const checkboxId = computed(() => `adjustment_confirmed_${props.accountId ?? 'none'}`);

function normalizeAmount(input: string) {
    return input.replace(/\s/g, '').replace(',', '.');
}

const canSubmit = computed(() => {
    return props.accountId !== null && form.new_balance.length > 0 && adjustmentConfirmed.value;
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        adjustmentConfirmed.value = false;
        form.clearErrors();
        form.new_balance = props.initialNewBalance ?? '';
    },
);

watch(
    () => form.processing,
    (processing) => {
        emit('processing', processing);
    },
    { immediate: true },
);

function submit() {
    if (props.accountId === null) {
        return;
    }

    form.new_balance = normalizeAmount(form.new_balance);
    form.patch(route('accounts.balance.update', props.accountId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            adjustmentConfirmed.value = false;
            emit('update:open', false);
            emit('success');
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent v-if="accountId !== null">
            <DialogHeader>
                <DialogTitle>{{ t('accounts.adjust.title') }}</DialogTitle>
                <DialogDescription>{{ t('accounts.adjust.description') }}</DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="new_balance">{{ t('accounts.adjust.newBalanceLabel') }}</Label>
                    <Input id="new_balance" inputmode="decimal" v-model="form.new_balance" :placeholder="t('accounts.adjust.newBalancePlaceholder')" />
                    <InputError :message="form.errors.new_balance" />
                </div>

                <div class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                    <Checkbox
                        :id="checkboxId"
                        :checked="adjustmentConfirmed"
                        :disabled="disabled || form.processing"
                        @update:checked="(value) => (adjustmentConfirmed = value === true)"
                    />
                    <div class="grid gap-1 leading-tight">
                        <Label :for="checkboxId" class="cursor-pointer">{{ t('accounts.adjust.confirmLabel') }}</Label>
                        <p class="text-xs text-muted-foreground">{{ t('accounts.adjust.confirmHint') }}</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="!canSubmit || disabled || form.processing">{{ t('actions.save') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

