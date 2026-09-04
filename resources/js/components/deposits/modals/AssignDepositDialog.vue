<script setup lang="ts">
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type AssignableDeposit = {
    id: number;
    name: string | null;
    principal: string;
    status: 'active' | 'closed';
    currency_id: number;
};

type Transaction = {
    id: number;
    description: string;
    amount: string | number;
    currency_id?: number;
    currency?: { id: number } | null;
};

type DepositMovementKind = 'open' | 'close_principal' | 'interest';

const props = defineProps<{
    open: boolean;
    transaction: Transaction | null;
    assignableDeposits: AssignableDeposit[];
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();

const form = useForm<{
    kind: DepositMovementKind | null;
    deposit_id: number | null;
    name: string;
    matures_at: string;
}>({
    kind: null,
    deposit_id: null,
    name: '',
    matures_at: '',
});

const kindOptions = computed<DropdownOption<DepositMovementKind>[]>(() => [
    { value: 'open', label: t('deposits.assign.kinds.open') },
    { value: 'close_principal', label: t('deposits.assign.kinds.close_principal') },
    { value: 'interest', label: t('deposits.assign.kinds.interest') },
]);

const filteredDeposits = computed(() => {
    const currencyId = props.transaction?.currency_id ?? props.transaction?.currency?.id;
    const kind = form.kind;

    if (currencyId === undefined || kind === null || kind === 'open') {
        return [];
    }

    return props.assignableDeposits.filter((deposit) => {
        if (deposit.currency_id !== currencyId) {
            return false;
        }

        if (kind === 'close_principal') {
            return deposit.status === 'active';
        }

        return deposit.status === 'active' || deposit.status === 'closed';
    });
});

const depositOptions = computed<DropdownOption<number>[]>(() =>
    filteredDeposits.value.map((deposit) => ({
        value: deposit.id,
        label: deposit.name?.trim() || t('deposits.fallbackName'),
    })),
);

const showDepositField = computed(() => form.kind !== null && form.kind !== 'open');
const showOpenFields = computed(() => form.kind === 'open');

function resetForm(): void {
    form.clearErrors();
    form.reset();
    form.kind = null;
    form.deposit_id = null;
    form.name = '';
    form.matures_at = '';
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);

watch(
    () => form.kind,
    () => {
        form.deposit_id = filteredDeposits.value[0]?.id ?? null;
    },
);

function onOpenChange(value: boolean): void {
    if (form.processing && !value) {
        return;
    }

    emit('update:open', value);
}

function submit(): void {
    if (props.transaction === null || form.kind === null) {
        return;
    }

    form.transform((data) => ({
        kind: data.kind,
        deposit_id: data.kind === 'open' ? null : data.deposit_id,
        name: data.kind === 'open' && data.name.trim() !== '' ? data.name.trim() : null,
        matures_at: data.kind === 'open' && data.matures_at.trim() !== '' ? data.matures_at.trim() : null,
    })).post(route('deposits.assign', props.transaction.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('deposits.assign.title') }}</DialogTitle>
                <DialogDescription>{{ t('deposits.assign.description') }}</DialogDescription>
            </DialogHeader>

            <div v-if="transaction" class="rounded-xl border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                <p class="font-medium">{{ transaction.description }}</p>
            </div>

            <form class="grid gap-6" @submit.prevent="submit">
                <FormField for-id="assign_kind" :label="t('deposits.assign.kind.label')" :error="form.errors.kind">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="assign_kind"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.kind"
                            :options="kindOptions"
                            :placeholder="t('deposits.assign.kind.placeholder')"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.kind = value)"
                        />
                    </template>
                </FormField>

                <FormField
                    v-if="showDepositField"
                    for-id="assign_deposit"
                    :label="t('deposits.assign.deposit.label')"
                    :error="form.errors.deposit_id"
                >
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="assign_deposit"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.deposit_id"
                            :options="depositOptions"
                            :placeholder="t('deposits.assign.deposit.placeholder')"
                            :disabled="form.processing || depositOptions.length === 0"
                            @update:model-value="(value) => (form.deposit_id = value)"
                        />
                    </template>
                </FormField>

                <template v-if="showOpenFields">
                    <FormField for-id="assign_name" :label="t('deposits.form.name.label')" :error="form.errors.name">
                        <template #default="{ errorId, hasError }">
                            <Input
                                id="assign_name"
                                v-model="form.name"
                                :placeholder="t('deposits.form.name.placeholder')"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                            />
                        </template>
                    </FormField>

                    <FormField for-id="assign_matures_at" :label="t('deposits.form.matures.label')" :error="form.errors.matures_at">
                        <template #default="{ errorId, hasError }">
                            <Input
                                id="assign_matures_at"
                                v-model="form.matures_at"
                                placeholder="DD-MM-YYYY"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                            />
                        </template>
                    </FormField>
                </template>

                <DialogFooter>
                    <Button type="button" variant="secondary" :disabled="form.processing" @click="onOpenChange(false)">
                        {{ t('actions.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing || form.kind === null" :aria-busy="form.processing || undefined">
                        {{ t('deposits.actions.assign') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
