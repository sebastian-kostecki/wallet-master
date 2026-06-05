<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BarChart3, CircleDollarSign, Target } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const features = [
    { key: 'accounts', icon: CircleDollarSign },
    { key: 'imports', icon: ArrowRight },
    { key: 'goals', icon: Target },
];
</script>

<template>
    <Head :title="t('welcome.headTitle')" />

    <main class="min-h-screen overflow-hidden bg-background text-foreground">
        <section class="relative isolate flex min-h-screen items-center px-6 py-10 lg:px-8">
            <div
                class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,hsl(var(--accent)),transparent_32%),linear-gradient(180deg,hsl(var(--background))_0%,hsl(var(--muted))_100%)]"
            />
            <div class="mx-auto grid w-full max-w-6xl gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                <div class="space-y-8">
                    <nav class="flex items-center justify-between">
                        <Link :href="route('home')" class="flex items-center gap-3 font-semibold">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <AppLogoIcon class="size-7 fill-current" />
                            </span>
                            <span>{{ t('brand.name') }}</span>
                        </Link>

                        <div class="flex items-center gap-3">
                            <Button v-if="$page.props.auth.user" as-child>
                                <Link :href="route('dashboard')">{{ t('welcome.dashboardCta') }}</Link>
                            </Button>
                            <template v-else>
                                <Button variant="ghost" as-child>
                                    <Link :href="route('login')">{{ t('welcome.secondaryCta') }}</Link>
                                </Button>
                                <Button as-child>
                                    <Link :href="route('register')">{{ t('welcome.primaryCta') }}</Link>
                                </Button>
                            </template>
                        </div>
                    </nav>

                    <div class="max-w-2xl space-y-6">
                        <p class="text-sm font-medium uppercase tracking-[0.28em] text-emerald-700 dark:text-emerald-300">
                            {{ t('welcome.eyebrow') }}
                        </p>
                        <h1 class="text-4xl font-semibold tracking-tight sm:text-6xl">{{ t('welcome.title') }}</h1>
                        <p class="text-lg leading-8 text-muted-foreground">{{ t('welcome.description') }}</p>
                    </div>

                    <div v-if="!$page.props.auth.user" class="flex flex-col gap-3 sm:flex-row">
                        <Button size="lg" as-child>
                            <Link :href="route('register')">{{ t('welcome.primaryCta') }}</Link>
                        </Button>
                        <Button size="lg" variant="outline" as-child>
                            <Link :href="route('login')">{{ t('welcome.secondaryCta') }}</Link>
                        </Button>
                    </div>
                </div>

                <aside class="rounded-[2rem] border border-border/80 bg-card/90 p-6 shadow-2xl shadow-slate-950/10 backdrop-blur">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">{{ t('brand.shortPromise') }}</p>
                            <h2 class="text-xl font-semibold">{{ t('brand.name') }}</h2>
                        </div>
                        <BarChart3 class="size-8 text-emerald-600 dark:text-emerald-300" />
                    </div>

                    <div class="space-y-4">
                        <div v-for="feature in features" :key="feature.key" class="rounded-2xl border border-border/80 bg-background/80 p-4">
                            <div class="mb-3 flex items-center gap-3">
                                <span class="flex size-10 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                                    <component :is="feature.icon" class="size-5" />
                                </span>
                                <h3 class="font-semibold">{{ t(`welcome.features.${feature.key}.title`) }}</h3>
                            </div>
                            <p class="text-sm leading-6 text-muted-foreground">{{ t(`welcome.features.${feature.key}.description`) }}</p>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </main>
</template>
