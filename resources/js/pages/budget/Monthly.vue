<script setup lang="ts">
import BudgetCategorySection from '@/components/budget/BudgetCategorySection.vue';
import BudgetSummaryCard from '@/components/budget/BudgetSummaryCard.vue';
import GoalBadge from '@/components/goals/GoalBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney as formatGoalMoney, type CurrencyDisplay } from '@/lib/formatMoney';
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

type GoalRow = {
    goal_id: number;
    name: string;
    icon: string;
    color: string;
    monthly_plan: string | null;
    saved: string;
    released: string;
    balance: string;
    balance_cumulative: string;
    target_amount: string | null;
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
    goal_rows: GoalRow[];
    summary: BudgetSummary;
    currency: CurrencyDisplay;
}>();

const { t } = useI18n();
const editingCategoryId = ref<number | null>(null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('budget.monthly.title'), href: route('budget.monthly') },
]);

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

function saveMonthlyEstimate(row: BudgetRow, rawValue: string) {
    const trimmed = rawValue.trim();
    const normalized = trimmed.replace(',', '.');
    const current = row.monthly_plan ?? '';

    if (normalized === current || (normalized === '' && current === '')) {
        editingCategoryId.value = null;
        return;
    }

    const amount = trimmed === '' ? null : normalized;

    router.patch(route('categories.estimates.monthly', row.category_id), {
        year: props.year,
        month: props.month,
        amount,
    }, {
        preserveScroll: true,
        onFinish: () => {
            editingCategoryId.value = null;
        },
    });
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

            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 text-lg font-semibold">{{ t('budget.monthly.goals_section') }}</h2>
                <p v-if="goal_rows.length === 0" class="text-sm text-muted-foreground">{{ t('goals.index.empty') }}</p>
                <div v-else class="overflow-x-auto">
                    <table class="budget-table text-sm">
                        <colgroup>
                            <col class="budget-col-label" />
                        </colgroup>
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="py-2 pr-4">{{ t('goals.index.fields.name') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.monthly.plan') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.monthly.saved') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.monthly.released') }}</th>
                                <th class="py-2">{{ t('budget.monthly.balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in goal_rows" :key="row.goal_id" class="border-b border-sidebar-border/40">
                                <td class="py-2 pr-4">
                                    <GoalBadge :name="row.name" :icon="row.icon" :color="row.color" size="md" />
                                </td>
                                <td class="py-2 pr-4">
                                    <div class="space-y-1">
                                        <span class="tabular-nums">{{ formatGoalMoney(row.monthly_plan, row.currency) }}</span>
                                        <p v-if="row.target_amount !== null" class="text-xs text-muted-foreground">
                                            {{ formatGoalMoney(row.balance_cumulative, row.currency) }} / {{ formatGoalMoney(row.target_amount, row.currency) }}
                                        </p>
                                    </div>
                                </td>
                                <td class="py-2 pr-4">{{ formatGoalMoney(row.saved, row.currency) }}</td>
                                <td class="py-2 pr-4">{{ formatGoalMoney(row.released, row.currency) }}</td>
                                <td class="py-2">{{ formatGoalMoney(row.balance, row.currency) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="flex flex-wrap gap-2">
                <Button variant="secondary" as-child>
                    <Link :href="route('categories.index')">{{ t('categories.index.title') }}</Link>
                </Button>
                <Button variant="secondary" as-child>
                    <Link :href="route('goals.index')">{{ t('budget.monthly.manage_goals') }}</Link>
                </Button>
            </div>
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
