<script setup lang="ts">
import DashboardBudgetCard from '@/components/dashboard/DashboardBudgetCard.vue';
import DashboardKpiCard from '@/components/dashboard/DashboardKpiCard.vue';
import DashboardRecentCard from '@/components/dashboard/DashboardRecentCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type SummarySection = {
    income: string;
    expense: string;
    balance: string;
};

type BudgetSummary = {
    plan: SummarySection;
    execution: SummarySection;
    progress: {
        income_percent: number | null;
        expense_percent: number | null;
    };
};

type Transaction = {
    id: number;
    booked_at: string;
    date: string;
    date_relative: string;
    description: string;
    amount: string | number;
    category: { name: string; icon: string; color: string } | null;
    currency: { symbol: string | null; precision: number } | null;
};

const props = defineProps<{
    period: { year: number; month: number };
    balances: { total_pln: string; savings_pln: string; deposits_pln: string };
    cashflow: { income: string; expense: string };
    budget_summary: BudgetSummary;
    currency: CurrencyDisplay;
    recent_transactions: Transaction[];
}>();

const { t, locale } = useI18n();

const periodLabel = computed(() => {
    const date = new Date(props.period.year, props.period.month - 1, 1);

    return date.toLocaleDateString(locale.value === 'en' ? 'en-GB' : 'pl-PL', {
        month: 'long',
        year: 'numeric',
    });
});
</script>

<template>
    <AppLayout :description="periodLabel">
        <template #headerActions>
            <Button as-child>
                <Link :href="route('transactions.create')">
                    <Plus class="size-4" :stroke-width="1.7" />
                    {{ t('transactions.index.addTransaction') }}
                </Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <DashboardKpiCard :label="t('accounts.summary.totalPln')" :value="formatMoney(balances.total_pln, currency)" />
                <DashboardKpiCard :label="t('accounts.summary.savingsPln')" :value="formatMoney(balances.savings_pln, currency)" />
                <Link
                    :href="route('accounts.index', { kind: 'deposits' })"
                    class="block rounded-2xl transition-colors duration-150 hover:border-border/40 hover:bg-foreground/[0.02]"
                >
                    <DashboardKpiCard :label="t('accounts.summary.depositsPln')" :value="formatMoney(balances.deposits_pln, currency)" />
                </Link>
                <DashboardKpiCard :label="t('budget.summary.income')" :value="formatMoney(cashflow.income, currency)" tone="income" />
                <DashboardKpiCard :label="t('budget.summary.expense')" :value="formatMoney(cashflow.expense, currency)" tone="expense" />
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <DashboardBudgetCard :summary="budget_summary" :currency="currency" :year="period.year" :month="period.month" />
                <DashboardRecentCard :transactions="recent_transactions" />
            </section>
        </div>
    </AppLayout>
</template>
