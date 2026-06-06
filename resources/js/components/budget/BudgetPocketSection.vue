<script setup lang="ts">
import BudgetPocketMovementCell from '@/components/budget/BudgetPocketMovementCell.vue';
import BudgetProgressCell from '@/components/budget/BudgetProgressCell.vue';
import BudgetTableColgroup from '@/components/budget/BudgetTableColgroup.vue';
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import { Button } from '@/components/ui/button';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

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

defineProps<{
    rows: PocketRow[];
}>();

const { t } = useI18n();
</script>

<template>
    <section
        v-if="rows.length > 0"
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">{{ t('budget.monthly.pockets_section') }}</h2>
            <Button variant="link" class="h-auto p-0" as-child>
                <Link :href="route('pockets.index')">{{ t('budget.monthly.manage_pockets') }}</Link>
            </Button>
        </div>
        <div class="overflow-x-auto">
            <table class="budget-table text-sm">
                <BudgetTableColgroup layout="pocket" period="monthly" />
                <thead>
                    <tr class="border-b text-left text-muted-foreground">
                        <th class="py-2 pr-4">{{ t('pockets.index.fields.name') }}</th>
                        <th class="py-2 pr-4">{{ t('budget.monthly.plan') }}</th>
                        <th class="py-2 pr-4">{{ t('budget.monthly.movement') }}</th>
                        <th class="py-2">{{ t('budget.columns.progress') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.pocket_id"
                        class="border-b border-sidebar-border/40"
                    >
                        <td class="py-2 pr-4">
                            <PocketBadge :name="row.name" :icon="row.icon" :color="row.color" size="md" />
                        </td>
                        <td class="py-2 pr-4 tabular-nums">
                            {{ formatMoney(row.monthly_plan, row.currency) }}
                        </td>
                        <td class="py-2 pr-4">
                            <BudgetPocketMovementCell
                                :saved="row.saved"
                                :released="row.released"
                                :currency="row.currency"
                            />
                        </td>
                        <td class="py-2">
                            <BudgetProgressCell :percent="row.progress_percent" category-type="expense" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
