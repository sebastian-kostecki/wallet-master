<script setup lang="ts">
import BudgetProgressCell from '@/components/budget/BudgetProgressCell.vue';
import BudgetTableColgroup from '@/components/budget/BudgetTableColgroup.vue';
import EditableEstimateCell from '@/components/budget/EditableEstimateCell.vue';
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { useI18n } from 'vue-i18n';

type BudgetRow = {
    category_id: number;
    name: string;
    icon: string;
    color: string;
    type: string;
    monthly_plan?: string | null;
    annual_plan?: string | null;
    actual: string;
    forecast?: string;
    progress_percent: number | null;
};

const props = defineProps<{
    title: string;
    categoryType: 'income' | 'expense';
    rows: BudgetRow[];
    currency: CurrencyDisplay;
    variant: 'monthly' | 'yearly';
    editingCategoryId: number | null;
    planPlaceholder: string;
}>();

const emit = defineEmits<{
    'start-edit': [categoryId: number];
    cancel: [];
    save: [row: BudgetRow, rawValue: string];
}>();

const { t } = useI18n();

function planForRow(row: BudgetRow): string | null {
    return props.variant === 'monthly' ? (row.monthly_plan ?? null) : (row.annual_plan ?? null);
}
</script>

<template>
    <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <h2 class="mb-3 text-lg font-semibold">{{ title }}</h2>
        <div class="overflow-x-auto">
            <table class="budget-table text-sm">
                <BudgetTableColgroup layout="category" :period="variant" />
                <thead>
                    <tr class="border-b text-left text-muted-foreground">
                        <th class="py-2 pr-4">{{ t('categories.index.fields.name') }}</th>
                        <th class="py-2 pr-4">{{ variant === 'monthly' ? t('budget.monthly.plan') : t('budget.yearly.plan') }}</th>
                        <th class="py-2 pr-4">{{ variant === 'monthly' ? t('budget.monthly.actual') : t('budget.yearly.actual') }}</th>
                        <th v-if="variant === 'yearly'" class="py-2 pr-4">{{ t('budget.columns.forecast') }}</th>
                        <th class="py-2">{{ t('budget.columns.progress') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.category_id" class="border-b border-sidebar-border/40">
                        <td class="py-2 pr-4">
                            <CategoryBadge :name="row.name" :icon="row.icon" :color="row.color" size="md" />
                        </td>
                        <td class="py-2 pr-4">
                            <EditableEstimateCell
                                :plan="planForRow(row)"
                                :currency="currency"
                                :input-id="`${variant}-plan-${row.category_id}`"
                                :placeholder="planPlaceholder"
                                :edit-label="t('budget.estimate.edit', { name: row.name })"
                                :save-label="t('budget.estimate.save')"
                                :cancel-label="t('budget.estimate.cancel')"
                                :is-editing="editingCategoryId === row.category_id"
                                @start-edit="emit('start-edit', row.category_id)"
                                @cancel="emit('cancel')"
                                @save="(raw) => emit('save', row, raw)"
                            />
                        </td>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(row.actual, currency) }}</td>
                        <td v-if="variant === 'yearly'" class="py-2 pr-4 tabular-nums">
                            {{ formatMoney(row.forecast ?? null, currency) }}
                        </td>
                        <td class="py-2">
                            <BudgetProgressCell :percent="row.progress_percent" :category-type="categoryType" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
