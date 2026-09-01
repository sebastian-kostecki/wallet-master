<script setup lang="ts">
import DashboardTransactionRow from '@/components/dashboard/DashboardTransactionRow.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

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

defineProps<{
    transactions: Transaction[];
}>();

const { t } = useI18n();
</script>

<template>
    <section class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
        <div class="mb-4 flex items-baseline justify-between gap-3">
            <h2 class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                {{ t('dashboard.recent.title') }}
            </h2>
            <Link :href="route('transactions.index')" class="text-sm text-muted-foreground transition-colors duration-150 hover:text-foreground">
                {{ t('dashboard.recent.open') }}
            </Link>
        </div>

        <p v-if="transactions.length === 0" class="text-sm text-muted-foreground">{{ t('dashboard.recent.empty') }}</p>
        <ul v-else class="-mx-3">
            <DashboardTransactionRow v-for="transaction in transactions" :key="transaction.id" :transaction="transaction" />
        </ul>
    </section>
</template>
