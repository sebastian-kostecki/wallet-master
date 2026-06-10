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
    <section class="rounded-xl border border-sidebar-border/70 bg-muted/30 p-4 shadow-sm dark:border-sidebar-border dark:bg-muted/20">
        <div class="overflow-x-auto">
            <table class="budget-table text-sm">
                <BudgetTableColgroup layout="summary" :period="variant" />
                <thead>
                    <tr class="border-b text-left text-muted-foreground">
                        <th class="py-2 pr-4" />
                        <th class="py-2 pr-4">{{ t('budget.summary.plan') }}</th>
                        <th v-if="variant === 'yearly'" class="py-2 pr-4 text-muted-foreground">{{ t('budget.summary.forecast') }}</th>
                        <th class="py-2 pr-4">{{ t('budget.summary.execution') }}</th>
                        <th class="py-2">{{ t('budget.columns.progress') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-sidebar-border/40">
                        <th class="py-2 pr-4 text-left text-foreground">
                            <BudgetSummaryRowLabel :label="t('budget.summary.income')" variant="income" />
                        </th>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.plan.income, currency) }}</td>
                        <td v-if="variant === 'yearly' && summary.forecast" class="py-2 pr-4 tabular-nums text-muted-foreground">
                            {{ formatMoney(summary.forecast.income, currency) }}
                        </td>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.execution.income, currency) }}</td>
                        <td class="py-2">
                            <BudgetProgressCell :percent="summary.progress.income_percent" category-type="income" />
                        </td>
                    </tr>
                    <tr class="border-b border-sidebar-border/40">
                        <th class="py-2 pr-4 text-left text-foreground">
                            <BudgetSummaryRowLabel :label="t('budget.summary.expense')" variant="expense" />
                        </th>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.plan.expense, currency) }}</td>
                        <td v-if="variant === 'yearly' && summary.forecast" class="py-2 pr-4 tabular-nums text-muted-foreground">
                            {{ formatMoney(summary.forecast.expense, currency) }}
                        </td>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.execution.expense, currency) }}</td>
                        <td class="py-2">
                            <BudgetProgressCell :percent="summary.progress.expense_percent" category-type="expense" />
                        </td>
                    </tr>
                    <tr class="border-b border-sidebar-border/40">
                        <th class="py-2 pr-4 text-left text-foreground">
                            <BudgetSummaryRowLabel :label="t('budget.summary.balance')" variant="balance" />
                        </th>
                        <td class="py-2 pr-4 tabular-nums" :class="signedMoneyClass(summary.plan.balance)">
                            {{ formatMoney(summary.plan.balance, currency) }}
                        </td>
                        <td
                            v-if="variant === 'yearly' && summary.forecast"
                            class="py-2 pr-4 tabular-nums"
                            :class="signedMoneyClass(summary.forecast.balance)"
                        >
                            {{ formatMoney(summary.forecast.balance, currency) }}
                        </td>
                        <td class="py-2 pr-4 tabular-nums" :class="signedMoneyClass(summary.execution.balance)">
                            {{ formatMoney(summary.execution.balance, currency) }}
                        </td>
                        <td class="py-2" />
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
