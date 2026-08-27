<script setup lang="ts">
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { normalizeAmount } from '@/lib/money';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type BankAccount = {
    id: number;
    name: string;
    bank_icon_url?: string | null;
};

type Deposit = {
    id: number;
    name: string | null;
    principal: string;
    opened_at: string;
    matures_at: string | null;
    source_account_id: number;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    deposit: Deposit | null;
    bankAccounts: BankAccount[];
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();

const form = useForm<{
    name: string;
    principal: string;
    source_account_id: number | null;
    opened_at: string;
    matures_at: string;
}>({
    name: '',
    principal: '0,00',
    source_account_id: null,
    opened_at: '',
    matures_at: '',
});

const title = computed(() => t(props.mode === 'edit' ? 'deposits.form.editTitle' : 'deposits.form.createTitle'));

const description = computed(() => {
    if (props.mode === 'edit') {
        return props.deposit?.name?.trim() || t('deposits.fallbackName');
    }

    return t('deposits.form.createDescription');
});

const bankAccountOptions = computed<DropdownOption<number>[]>(() =>
    props.bankAccounts.map((account) => ({ value: account.id, label: account.name })),
);

function todayDdMmYyyy(): string {
    const now = new Date();
    const d = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));

    return `${String(d.getUTCDate()).padStart(2, '0')}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${d.getUTCFullYear()}`;
}

function isoToDdMmYyyy(input: string): string {
    const trimmed = input.trim();
    const datePart = /^\d{4}-\d{2}-\d{2}/.test(trimmed) ? trimmed.slice(0, 10) : trimmed;
    const parts = datePart.split('-');

    if (parts.length !== 3) {
        return input;
    }

    const [yyyy, mm, dd] = parts;

    return `${dd}-${mm}-${yyyy}`;
}

function resetForm(): void {
    form.clearErrors();

    if (props.mode === 'edit' && props.deposit) {
        form.name = props.deposit.name ?? '';
        form.matures_at = props.deposit.matures_at ? isoToDdMmYyyy(props.deposit.matures_at) : '';
        return;
    }

    form.reset();
    form.name = '';
    form.principal = '0,00';
    form.source_account_id = props.bankAccounts[0]?.id ?? null;
    form.opened_at = todayDdMmYyyy();
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

function onOpenChange(value: boolean): void {
    if (form.processing && !value) {
        return;
    }

    emit('update:open', value);
}

function submit(): void {
    const visit = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    };

    if (props.mode === 'edit' && props.deposit) {
        form.transform((data) => ({
            name: data.name.trim() === '' ? null : data.name.trim(),
            matures_at: data.matures_at.trim() === '' ? null : data.matures_at.trim(),
        })).patch(route('deposits.update', props.deposit.id), visit);
        return;
    }

    form.transform((data) => ({
        name: data.name.trim() === '' ? null : data.name.trim(),
        principal: normalizeAmount(data.principal),
        source_account_id: data.source_account_id,
        opened_at: data.opened_at.trim(),
        matures_at: data.matures_at.trim() === '' ? null : data.matures_at.trim(),
    })).post(route('deposits.store'), visit);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <form class="grid gap-6" @submit.prevent="submit">
                <FormField for-id="deposit_name" :label="t('deposits.form.name.label')" :error="form.errors.name">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="deposit_name"
                            v-model="form.name"
                            autofocus
                            :placeholder="t('deposits.form.name.placeholder')"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <template v-if="mode === 'create'">
                    <FormField for-id="deposit_principal" :label="t('deposits.form.principal.label')" :error="form.errors.principal">
                        <template #default="{ errorId, hasError }">
                            <Input
                                id="deposit_principal"
                                v-model="form.principal"
                                inputmode="decimal"
                                required
                                :placeholder="t('deposits.form.principal.placeholder')"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                            />
                        </template>
                    </FormField>

                    <FormField for-id="deposit_source" :label="t('deposits.form.source.label')" :error="form.errors.source_account_id">
                        <template #default="{ errorId, hasError }">
                            <DropdownSelect
                                id="deposit_source"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                                :model-value="form.source_account_id"
                                :options="bankAccountOptions"
                                :placeholder="t('deposits.form.source.placeholder')"
                                :disabled="form.processing || bankAccounts.length === 0"
                                @update:model-value="(value) => (form.source_account_id = value)"
                            />
                        </template>
                    </FormField>

                    <FormField for-id="deposit_opened_at" :label="t('deposits.form.opened.label')" :error="form.errors.opened_at">
                        <template #default="{ errorId, hasError }">
                            <DatePickerInput
                                id="deposit_opened_at"
                                v-model="form.opened_at"
                                :disabled="form.processing"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                            />
                        </template>
                    </FormField>
                </template>

                <FormField for-id="deposit_matures_at" :label="t('deposits.form.matures.label')" :error="form.errors.matures_at">
                    <template #default="{ errorId, hasError }">
                        <DatePickerInput
                            id="deposit_matures_at"
                            v-model="form.matures_at"
                            :disabled="form.processing"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <DialogFooter>
                    <Button type="button" variant="secondary" :disabled="form.processing" @click="onOpenChange(false)">
                        {{ t('actions.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing" :aria-busy="form.processing || undefined">
                        {{ t('actions.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
