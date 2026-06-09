<script setup lang="ts">
import BudgetCategorySection from '@/components/budget/BudgetCategorySection.vue';
import BudgetPocketSection from '@/components/budget/BudgetPocketSection.vue';
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
    type_label_key: string;
    monthly_plan: string | null;
    actual: string;
    progress_percent: number | null;
};

type PocketRow = {
    pocket_id: number;
    name: string;
    icon: string;
    color: string;
    monthly_plan: string | null;
    saved: string;
    released: string;
    progress_percent: number | null;
    currency: CurrencyDisplay;
};

type BudgetSummary = {
    plan: { income: string; expense: string; balance: string };
    execution: { income: string; expense: string; balance: string };
    progress: { income_percent: number | null; expense_percent: number | null };
};

const props = defineProps<{
    year: number;
    month: number;
    rows: BudgetRow[];
    pocket_rows: PocketRow[];
    summary: BudgetSummary;
    currency: CurrencyDisplay;
}>();

const { t } = useI18n();
const editingCategoryId = ref<number | null>(null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: t('budget.monthly.title'), href: route('budget.monthly') }]);

const expenseRows = computed(() => props.rows.filter((r) => r.type === 'expense'));
const incomeRows = computed(() => props.rows.filter((r) => r.type === 'income'));

function changePeriod(delta: number) {
    let m = props.month + delta;
    let y = props.year;
    if (m < 1) {
        m = 12;
        y -= 1;
    }
    if (m > 12) {
        m = 1;
        y += 1;
    }
    router.get(route('budget.monthly', { year: y, month: m }));
}

function startEdit(categoryId: number) {
    editingCategoryId.value = categoryId;
}

function cancelEdit() {
    editingCategoryId.value = null;
}

function saveMonthlyEstimate(row: { category_id: number; monthly_plan?: string | null }, rawValue: string) {
    const trimmed = rawValue.trim();
    const normalized = trimmed.replace(',', '.');
    const current = row.monthly_plan ?? '';

    if (normalized === current || (normalized === '' && current === '')) {
        editingCategoryId.value = null;
        return;
    }

    const amount = trimmed === '' ? null : normalized;

    router.patch(
        route('categories.estimates.monthly', row.category_id),
        {
            year: props.year,
            month: props.month,
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
        <Head :title="t('budget.monthly.title')" />

        <div class="budget-page flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="route('budget.monthly', { year, month })">{{ t('budget.view.monthly') }}</Link>
                    </Button>
                    <Button variant="ghost" as-child>
                        <Link :href="route('budget.yearly', { year })">{{ t('budget.view.yearly') }}</Link>
                    </Button>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="changePeriod(-1)">←</Button>
                    <span class="text-sm font-medium">{{ month }}/{{ year }}</span>
                    <Button variant="outline" @click="changePeriod(1)">→</Button>
                </div>
            </div>

            <BudgetSummaryCard :summary="summary" :currency="currency" variant="monthly" />

            <BudgetCategorySection
                :title="t('budget.sections.income')"
                category-type="income"
                :rows="incomeRows"
                :currency="currency"
                variant="monthly"
                :editing-category-id="editingCategoryId"
                :plan-placeholder="t('budget.monthly.planPlaceholder')"
                @start-edit="startEdit"
                @cancel="cancelEdit"
                @save="saveMonthlyEstimate"
            />

            <BudgetCategorySection
                :title="t('budget.sections.expense')"
                category-type="expense"
                :rows="expenseRows"
                :currency="currency"
                variant="monthly"
                :editing-category-id="editingCategoryId"
                :plan-placeholder="t('budget.monthly.planPlaceholder')"
                @start-edit="startEdit"
                @cancel="cancelEdit"
                @save="saveMonthlyEstimate"
            />

            <BudgetPocketSection :rows="pocket_rows" />
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
