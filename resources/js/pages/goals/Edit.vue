<script setup lang="ts">
import CategoryColorPicker from '@/components/categories/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/categories/CategoryIconPicker.vue';
import FormField from '@/components/forms/FormField.vue';
import GoalBadge from '@/components/goals/GoalBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
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

type Goal = {
    id: number;
    name: string;
    icon: string;
    color: string;
    target_amount: string | null;
    planning_mode: 'monthly' | 'by_date' | null;
    monthly_contribution: string | null;
    target_date: string | null;
    is_archived: boolean;
    recommended_monthly: string | null;
    projected_completion_date: string | null;
};

const props = defineProps<{
    goal: Goal;
    icons: IconOption[];
    colors: ColorOption[];
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('goals.index.title'), href: route('goals.index') },
    { title: t('goals.edit.title'), href: route('goals.edit', props.goal.id) },
]);

const form = useForm({
    name: props.goal.name,
    icon: props.goal.icon,
    color: props.goal.color,
    target_amount: props.goal.target_amount ?? '',
    planning_mode: (props.goal.planning_mode ?? 'monthly') as 'monthly' | 'by_date',
    monthly_contribution: props.goal.monthly_contribution ?? '',
    target_date: props.goal.target_date ?? '',
    is_archived: props.goal.is_archived,
});

const hasTarget = computed(() => {
    const normalized = form.target_amount.trim().replace(',', '.');
    const parsed = Number(normalized);

    return Number.isFinite(parsed) && parsed > 0;
});

const previewName = computed(() => form.name.trim() || props.goal.name);

function submit(): void {
    if (!hasTarget.value) {
        form.target_amount = '';
        form.monthly_contribution = '';
        form.target_date = '';
    } else if (form.planning_mode === 'monthly') {
        form.target_date = '';
    } else {
        form.monthly_contribution = '';
    }

    form.patch(route('goals.update', props.goal.id));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('goals.edit.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <form class="grid gap-6" @submit.prevent="submit">
                    <GoalBadge :name="previewName" :icon="form.icon" :color="form.color" size="md" />

                    <FormField for-id="name" :label="t('goals.index.fields.name')" :error="form.errors.name">
                        <Input id="name" v-model="form.name" required autofocus />
                    </FormField>

                    <FormField for-id="target_amount" :label="t('goals.fields.targetAmount')" :error="form.errors.target_amount">
                        <Input id="target_amount" v-model="form.target_amount" type="text" inputmode="decimal" />
                    </FormField>

                    <FormField :label="t('categories.fields.color')" :error="form.errors.color">
                        <CategoryColorPicker :colors="props.colors" :model-value="form.color" @update:model-value="(v) => (form.color = v)" />
                    </FormField>

                    <FormField :label="t('categories.fields.icon')" :error="form.errors.icon">
                        <CategoryIconPicker :icons="props.icons" :model-value="form.icon" @update:model-value="(v) => (form.icon = v)" />
                    </FormField>

                    <div v-if="hasTarget" class="grid gap-4 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <fieldset class="grid gap-3">
                            <legend class="text-sm font-medium">{{ t('goals.fields.planningMode') }}</legend>
                            <Label for="planning-monthly" class="flex items-center gap-2 font-normal">
                                <input
                                    id="planning-monthly"
                                    v-model="form.planning_mode"
                                    type="radio"
                                    value="monthly"
                                    class="h-4 w-4 border-input text-primary focus:ring-ring"
                                />
                                {{ t('goals.planning.monthly') }}
                            </Label>
                            <Label for="planning-by-date" class="flex items-center gap-2 font-normal">
                                <input
                                    id="planning-by-date"
                                    v-model="form.planning_mode"
                                    type="radio"
                                    value="by_date"
                                    class="h-4 w-4 border-input text-primary focus:ring-ring"
                                />
                                {{ t('goals.planning.by_date') }}
                            </Label>
                        </fieldset>

                        <FormField
                            v-if="form.planning_mode === 'monthly'"
                            for-id="monthly_contribution"
                            :label="t('goals.fields.monthlyContribution')"
                            :error="form.errors.monthly_contribution"
                        >
                            <Input id="monthly_contribution" v-model="form.monthly_contribution" type="text" inputmode="decimal" />
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

                    <div class="grid gap-2 rounded-lg border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border">
                        <p class="text-muted-foreground">
                            {{ t('goals.edit.recommendedMonthly') }}:
                            <span class="font-medium text-foreground">{{ goal.recommended_monthly ?? '—' }}</span>
                        </p>
                        <p class="text-muted-foreground">
                            {{ t('goals.edit.projectedCompletionDate') }}:
                            <span class="font-medium text-foreground">{{ goal.projected_completion_date ?? '—' }}</span>
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
                            <Label for="is_archived" class="cursor-pointer">{{ t('goals.status.archived') }}</Label>
                        </div>
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
