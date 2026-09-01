<script setup lang="ts">
import BudgetProgressCell from '@/components/budget/BudgetProgressCell.vue';
import BudgetTableColgroup from '@/components/budget/BudgetTableColgroup.vue';
import EditableEstimateCell from '@/components/budget/EditableEstimateCell.vue';
import YearlyPlanEditCell from '@/components/budget/YearlyPlanEditCell.vue';
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type BudgetRow = {
    category_id: number;
    name: string;
    icon: string;
    color: string;
    type: string;
    monthly_plan?: string | null;
    annual_plan?: string | null;
    monthly_template?: string | null;
    actual: string;
    forecast?: string;
    progress_percent: number | null;
};

const props = defineProps<{
    incomeRows: BudgetRow[];
    expenseRows: BudgetRow[];
    currency: CurrencyDisplay;
    variant: 'monthly' | 'yearly';
    editingCategoryId: number | null;
    editingMode?: 'plan' | 'align' | null;
    planPlaceholder: string;
    monthlyPlanPlaceholder?: string;
    monthlyPlanLabel?: string;
}>();

const emit = defineEmits<{
    'start-edit': [categoryId: number];
    'start-align': [categoryId: number];
    cancel: [];
    save: [row: BudgetRow, rawValue: string];
    'save-yearly': [row: BudgetRow, annualRaw: string, monthlyRaw: string];
}>();

const { t } = useI18n();

const columnCount = computed(() => (props.variant === 'yearly' ? 5 : 4));

const groups = computed(() => [
    { key: 'income' as const, rows: props.incomeRows },
    { key: 'expense' as const, rows: props.expenseRows },
]);

function planForRow(row: BudgetRow): string | null {
    return props.variant === 'monthly' ? (row.monthly_plan ?? null) : (row.annual_plan ?? null);
}
</script>

<template>
    <section class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
        <div class="overflow-x-auto">
            <table class="budget-table text-sm">
                <BudgetTableColgroup layout="category" :period="variant" />
                <thead>
                    <tr class="border-b border-border/50 text-left text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                        <th class="py-2 pr-4">{{ t('categories.index.fields.name') }}</th>
                        <th class="py-2 pr-4 text-right">{{ variant === 'monthly' ? t('budget.monthly.plan') : t('budget.yearly.plan') }}</th>
                        <th v-if="variant === 'yearly'" class="py-2 pr-4 text-right">{{ t('budget.columns.forecast') }}</th>
                        <th class="py-2 pr-4 text-right">{{ variant === 'monthly' ? t('budget.monthly.actual') : t('budget.yearly.actual') }}</th>
                        <th class="py-2 text-right">{{ t('budget.columns.progress') }}</th>
                    </tr>
                </thead>
                <tbody v-for="group in groups" :key="group.key">
                    <tr>
                        <th
                            :colspan="columnCount"
                            scope="colgroup"
                            class="py-3 pr-4 text-left text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60"
                        >
                            {{ t(`budget.sections.${group.key}`) }}
                        </th>
                    </tr>
                    <tr v-for="row in group.rows" :key="row.category_id" class="list-row-hover border-b border-border/50 hover:bg-foreground/[0.03]">
                        <td class="py-2 pr-4">
                            <CategoryBadge :name="row.name" :icon="row.icon" :color="row.color" size="md" />
                        </td>
                        <td class="py-2 pr-4 text-right">
                            <YearlyPlanEditCell
                                v-if="variant === 'yearly'"
                                :annual-plan="row.annual_plan ?? null"
                                :monthly-template="row.monthly_template ?? null"
                                :currency="currency"
                                :input-id-prefix="`${variant}-plan-${row.category_id}`"
                                :annual-placeholder="planPlaceholder"
                                :monthly-placeholder="monthlyPlanPlaceholder ?? ''"
                                :monthly-plan-label="monthlyPlanLabel ?? t('budget.yearly.monthlyPlanLabel')"
                                :edit-label="t('budget.estimate.edit', { name: row.name })"
                                :save-label="t('budget.estimate.save')"
                                :cancel-label="t('budget.estimate.cancel')"
                                :is-editing="editingCategoryId === row.category_id"
                                @start-edit="emit('start-edit', row.category_id)"
                                @cancel="emit('cancel')"
                                @save="(annualRaw, monthlyRaw) => emit('save-yearly', row, annualRaw, monthlyRaw)"
                            />
                            <EditableEstimateCell
                                v-else
                                :plan="planForRow(row)"
                                :currency="currency"
                                :input-id="`${variant}-plan-${row.category_id}`"
                                :placeholder="planPlaceholder"
                                :edit-label="t('budget.estimate.edit', { name: row.name })"
                                :align-label="t('budget.estimate.align', { name: row.name })"
                                :save-label="t('budget.estimate.save')"
                                :cancel-label="t('budget.estimate.cancel')"
                                :is-editing="editingCategoryId === row.category_id"
                                :mode="editingCategoryId === row.category_id ? (editingMode ?? 'plan') : null"
                                :align-value="row.actual"
                                :show-align-button="variant === 'monthly'"
                                @start-edit="emit('start-edit', row.category_id)"
                                @start-align="emit('start-align', row.category_id)"
                                @cancel="emit('cancel')"
                                @save="(raw) => emit('save', row, raw)"
                            />
                        </td>
                        <td v-if="variant === 'yearly'" class="py-2 pr-4 text-right tabular-nums text-muted-foreground">
                            {{ formatMoney(row.forecast ?? null, currency) }}
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums">{{ formatMoney(row.actual, currency) }}</td>
                        <td class="py-2 text-right">
                            <BudgetProgressCell :percent="row.progress_percent" :category-type="group.key" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
