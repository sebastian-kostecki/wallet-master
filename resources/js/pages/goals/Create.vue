<script setup lang="ts">
import CategoryColorPicker from '@/components/categories/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/categories/CategoryIconPicker.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import SegmentedControl, { type SegmentedControlOption } from '@/components/forms/SegmentedControl.vue';
import GoalBadge from '@/components/goals/GoalBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

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

const props = defineProps<{
    icons: IconOption[];
    colors: ColorOption[];
    currencies: Currency[];
}>();

const { t } = useI18n();

const initialCurrencyId = computed(() => props.currencies[0]?.id ?? null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('goals.index.title'), href: route('goals.index') },
    { title: t('goals.create.title'), href: route('goals.create') },
]);

const colorError = ref<string | null>(null);

const form = useForm({
    name: '',
    icon: 'flag',
    color: null as string | null,
    currency_id: initialCurrencyId.value as number | null,
    target_amount: '',
    planning_mode: 'monthly' as 'monthly' | 'by_date',
    monthly_contribution: '',
    target_date: '',
});

const previewName = computed(() => form.name.trim() || t('categories.fields.preview'));

const selectedCurrency = computed(() => props.currencies.find((currency) => currency.id === form.currency_id) ?? null);

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
    { value: 'monthly', label: t('goals.planning.monthly') },
    { value: 'by_date', label: t('goals.planning.by_date') },
]);

function submit(): void {
    colorError.value = null;

    if (form.color === null) {
        colorError.value = t('categories.validation.colorRequired');
        return;
    }

    if (!hasTarget.value) {
        form.target_amount = '';
        form.monthly_contribution = '';
        form.target_date = '';
    } else if (form.planning_mode === 'monthly') {
        form.target_date = '';
    } else {
        form.monthly_contribution = '';
    }

    form.post(route('goals.store'));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('goals.create.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <form class="grid gap-6" @submit.prevent="submit">
                    <div class="flex items-center gap-4 rounded-lg border border-sidebar-border/50 p-4 dark:border-sidebar-border">
                        <GoalBadge
                            v-if="form.color"
                            :name="previewName"
                            :icon="form.icon"
                            :color="form.color"
                            size="md"
                        />
                        <p v-else class="text-sm text-muted-foreground">{{ t('categories.fields.previewHint') }}</p>
                    </div>

                    <FormField for-id="name" :label="t('goals.index.fields.name')" :error="form.errors.name">
                        <Input id="name" v-model="form.name" required autofocus />
                    </FormField>

                    <FormField for-id="currency_id" :label="t('goals.fields.currency.label')" :error="form.errors.currency_id">
                        <DropdownSelect
                            id="currency_id"
                            :model-value="form.currency_id"
                            :options="currencyOptions"
                            :placeholder="t('goals.fields.currency.placeholder')"
                            @update:model-value="(value) => (form.currency_id = value)"
                        />
                    </FormField>

                    <FormField for-id="target_amount" :label="t('goals.fields.targetAmount')" :error="form.errors.target_amount">
                        <template #default="{ errorId, hasError }">
                            <div class="relative">
                                <Input
                                    id="target_amount"
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

                    <FormField :label="t('categories.fields.color')" :error="form.errors.color ?? colorError ?? undefined">
                        <CategoryColorPicker :colors="props.colors" :model-value="form.color" @update:model-value="(v) => (form.color = v)" />
                    </FormField>

                    <FormField :label="t('categories.fields.icon')" :error="form.errors.icon">
                        <CategoryIconPicker :icons="props.icons" :model-value="form.icon" @update:model-value="(v) => (form.icon = v)" />
                    </FormField>

                    <div v-if="hasTarget" class="grid gap-4 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <FormField for-id="planning_mode" :label="t('goals.fields.planningMode')">
                            <SegmentedControl
                                id="planning_mode"
                                :model-value="form.planning_mode"
                                :options="planningModeOptions"
                                :aria-label="t('goals.fields.planningMode')"
                                @update:model-value="(value) => (form.planning_mode = value as 'monthly' | 'by_date')"
                            />
                        </FormField>

                        <FormField
                            v-if="form.planning_mode === 'monthly'"
                            for-id="monthly_contribution"
                            :label="t('goals.fields.monthlyContribution')"
                            :error="form.errors.monthly_contribution"
                        >
                            <template #default="{ errorId, hasError }">
                                <div class="relative">
                                    <Input
                                        id="monthly_contribution"
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

                        <FormField
                            v-else
                            for-id="target_date"
                            :label="t('goals.fields.targetDate')"
                            :error="form.errors.target_date"
                        >
                            <Input id="target_date" v-model="form.target_date" type="date" />
                        </FormField>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" :disabled="form.processing">{{ t('actions.save') }}</Button>
                        <Button variant="outline" as-child>
                            <Link :href="route('goals.index')">{{ t('actions.cancel') }}</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
