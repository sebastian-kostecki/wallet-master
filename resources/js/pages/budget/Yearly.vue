<script setup lang="ts">
import BudgetCategorySection from '@/components/budget/BudgetCategorySection.vue';
import BudgetSummaryCard from '@/components/budget/BudgetSummaryCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type CurrencyDisplay } from '@/lib/formatMoney';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type BudgetRow = {
    category_id: number;
    name: string;
    icon: string;
    color: string;
    type: string;
    annual_plan: string | null;
    actual: string;
    forecast: string;
    progress_percent: number | null;
};

type BudgetSummary = {
    plan: { income: string; expense: string; balance: string };
    execution: { income: string; expense: string; balance: string };
    forecast: { income: string; expense: string; balance: string };
    progress: { income_percent: number | null; expense_percent: number | null };
};

const props = defineProps<{
    year: number;
    rows: BudgetRow[];
    summary: BudgetSummary;
    currency: CurrencyDisplay;
}>();

const { t } = useI18n();
const editingCategoryId = ref<number | null>(null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: t('budget.yearly.title'), href: route('budget.yearly') }]);

const expenseRows = computed(() => props.rows.filter((r) => r.type === 'expense'));
const incomeRows = computed(() => props.rows.filter((r) => r.type === 'income'));

const monthlyViewMonth = computed(() => {
    const now = new Date();

    if (props.year === now.getFullYear()) {
        return now.getMonth() + 1;
    }

    return 1;
});

function startEdit(categoryId: number) {
    editingCategoryId.value = categoryId;
}

function cancelEdit() {
    editingCategoryId.value = null;
}

function saveAnnualEstimate(row: { category_id: number; annual_plan?: string | null }, rawValue: string) {
    const trimmed = rawValue.trim();
    const normalized = trimmed.replace(',', '.');
    const current = row.annual_plan ?? '';

    if (normalized === current || (normalized === '' && current === '')) {
        editingCategoryId.value = null;
        return;
    }

    const amount = trimmed === '' ? null : normalized;

    router.patch(
        route('categories.estimates.annual', row.category_id),
        {
            year: props.year,
            amount,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                editingCategoryId.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('budget.yearly.title')" />

        <div class="budget-page flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex gap-2">
                    <Button variant="ghost" as-child>
                        <Link :href="route('budget.monthly', { year, month: monthlyViewMonth })">{{ t('budget.view.monthly') }}</Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="route('budget.yearly', { year })">{{ t('budget.view.yearly') }}</Link>
                    </Button>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="router.get(route('budget.yearly', { year: year - 1 }))">←</Button>
                    <span class="text-sm font-medium">{{ year }}</span>
                    <Button variant="outline" @click="router.get(route('budget.yearly', { year: year + 1 }))">→</Button>
                </div>
            </div>

            <BudgetSummaryCard :summary="summary" :currency="currency" variant="yearly" />

            <BudgetCategorySection
                :title="t('budget.sections.income')"
                category-type="income"
                :rows="incomeRows"
                :currency="currency"
                variant="yearly"
                :editing-category-id="editingCategoryId"
                :plan-placeholder="t('budget.yearly.planPlaceholder')"
                @start-edit="startEdit"
                @cancel="cancelEdit"
                @save="saveAnnualEstimate"
            />

            <BudgetCategorySection
                :title="t('budget.sections.expense')"
                category-type="expense"
                :rows="expenseRows"
                :currency="currency"
                variant="yearly"
                :editing-category-id="editingCategoryId"
                :plan-placeholder="t('budget.yearly.planPlaceholder')"
                @start-edit="startEdit"
                @cancel="cancelEdit"
                @save="saveAnnualEstimate"
            />
        </div>
    </AppLayout>
</template>

<style scoped>
.budget-page {
    --budget-col-label: 11rem;
    --budget-col-plan: 9rem;
    --budget-col-amount: 8.5rem;
    --budget-col-forecast: 8.5rem;
    --budget-col-progress: 4.5rem;
}
</style>
