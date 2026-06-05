<script setup lang="ts">
import BudgetProgressCell from '@/components/budget/BudgetProgressCell.vue';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
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
    <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-muted-foreground">
                        <th class="py-2 pr-4" />
                        <th class="py-2 pr-4">{{ t('budget.summary.plan') }}</th>
                        <th class="py-2 pr-4">{{ t('budget.summary.execution') }}</th>
                        <th v-if="variant === 'yearly'" class="py-2">{{ t('budget.summary.forecast') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-sidebar-border/40">
                        <th class="py-2 pr-4 text-left font-medium text-foreground">{{ t('budget.summary.income') }}</th>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.plan.income, currency) }}</td>
                        <td class="py-2 pr-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="tabular-nums">{{ formatMoney(summary.execution.income, currency) }}</span>
                                <BudgetProgressCell :percent="summary.progress.income_percent" category-type="income" />
                            </div>
                        </td>
                        <td v-if="variant === 'yearly' && summary.forecast" class="py-2 tabular-nums">
                            {{ formatMoney(summary.forecast.income, currency) }}
                        </td>
                    </tr>
                    <tr class="border-b border-sidebar-border/40">
                        <th class="py-2 pr-4 text-left font-medium text-foreground">{{ t('budget.summary.expense') }}</th>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.plan.expense, currency) }}</td>
                        <td class="py-2 pr-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="tabular-nums">{{ formatMoney(summary.execution.expense, currency) }}</span>
                                <BudgetProgressCell :percent="summary.progress.expense_percent" category-type="expense" />
                            </div>
                        </td>
                        <td v-if="variant === 'yearly' && summary.forecast" class="py-2 tabular-nums">
                            {{ formatMoney(summary.forecast.expense, currency) }}
                        </td>
                    </tr>
                    <tr class="border-b border-sidebar-border/40">
                        <th class="py-2 pr-4 text-left font-medium text-foreground">{{ t('budget.summary.balance') }}</th>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.plan.balance, currency) }}</td>
                        <td class="py-2 pr-4 tabular-nums">{{ formatMoney(summary.execution.balance, currency) }}</td>
                        <td v-if="variant === 'yearly' && summary.forecast" class="py-2 tabular-nums">
                            {{ formatMoney(summary.forecast.balance, currency) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
