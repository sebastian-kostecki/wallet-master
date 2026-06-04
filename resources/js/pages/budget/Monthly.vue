<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type BudgetRow = {
    category_id: number;
    name: string;
    type: string;
    type_label_key: string;
    monthly_plan: string | null;
    actual: string;
    difference: string | null;
};

type GoalRow = {
    goal_id: number;
    name: string;
    monthly_plan: string | null;
    saved: string;
    released: string;
    balance: string;
    linked_expenses: string;
};

const props = defineProps<{
    year: number;
    month: number;
    rows: BudgetRow[];
    goal_rows: GoalRow[];
    allocation_hint: { monthly_sum: string; annual_sum: string };
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('budget.monthly.title'), href: route('budget.monthly') },
]);

const expenseRows = computed(() => props.rows.filter((r) => r.type === 'expense'));
const incomeRows = computed(() => props.rows.filter((r) => r.type === 'income'));

const money = new Intl.NumberFormat('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function formatMoney(value: string | null) {
    if (value === null || value === '') {
        return '—';
    }
    const n = Number(value);
    return Number.isNaN(n) ? value : money.format(n);
}

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

function saveMonthlyEstimate(row: BudgetRow, rawValue: string) {
    const trimmed = rawValue.trim();
    const normalized = trimmed.replace(',', '.');
    const current = row.monthly_plan ?? '';

    if (normalized === current || (normalized === '' && current === '')) {
        return;
    }

    const amount = trimmed === '' ? null : normalized;

    router.patch(route('categories.estimates.monthly', row.category_id), {
        year: props.year,
        month: props.month,
        amount,
    }, { preserveScroll: true });
}

function saveGoalMonthlyEstimate(row: GoalRow, rawValue: string) {
    const trimmed = rawValue.trim();
    const normalized = trimmed.replace(',', '.');
    const current = row.monthly_plan ?? '';

    if (normalized === current || (normalized === '' && current === '')) {
        return;
    }

    const amount = trimmed === '' ? null : normalized;

    router.patch(route('goals.estimates.monthly', row.goal_id), {
        year: props.year,
        month: props.month,
        amount,
    }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('budget.monthly.title')" />

        <div class="flex flex-col gap-6 p-4">
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

            <p class="text-sm text-muted-foreground">
                {{ t('budget.monthly.allocation_hint', { monthly: formatMoney(allocation_hint.monthly_sum), annual: formatMoney(allocation_hint.annual_sum) }) }}
            </p>

            <section v-for="(sectionRows, key) in { expense: expenseRows, income: incomeRows }" :key="key" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 text-lg font-semibold">{{ t(`budget.sections.${key}`) }}</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="py-2 pr-4">{{ t('categories.index.fields.name') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.monthly.plan') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.monthly.actual') }}</th>
                                <th class="py-2">{{ t('budget.monthly.difference') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in sectionRows" :key="row.category_id" class="border-b border-sidebar-border/40">
                                <td class="py-2 pr-4">{{ row.name }}</td>
                                <td class="py-2 pr-4">
                                    <label class="sr-only" :for="`monthly-plan-${row.category_id}`">
                                        {{ t('budget.monthly.plan') }} — {{ row.name }}
                                    </label>
                                    <Input
                                        :id="`monthly-plan-${row.category_id}`"
                                        :key="`${year}-${month}-${row.category_id}-${row.monthly_plan ?? ''}`"
                                        type="text"
                                        inputmode="decimal"
                                        class="h-8 w-28 tabular-nums"
                                        :default-value="row.monthly_plan ?? ''"
                                        :placeholder="t('budget.monthly.planPlaceholder')"
                                        @blur="(e) => saveMonthlyEstimate(row, (e.target as HTMLInputElement).value)"
                                    />
                                </td>
                                <td class="py-2 pr-4">{{ formatMoney(row.actual) }}</td>
                                <td class="py-2">{{ formatMoney(row.difference) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 text-lg font-semibold">{{ t('budget.monthly.goals_section') }}</h2>
                <p v-if="goal_rows.length === 0" class="text-sm text-muted-foreground">{{ t('goals.index.empty') }}</p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="py-2 pr-4">{{ t('goals.index.fields.name') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.monthly.plan') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.monthly.saved') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.monthly.released') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.monthly.balance') }}</th>
                                <th class="py-2">{{ t('budget.monthly.linked_expenses') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in goal_rows" :key="row.goal_id" class="border-b border-sidebar-border/40">
                                <td class="py-2 pr-4">{{ row.name }}</td>
                                <td class="py-2 pr-4">
                                    <label class="sr-only" :for="`goal-plan-${row.goal_id}`">
                                        {{ t('budget.monthly.plan') }} — {{ row.name }}
                                    </label>
                                    <Input
                                        :id="`goal-plan-${row.goal_id}`"
                                        :key="`${year}-${month}-${row.goal_id}-${row.monthly_plan ?? ''}`"
                                        type="text"
                                        inputmode="decimal"
                                        class="h-8 w-28 tabular-nums"
                                        :default-value="row.monthly_plan ?? ''"
                                        :placeholder="t('budget.monthly.planPlaceholder')"
                                        @blur="(e) => saveGoalMonthlyEstimate(row, (e.target as HTMLInputElement).value)"
                                    />
                                </td>
                                <td class="py-2 pr-4">{{ formatMoney(row.saved) }}</td>
                                <td class="py-2 pr-4">{{ formatMoney(row.released) }}</td>
                                <td class="py-2 pr-4">{{ formatMoney(row.balance) }}</td>
                                <td class="py-2">{{ formatMoney(row.linked_expenses) }}</td>
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
