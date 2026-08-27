<script setup lang="ts">
import BudgetProgressCell from '@/components/budget/BudgetProgressCell.vue';
import DashboardPlanActualBars from '@/components/dashboard/DashboardPlanActualBars.vue';
import { formatMoney, signedMoneyClass, type CurrencyDisplay } from '@/lib/formatMoney';
import { Link } from '@inertiajs/vue3';
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

const props = defineProps<{
    summary: BudgetSummary;
    currency: CurrencyDisplay;
    year: number;
    month: number;
}>();

const { t } = useI18n();
</script>

<template>
    <section class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
        <div class="mb-5 flex items-baseline justify-between gap-3">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                {{ t('dashboard.budget.title') }}
            </h2>
            <Link
                :href="route('budget.monthly', { year: props.year, month: props.month })"
                class="text-sm text-muted-foreground transition-colors duration-150 hover:text-foreground"
            >
                {{ t('dashboard.budget.open') }}
            </Link>
        </div>

        <DashboardPlanActualBars
            :plan-income="summary.plan.income"
            :actual-income="summary.execution.income"
            :plan-expense="summary.plan.expense"
            :actual-expense="summary.execution.expense"
        />

        <div class="mt-6 grid gap-3 text-sm">
            <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-b border-border/50 pb-3">
                <span class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60" />
                <span class="text-right text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">{{
                    t('budget.summary.plan')
                }}</span>
                <span class="text-right text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">{{
                    t('budget.summary.execution')
                }}</span>
                <span class="text-right text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">{{
                    t('budget.columns.progress')
                }}</span>
            </div>
            <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-b border-border/50 pb-3">
                <span>{{ t('budget.summary.income') }}</span>
                <span class="text-right tabular-nums">{{ formatMoney(summary.plan.income, currency) }}</span>
                <span class="text-right tabular-nums">{{ formatMoney(summary.execution.income, currency) }}</span>
                <span class="justify-self-end"><BudgetProgressCell :percent="summary.progress.income_percent" category-type="income" /></span>
            </div>
            <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-b border-border/50 pb-3">
                <span>{{ t('budget.summary.expense') }}</span>
                <span class="text-right tabular-nums">{{ formatMoney(summary.plan.expense, currency) }}</span>
                <span class="text-right tabular-nums">{{ formatMoney(summary.execution.expense, currency) }}</span>
                <span class="justify-self-end"><BudgetProgressCell :percent="summary.progress.expense_percent" category-type="expense" /></span>
            </div>
            <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3">
                <span>{{ t('budget.summary.balance') }}</span>
                <span class="text-right tabular-nums" :class="signedMoneyClass(summary.plan.balance)">{{
                    formatMoney(summary.plan.balance, currency)
                }}</span>
                <span class="text-right tabular-nums" :class="signedMoneyClass(summary.execution.balance)">{{
                    formatMoney(summary.execution.balance, currency)
                }}</span>
                <span />
            </div>
        </div>
    </section>
</template>
