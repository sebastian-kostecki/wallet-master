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
    annual_plan: string | null;
    actual: string;
    difference: string | null;
};

const props = defineProps<{
    year: number;
    rows: BudgetRow[];
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('budget.yearly.title'), href: route('budget.yearly') },
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

function saveAnnualEstimate(row: BudgetRow, rawValue: string) {
    const trimmed = rawValue.trim();
    const normalized = trimmed.replace(',', '.');
    const current = row.annual_plan ?? '';

    if (normalized === current || (normalized === '' && current === '')) {
        return;
    }

    const amount = trimmed === '' ? null : normalized;

    router.patch(route('categories.estimates.annual', row.category_id), {
        year: props.year,
        amount,
    }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('budget.yearly.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex gap-2">
                    <Button variant="ghost" as-child>
                        <Link :href="route('budget.monthly', { year, month: 1 })">{{ t('budget.view.monthly') }}</Link>
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

            <section v-for="(sectionRows, key) in { expense: expenseRows, income: incomeRows }" :key="key" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 text-lg font-semibold">{{ t(`budget.sections.${key}`) }}</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="py-2 pr-4">{{ t('categories.index.fields.name') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.yearly.plan') }}</th>
                                <th class="py-2 pr-4">{{ t('budget.yearly.actual') }}</th>
                                <th class="py-2">{{ t('budget.yearly.difference') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in sectionRows" :key="row.category_id" class="border-b border-sidebar-border/40">
                                <td class="py-2 pr-4">{{ row.name }}</td>
                                <td class="py-2 pr-4">
                                    <label class="sr-only" :for="`annual-plan-${row.category_id}`">
                                        {{ t('budget.yearly.plan') }} — {{ row.name }}
                                    </label>
                                    <Input
                                        :id="`annual-plan-${row.category_id}`"
                                        :key="`${year}-${row.category_id}-${row.annual_plan ?? ''}`"
                                        type="text"
                                        inputmode="decimal"
                                        class="h-8 w-28 tabular-nums"
                                        :default-value="row.annual_plan ?? ''"
                                        :placeholder="t('budget.yearly.planPlaceholder')"
                                        @blur="(e) => saveAnnualEstimate(row, (e.target as HTMLInputElement).value)"
                                    />
                                </td>
                                <td class="py-2 pr-4">{{ formatMoney(row.actual) }}</td>
                                <td class="py-2">{{ formatMoney(row.difference) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <Button variant="secondary" as-child>
                <Link :href="route('categories.index')">{{ t('budget.yearly.manage_categories') }}</Link>
            </Button>
        </div>
    </AppLayout>
</template>
