<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { categoriesByIdMap, type CategoryOption } from '@/lib/categories';
import { normalizeAmount } from '@/lib/money';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type BankAccount = {
    id: number;
    name: string;
};

type Deposit = {
    id: number;
    name: string | null;
    principal: string;
    has_interest: boolean;
};

const props = defineProps<{
    open: boolean;
    deposit: Deposit | null;
    bankAccounts: BankAccount[];
    incomeCategories: CategoryOption[];
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();

const form = useForm<{
    destination_account_id: number | null;
    closed_at: string;
    interest: string;
    category_id: number | null;
}>({
    destination_account_id: null,
    closed_at: '',
    interest: '0,00',
    category_id: null,
});

const displayName = computed(() => props.deposit?.name?.trim() || t('deposits.fallbackName'));
const hideInterestFields = computed(() => props.deposit?.has_interest === true);

const showCategoryField = computed(() => {
    if (hideInterestFields.value) {
        return false;
    }

    const normalized = normalizeAmount(form.interest);
    const parsed = Number(normalized);

    return !Number.isNaN(parsed) && parsed > 0;
});

const bankAccountOptions = computed<DropdownOption<number>[]>(() =>
    props.bankAccounts.map((account) => ({ value: account.id, label: account.name })),
);

const categoryOptions = computed<DropdownOption<number>[]>(() =>
    props.incomeCategories.map((category) => ({ value: category.id, label: category.name })),
);

const categoriesById = computed(() => categoriesByIdMap(props.incomeCategories));

const selectedCategory = computed(() => {
    if (form.category_id === null) {
        return null;
    }

    return categoriesById.value.get(form.category_id) ?? null;
});

function todayDdMmYyyy(): string {
    const now = new Date();
    const d = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));

    return `${String(d.getUTCDate()).padStart(2, '0')}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${d.getUTCFullYear()}`;
}

function resetForm(): void {
    form.clearErrors();
    form.reset();
    form.destination_account_id = props.bankAccounts[0]?.id ?? null;
    form.closed_at = todayDdMmYyyy();
    form.interest = '0,00';
    form.category_id = props.incomeCategories[0]?.id ?? null;
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);

watch(showCategoryField, (visible) => {
    if (!visible) {
        form.category_id = null;
    } else if (form.category_id === null) {
        form.category_id = props.incomeCategories[0]?.id ?? null;
    }
});

function onOpenChange(value: boolean): void {
    if (form.processing && !value) {
        return;
    }

    emit('update:open', value);
}

function submit(): void {
    if (props.deposit === null) {
        return;
    }

    form.transform((data) => ({
        destination_account_id: data.destination_account_id,
        closed_at: data.closed_at.trim(),
        interest: hideInterestFields.value ? '0.00' : normalizeAmount(data.interest),
        category_id: showCategoryField.value ? data.category_id : null,
    })).post(route('deposits.close', props.deposit.id), {
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
                <DialogTitle>{{ t('deposits.form.closeTitle') }}</DialogTitle>
                <DialogDescription>{{ t('deposits.form.closeDescription') }}</DialogDescription>
            </DialogHeader>

            <div v-if="deposit" class="rounded-xl border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                <p class="font-medium">{{ displayName }}</p>
            </div>

            <form class="grid gap-6" @submit.prevent="submit">
                <FormField for-id="deposit_destination" :label="t('deposits.form.destination.label')" :error="form.errors.destination_account_id">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="deposit_destination"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.destination_account_id"
                            :options="bankAccountOptions"
                            :placeholder="t('deposits.form.destination.placeholder')"
                            :disabled="form.processing || bankAccounts.length === 0"
                            @update:model-value="(value) => (form.destination_account_id = value)"
                        />
                    </template>
                </FormField>

                <FormField for-id="deposit_closed_at" :label="t('deposits.form.closedAt.label')" :error="form.errors.closed_at">
                    <template #default="{ errorId, hasError }">
                        <DatePickerInput
                            id="deposit_closed_at"
                            v-model="form.closed_at"
                            :disabled="form.processing"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <template v-if="!hideInterestFields">
                    <FormField for-id="deposit_interest" :label="t('deposits.form.interest.label')" :error="form.errors.interest">
                        <template #default="{ errorId, hasError }">
                            <Input
                                id="deposit_interest"
                                v-model="form.interest"
                                inputmode="decimal"
                                required
                                :placeholder="t('deposits.form.interest.placeholder')"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                            />
                        </template>
                    </FormField>

                    <FormField
                        v-if="showCategoryField"
                        for-id="deposit_category"
                        :label="t('deposits.form.category.label')"
                        :error="form.errors.category_id"
                    >
                        <template #default="{ errorId, hasError }">
                            <DropdownSelect
                                id="deposit_category"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                                :model-value="form.category_id"
                                :options="categoryOptions"
                                :placeholder="t('deposits.form.category.placeholder')"
                                :disabled="form.processing || incomeCategories.length === 0"
                                @update:model-value="(value) => (form.category_id = value)"
                            >
                                <template #trigger-leading>
                                    <CategoryBadge
                                        v-if="selectedCategory"
                                        :name="selectedCategory.name"
                                        :icon="selectedCategory.icon"
                                        :color="selectedCategory.color"
                                        size="sm"
                                    />
                                </template>
                                <template #option-leading="{ option }">
                                    <CategoryBadge
                                        v-if="categoriesById.get(option.value)"
                                        :name="categoriesById.get(option.value)!.name"
                                        :icon="categoriesById.get(option.value)!.icon"
                                        :color="categoriesById.get(option.value)!.color"
                                        size="sm"
                                    />
                                </template>
                            </DropdownSelect>
                        </template>
                    </FormField>
                </template>

                <DialogFooter>
                    <Button type="button" variant="secondary" :disabled="form.processing" @click="onOpenChange(false)">
                        {{ t('actions.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing" :aria-busy="form.processing || undefined">
                        {{ t('deposits.actions.close') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
