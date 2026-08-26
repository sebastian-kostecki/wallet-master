<script setup lang="ts">
import CategoryColorPicker from '@/components/categories/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/categories/CategoryIconPicker.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import SegmentedControl, { type SegmentedControlOption } from '@/components/forms/SegmentedControl.vue';
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/formatMoney';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type IconOption = {
    value: string;
    label_key: string;
};

type ColorOption = {
    value: string;
};

type Currency = {
    id: number;
    code: string;
    name: string;
    symbol: string;
    precision: number;
};

type Pocket = {
    id: number;
    name: string;
    icon: string;
    color: string;
    currency_id: number;
    currency: Currency;
    initial_balance: string;
    target_amount: string | null;
    planning_mode: 'monthly' | 'by_date' | null;
    monthly_contribution: string | null;
    target_date: string | null;
    recommended_monthly: string | null;
    projected_completion_date: string | null;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    pocket: Pocket | null;
    icons: IconOption[];
    colors: ColorOption[];
    currencies: Currency[];
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();

const colorError = ref<string | null>(null);

const form = useForm<{
    name: string;
    icon: string;
    color: string | null;
    currency_id: number | null;
    initial_balance: string;
    target_amount: string;
    planning_mode: 'monthly' | 'by_date' | null;
    monthly_contribution: string;
    target_date: string;
}>({
    name: '',
    icon: 'flag',
    color: null,
    currency_id: null,
    initial_balance: '',
    target_amount: '',
    planning_mode: 'monthly',
    monthly_contribution: '',
    target_date: '',
});

const title = computed(() => (props.mode === 'edit' ? t('pockets.edit.title') : t('pockets.create.title')));

const description = computed(() => {
    if (props.mode === 'edit') {
        return props.pocket?.name ?? '';
    }

    return t('pockets.create.description');
});

const previewName = computed(() => {
    const trimmed = form.name.trim();

    if (trimmed !== '') {
        return trimmed;
    }

    if (props.mode === 'edit' && props.pocket) {
        return props.pocket.name;
    }

    return t('categories.fields.preview');
});

const selectedCurrency = computed(() => {
    if (props.mode === 'edit' && props.pocket) {
        return props.pocket.currency;
    }

    return props.currencies.find((currency) => currency.id === form.currency_id) ?? null;
});

const currencyOptions = computed<DropdownOption<number>[]>(() =>
    props.currencies.map((currency) => ({
        value: currency.id,
        label: `${currency.code} — ${currency.name}`,
    })),
);

const hasTarget = computed(() => {
    const normalized = form.target_amount.trim().replace(',', '.');
    const parsed = Number(normalized);

    return Number.isFinite(parsed) && parsed > 0;
});

const planningModeOptions = computed<SegmentedControlOption<'monthly' | 'by_date'>[]>(() => [
    { value: 'monthly', label: t('pockets.planning.monthly') },
    { value: 'by_date', label: t('pockets.planning.by_date') },
]);

function resetForm(): void {
    colorError.value = null;
    form.clearErrors();

    if (props.mode === 'edit' && props.pocket) {
        form.name = props.pocket.name;
        form.icon = props.pocket.icon;
        form.color = props.pocket.color;
        form.currency_id = props.pocket.currency_id;
        form.initial_balance = props.pocket.initial_balance;
        form.target_amount = props.pocket.target_amount ?? '';
        form.planning_mode = props.pocket.planning_mode ?? 'monthly';
        form.monthly_contribution = props.pocket.monthly_contribution ?? '';
        form.target_date = props.pocket.target_date ?? '';
        return;
    }

    form.reset();
    form.name = '';
    form.icon = 'flag';
    form.color = null;
    form.currency_id = props.currencies[0]?.id ?? null;
    form.initial_balance = '';
    form.target_amount = '';
    form.planning_mode = 'monthly';
    form.monthly_contribution = '';
    form.target_date = '';
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
    colorError.value = null;

    if (props.mode === 'create' && form.color === null) {
        colorError.value = t('categories.validation.colorRequired');
        return;
    }

    if (!hasTarget.value) {
        form.target_amount = '';
        form.target_date = '';
        form.planning_mode = null;
    } else if (form.planning_mode === 'monthly') {
        form.target_date = '';
    } else {
        form.monthly_contribution = '';
    }

    const visit = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    };

    if (props.mode === 'edit' && props.pocket) {
        form.transform((data) => ({
            name: data.name,
            icon: data.icon,
            color: data.color,
            target_amount: data.target_amount,
            planning_mode: data.planning_mode,
            monthly_contribution: data.monthly_contribution,
            target_date: data.target_date,
        })).patch(route('pockets.update', props.pocket.id), visit);
        return;
    }

    form.transform((data) => data).post(route('pockets.store'), visit);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <form class="grid gap-6" @submit.prevent="submit">
                <div class="flex items-center gap-4 rounded-xl border border-border/50 p-4">
                    <PocketBadge v-if="form.color" :name="previewName" :icon="form.icon" :color="form.color" size="md" />
                    <p v-else class="text-sm text-muted-foreground">{{ t('categories.fields.previewHint') }}</p>
                </div>

                <FormField for-id="pocket_name" :label="t('pockets.index.fields.name')" :error="form.errors.name">
                    <Input id="pocket_name" v-model="form.name" required autofocus />
                </FormField>

                <FormField
                    v-if="mode === 'create'"
                    for-id="pocket_currency_id"
                    :label="t('pockets.fields.currency.label')"
                    :error="form.errors.currency_id"
                >
                    <DropdownSelect
                        id="pocket_currency_id"
                        :model-value="form.currency_id"
                        :options="currencyOptions"
                        :placeholder="t('pockets.fields.currency.placeholder')"
                        :disabled="form.processing"
                        @update:model-value="(value) => (form.currency_id = value)"
                    />
                </FormField>

                <FormField v-if="mode === 'edit' && pocket" for-id="pocket_currency" :label="t('pockets.fields.currency.label')">
                    <Input id="pocket_currency" :model-value="`${pocket.currency.code} (${pocket.currency.symbol})`" type="text" disabled readonly />
                </FormField>

                <FormField
                    v-if="mode === 'create'"
                    for-id="pocket_initial_balance"
                    :label="t('pockets.fields.initialBalance.label')"
                    :hint="t('pockets.fields.initialBalance.hint')"
                    :error="form.errors.initial_balance"
                >
                    <template #default="{ errorId, hasError, hintId }">
                        <div class="relative">
                            <Input
                                id="pocket_initial_balance"
                                v-model="form.initial_balance"
                                type="text"
                                inputmode="decimal"
                                class="pr-10"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="[hasError ? errorId : null, hintId].filter(Boolean).join(' ') || undefined"
                            />
                            <span
                                v-if="selectedCurrency?.symbol"
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                            >
                                {{ selectedCurrency.symbol }}
                            </span>
                        </div>
                    </template>
                </FormField>

                <div
                    v-if="mode === 'edit' && pocket && Number(pocket.initial_balance.replace(',', '.')) > 0"
                    class="grid gap-1 rounded-xl border border-border/50 p-4 text-sm"
                >
                    <p class="text-muted-foreground">
                        {{ t('pockets.fields.initialBalance.label') }}:
                        <span class="font-medium text-foreground">{{ formatMoney(pocket.initial_balance, pocket.currency) }}</span>
                    </p>
                    <p class="text-xs text-muted-foreground">{{ t('pockets.fields.initialBalance.hint') }}</p>
                </div>

                <FormField for-id="pocket_target_amount" :label="t('pockets.fields.targetAmount')" :error="form.errors.target_amount">
                    <template #default="{ errorId, hasError }">
                        <div class="relative">
                            <Input
                                id="pocket_target_amount"
                                v-model="form.target_amount"
                                type="text"
                                inputmode="decimal"
                                class="pr-10"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                            />
                            <span
                                v-if="selectedCurrency?.symbol"
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                            >
                                {{ selectedCurrency.symbol }}
                            </span>
                        </div>
                    </template>
                </FormField>

                <FormField
                    for-id="pocket_monthly_contribution"
                    :label="t('pockets.fields.monthlyContribution')"
                    :error="form.errors.monthly_contribution"
                >
                    <template #default="{ errorId, hasError }">
                        <div class="relative">
                            <Input
                                id="pocket_monthly_contribution"
                                v-model="form.monthly_contribution"
                                type="text"
                                inputmode="decimal"
                                class="pr-10"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                            />
                            <span
                                v-if="selectedCurrency?.symbol"
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                            >
                                {{ selectedCurrency.symbol }}
                            </span>
                        </div>
                    </template>
                </FormField>

                <FormField :label="t('categories.fields.color')" :error="form.errors.color ?? colorError ?? undefined">
                    <CategoryColorPicker :colors="props.colors" :model-value="form.color" @update:model-value="(v) => (form.color = v)" />
                </FormField>

                <FormField :label="t('categories.fields.icon')" :error="form.errors.icon">
                    <CategoryIconPicker :icons="props.icons" :model-value="form.icon" @update:model-value="(v) => (form.icon = v)" />
                </FormField>

                <div v-if="hasTarget" class="grid gap-4 rounded-xl border border-border/50 p-4">
                    <FormField for-id="pocket_planning_mode" :label="t('pockets.fields.planningMode')">
                        <SegmentedControl
                            id="pocket_planning_mode"
                            :model-value="form.planning_mode ?? 'monthly'"
                            :options="planningModeOptions"
                            :aria-label="t('pockets.fields.planningMode')"
                            @update:model-value="(value) => (form.planning_mode = value as 'monthly' | 'by_date')"
                        />
                    </FormField>

                    <FormField
                        v-if="form.planning_mode === 'by_date'"
                        for-id="pocket_target_date"
                        :label="t('pockets.fields.targetDate')"
                        :error="form.errors.target_date"
                    >
                        <Input id="pocket_target_date" v-model="form.target_date" type="date" />
                    </FormField>
                </div>

                <div v-if="mode === 'edit' && pocket" class="grid gap-2 rounded-xl border border-border/50 p-4 text-sm">
                    <p class="text-muted-foreground">
                        {{ t('pockets.edit.recommendedMonthly') }}:
                        <span class="font-medium text-foreground">{{ formatMoney(pocket.recommended_monthly, pocket.currency) }}</span>
                    </p>
                    <p class="text-muted-foreground">
                        {{ t('pockets.edit.projectedCompletionDate') }}:
                        <span class="font-medium text-foreground">{{ pocket.projected_completion_date ?? '—' }}</span>
                    </p>
                </div>

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
