<script setup lang="ts">
import AccountsSummaryCard from '@/components/accounts/AccountsSummaryCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, Target, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type Currency = {
    code: string;
};

type Account = {
    type: string;
    current_balance: string;
    currency: Currency;
};

defineProps<{
    accounts: Account[];
}>();

const { t } = useI18n();

const quickActions = computed(() => [
    { key: 'accounts', href: route('accounts.index'), icon: Wallet },
    { key: 'transactions', href: route('transactions.index'), icon: ArrowLeftRight },
    { key: 'pockets', href: route('pockets.index'), icon: Target },
]);
</script>

<template>
    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-6">
            <section class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="action in quickActions"
                    :key="action.key"
                    class="rounded-2xl border border-transparent bg-card p-6 transition-colors hover:border-border"
                >
                    <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-foreground/[0.06] text-foreground">
                        <component :is="action.icon" class="size-5" :stroke-width="1.7" />
                    </div>
                    <h2 class="text-lg font-semibold tracking-tight">{{ t(`dashboard.quickActions.${action.key}.title`) }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ t(`dashboard.quickActions.${action.key}.description`) }}</p>
                    <Button class="mt-5 w-full" variant="outline" as-child>
                        <Link :href="action.href">{{ t(`dashboard.quickActions.${action.key}.cta`) }}</Link>
                    </Button>
                </article>
            </section>

            <AccountsSummaryCard :accounts="accounts" />
        </div>
    </AppLayout>
</template>
