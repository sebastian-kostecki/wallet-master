<script setup lang="ts">
import CategoryColorPicker from '@/components/categories/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/categories/CategoryIconPicker.vue';
import FormField from '@/components/forms/FormField.vue';
import SegmentedControl, { type SegmentedControlOption } from '@/components/forms/SegmentedControl.vue';
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/formatMoney';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type IconOption = {
    value: string;
    label_key: string;
};

type ColorOption = {
    value: string;
};

type Pocket = {
    id: number;
    name: string;
    icon: string;
    color: string;
    currency: {
        code: string;
        symbol: string;
        precision: number;
    };
    initial_balance: string;
    target_amount: string | null;
    planning_mode: 'monthly' | 'by_date' | null;
    monthly_contribution: string | null;
    target_date: string | null;
    is_archived: boolean;
    recommended_monthly: string | null;
    projected_completion_date: string | null;
};

const props = defineProps<{
    pocket: Pocket;
    icons: IconOption[];
    colors: ColorOption[];
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('pockets.index.title'), href: route('pockets.index') },
    { title: t('pockets.edit.title'), href: route('pockets.edit', props.pocket.id) },
]);

const form = useForm({
    name: props.pocket.name,
    icon: props.pocket.icon,
    color: props.pocket.color,
    target_amount: props.pocket.target_amount ?? '',
    planning_mode: (props.pocket.planning_mode ?? 'monthly') as 'monthly' | 'by_date',
    monthly_contribution: props.pocket.monthly_contribution ?? '',
    target_date: props.pocket.target_date ?? '',
    is_archived: props.pocket.is_archived,
});

const hasTarget = computed(() => {
    const normalized = form.target_amount.trim().replace(',', '.');
    const parsed = Number(normalized);

    return Number.isFinite(parsed) && parsed > 0;
});

const previewName = computed(() => form.name.trim() || props.pocket.name);

const planningModeOptions = computed<SegmentedControlOption<'monthly' | 'by_date'>[]>(() => [
    { value: 'monthly', label: t('pockets.planning.monthly') },
    { value: 'by_date', label: t('pockets.planning.by_date') },
]);

function submit(): void {
    if (!hasTarget.value) {
        form.target_amount = '';
        form.target_date = '';
        (form as { planning_mode: 'monthly' | 'by_date' | null }).planning_mode = null;
    } else if (form.planning_mode === 'monthly') {
        form.target_date = '';
    } else {
        form.monthly_contribution = '';
    }

    form.patch(route('pockets.update', props.pocket.id));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('pockets.edit.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <form class="grid gap-6" @submit.prevent="submit">
                    <PocketBadge :name="previewName" :icon="form.icon" :color="form.color" size="md" />

                    <FormField for-id="name" :label="t('pockets.index.fields.name')" :error="form.errors.name">
                        <Input id="name" v-model="form.name" required autofocus />
                    </FormField>

                    <FormField for-id="currency" :label="t('pockets.fields.currency.label')">
                        <Input id="currency" :model-value="`${pocket.currency.code} (${pocket.currency.symbol})`" type="text" disabled readonly />
                    </FormField>

                    <div
                        v-if="Number(pocket.initial_balance.replace(',', '.')) > 0"
                        class="grid gap-1 rounded-lg border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border"
                    >
                        <p class="text-muted-foreground">
                            {{ t('pockets.fields.initialBalance.label') }}:
                            <span class="font-medium text-foreground">{{ formatMoney(pocket.initial_balance, pocket.currency) }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">{{ t('pockets.fields.initialBalance.hint') }}</p>
                    </div>

                    <FormField for-id="target_amount" :label="t('pockets.fields.targetAmount')" :error="form.errors.target_amount">
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
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                    {{ pocket.currency.symbol }}
                                </span>
                            </div>
                        </template>
                    </FormField>

                    <FormField
                        for-id="monthly_contribution"
                        :label="t('pockets.fields.monthlyContribution')"
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
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">
                                    {{ pocket.currency.symbol }}
                                </span>
                            </div>
                        </template>
                    </FormField>

                    <FormField :label="t('categories.fields.color')" :error="form.errors.color">
                        <CategoryColorPicker :colors="props.colors" :model-value="form.color" @update:model-value="(v) => (form.color = v)" />
                    </FormField>

                    <FormField :label="t('categories.fields.icon')" :error="form.errors.icon">
                        <CategoryIconPicker :icons="props.icons" :model-value="form.icon" @update:model-value="(v) => (form.icon = v)" />
                    </FormField>

                    <div v-if="hasTarget" class="grid gap-4 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <FormField for-id="planning_mode" :label="t('pockets.fields.planningMode')">
                            <SegmentedControl
                                id="planning_mode"
                                :model-value="form.planning_mode"
                                :options="planningModeOptions"
                                :aria-label="t('pockets.fields.planningMode')"
                                @update:model-value="(value) => (form.planning_mode = value as 'monthly' | 'by_date')"
                            />
                        </FormField>

                        <FormField v-if="form.planning_mode === 'by_date'" for-id="target_date" :label="t('pockets.fields.targetDate')" :error="form.errors.target_date">
                            <Input id="target_date" v-model="form.target_date" type="date" />
                        </FormField>
                    </div>

                    <div class="grid gap-2 rounded-lg border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border">
                        <p class="text-muted-foreground">
                            {{ t('pockets.edit.recommendedMonthly') }}:
                            <span class="font-medium text-foreground">{{ formatMoney(pocket.recommended_monthly, pocket.currency) }}</span>
                        </p>
                        <p class="text-muted-foreground">
                            {{ t('pockets.edit.projectedCompletionDate') }}:
                            <span class="font-medium text-foreground">{{ pocket.projected_completion_date ?? '—' }}</span>
                        </p>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
                        <Checkbox
                            id="is_archived"
                            :checked="form.is_archived"
                            :disabled="form.processing"
                            @update:checked="(value) => (form.is_archived = value === true)"
                        />
                        <div class="grid gap-1 leading-tight">
                            <Label for="is_archived" class="cursor-pointer">{{ t('pockets.status.archived') }}</Label>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" :disabled="form.processing">{{ t('actions.save') }}</Button>
                        <Button variant="outline" as-child>
                            <Link :href="route('pockets.index')">{{ t('actions.cancel') }}</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
