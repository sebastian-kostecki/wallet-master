<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftRight, BarChart3, Target, Wallet } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const quickActions = [
    { key: 'accounts', href: route('accounts.index'), icon: Wallet },
    { key: 'transactions', href: route('transactions.index'), icon: ArrowLeftRight },
    { key: 'goals', href: route('goals.index'), icon: Target },
];

const previewItems = ['imports', 'budget', 'goals'];
</script>

<template>
    <Head :title="t('dashboard.headTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <section
                class="overflow-hidden rounded-3xl border border-sidebar-border/70 bg-gradient-to-br from-primary via-slate-800 to-emerald-900 p-6 text-primary-foreground shadow-sm dark:from-slate-950 dark:via-slate-900 dark:to-emerald-950 md:p-8"
            >
                <div class="max-w-3xl space-y-3">
                    <p class="text-sm font-medium uppercase tracking-[0.28em] text-emerald-200">{{ t('brand.shortPromise') }}</p>
                    <h1 class="text-3xl font-semibold tracking-tight md:text-4xl">{{ t('dashboard.title') }}</h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-200 md:text-base">{{ t('dashboard.description') }}</p>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="action in quickActions"
                    :key="action.key"
                    class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border"
                >
                    <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                        <component :is="action.icon" class="size-5" />
                    </div>
                    <h2 class="text-lg font-semibold">{{ t(`dashboard.quickActions.${action.key}.title`) }}</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ t(`dashboard.quickActions.${action.key}.description`) }}</p>
                    <Button class="mt-5 w-full" variant="outline" as-child>
                        <Link :href="action.href">{{ t(`dashboard.quickActions.${action.key}.cta`) }}</Link>
                    </Button>
                </article>
            </section>

            <section
                class="grid gap-4 rounded-3xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border lg:grid-cols-[0.9fr_1.1fr]"
            >
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-emerald-700 dark:text-emerald-300">{{ t('brand.name') }}</p>
                    <h2 class="mt-3 text-2xl font-semibold">{{ t('dashboard.preview.title') }}</h2>
                    <div class="mt-6 space-y-3">
                        <div v-for="item in previewItems" :key="item" class="flex items-center gap-3 rounded-xl bg-muted/60 p-3 text-sm">
                            <span class="size-2 rounded-full bg-emerald-500" />
                            <span>{{ t(`dashboard.preview.items.${item}`) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-border/80 bg-background p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground">{{ t('brand.shortPromise') }}</span>
                        <BarChart3 class="size-5 text-emerald-600 dark:text-emerald-300" />
                    </div>
                    <div class="space-y-3">
                        <div class="h-3 rounded-full bg-muted">
                            <div class="h-3 w-2/3 rounded-full bg-emerald-500" />
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="h-24 rounded-xl bg-emerald-500/20" />
                            <div class="h-24 rounded-xl bg-primary/15" />
                            <div class="h-24 rounded-xl bg-emerald-500/10" />
                        </div>
                        <div class="h-28 rounded-xl border border-dashed border-border bg-muted/40" />
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
