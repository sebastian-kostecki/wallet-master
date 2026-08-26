<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, BarChart3, Target, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

const { t } = useI18n();

const quickActions = computed(() => [
    { key: 'accounts', href: route('accounts.index'), icon: Wallet },
    { key: 'transactions', href: route('transactions.index'), icon: ArrowLeftRight },
    { key: 'pockets', href: route('pockets.index'), icon: Target },
]);

const previewItems = ['imports', 'budget', 'pockets'];
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

            <section class="grid gap-4 rounded-2xl border border-transparent bg-card p-6 lg:grid-cols-[0.9fr_1.1fr]">
                <div>
                    <h2 class="text-sm font-semibold tracking-tight text-foreground">{{ t('dashboard.preview.title') }}</h2>
                    <div class="mt-6 space-y-3">
                        <div v-for="item in previewItems" :key="item" class="flex items-center gap-3 rounded-xl bg-foreground/[0.03] p-3 text-sm">
                            <span class="size-2 rounded-full bg-emerald-500" />
                            <span>{{ t(`dashboard.preview.items.${item}`) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-border/50 bg-background p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground">{{ t('brand.shortPromise') }}</span>
                        <BarChart3 class="size-5 text-muted-foreground" :stroke-width="1.7" />
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
