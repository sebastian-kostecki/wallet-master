<script setup lang="ts">
import BudgetProgressCell from '@/components/budget/BudgetProgressCell.vue';
import BudgetSummaryRowLabel from '@/components/budget/BudgetSummaryRowLabel.vue';
import BudgetTableColgroup from '@/components/budget/BudgetTableColgroup.vue';
import { formatMoney, signedMoneyClass, type CurrencyDisplay } from '@/lib/formatMoney';
import { useI18n } from 'vue-i18n';

type SummarySection = {
    income: string;
    expense: string;
    balance: string;
};

type BudgetSummary = {
    plan: SummarySection;
    execution: SummarySection;
    forecast?: SummarySection;
    progress: {
        income_percent: number | null;
        expense_percent: number | null;
    };
};

defineProps<{
    summary: BudgetSummary;
    currency: CurrencyDisplay;
    variant: 'monthly' | 'yearly';
}>();

const { t } = useI18n();
</script>

<template>
    <section class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
        <div class="overflow-x-auto">
            <table class="budget-table text-sm">
                <BudgetTableColgroup layout="summary" :period="variant" />
                <thead>
                    <tr class="border-b border-border/50 text-left text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                        <th class="py-2 pr-4" />
                        <th class="py-2 pr-4 text-right">{{ t('budget.summary.plan') }}</th>
                        <th v-if="variant === 'yearly'" class="py-2 pr-4 text-right">{{ t('budget.summary.forecast') }}</th>
                        <th class="py-2 pr-4 text-right">{{ t('budget.summary.execution') }}</th>
                        <th class="py-2 text-right">{{ t('budget.columns.progress') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border/50">
                        <th class="py-2 pr-4 text-left text-foreground">
                            <BudgetSummaryRowLabel :label="t('budget.summary.income')" variant="income" />
                        </th>
                        <td class="py-2 pr-4 text-right tabular-nums">{{ formatMoney(summary.plan.income, currency) }}</td>
                        <td v-if="variant === 'yearly' && summary.forecast" class="py-2 pr-4 text-right tabular-nums text-muted-foreground">
                            {{ formatMoney(summary.forecast.income, currency) }}
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums">{{ formatMoney(summary.execution.income, currency) }}</td>
                        <td class="py-2 text-right">
                            <BudgetProgressCell :percent="summary.progress.income_percent" category-type="income" />
                        </td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <th class="py-2 pr-4 text-left text-foreground">
                            <BudgetSummaryRowLabel :label="t('budget.summary.expense')" variant="expense" />
                        </th>
                        <td class="py-2 pr-4 text-right tabular-nums">{{ formatMoney(summary.plan.expense, currency) }}</td>
                        <td v-if="variant === 'yearly' && summary.forecast" class="py-2 pr-4 text-right tabular-nums text-muted-foreground">
                            {{ formatMoney(summary.forecast.expense, currency) }}
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums">{{ formatMoney(summary.execution.expense, currency) }}</td>
                        <td class="py-2 text-right">
                            <BudgetProgressCell :percent="summary.progress.expense_percent" category-type="expense" />
                        </td>
                    </tr>
                    <tr class="border-b border-border/50">
                        <th class="py-2 pr-4 text-left text-foreground">
                            <BudgetSummaryRowLabel :label="t('budget.summary.balance')" variant="balance" />
                        </th>
                        <td class="py-2 pr-4 text-right tabular-nums" :class="signedMoneyClass(summary.plan.balance)">
                            {{ formatMoney(summary.plan.balance, currency) }}
                        </td>
                        <td
                            v-if="variant === 'yearly' && summary.forecast"
                            class="py-2 pr-4 text-right tabular-nums"
                            :class="signedMoneyClass(summary.forecast.balance)"
                        >
                            {{ formatMoney(summary.forecast.balance, currency) }}
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums" :class="signedMoneyClass(summary.execution.balance)">
                            {{ formatMoney(summary.execution.balance, currency) }}
                        </td>
                        <td class="py-2 text-right" />
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
