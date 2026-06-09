<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';
import { BarChart3, CircleDollarSign, Target } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    title?: string;
    description?: string;
}>();

const { t } = useI18n();

const benefits = [
    { key: 'imports', icon: CircleDollarSign },
    { key: 'budget', icon: BarChart3 },
    { key: 'pockets', icon: Target },
];
</script>

<template>
    <div class="grid min-h-svh bg-slate-950 text-white lg:grid-cols-[1.05fr_0.95fr]">
        <section class="relative hidden overflow-hidden p-10 lg:flex lg:flex-col">
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.28),transparent_34%),linear-gradient(135deg,#020617_0%,#0f172a_52%,#064e3b_100%)]"
            />
            <div class="absolute inset-x-10 top-1/3 h-px bg-gradient-to-r from-transparent via-emerald-300/40 to-transparent" />
            <div class="absolute bottom-16 right-12 h-48 w-48 rounded-full border border-emerald-300/20" />

            <div class="relative z-10 flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-950/40">
                    <AppLogoIcon class="size-7 fill-current" />
                </div>
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.28em] text-emerald-200">{{ t('auth.brand.eyebrow') }}</p>
                    <p class="text-lg font-semibold">{{ t('brand.name') }}</p>
                </div>
            </div>

            <div class="relative z-10 mt-auto max-w-xl space-y-8">
                <div class="space-y-4">
                    <p class="text-sm font-medium uppercase tracking-[0.28em] text-emerald-200">{{ t('brand.shortPromise') }}</p>
                    <h1 class="text-4xl font-semibold tracking-tight text-white xl:text-5xl">{{ t('auth.brand.promise') }}</h1>
                    <p class="max-w-lg text-base leading-7 text-slate-300">{{ t('auth.brand.description') }}</p>
                </div>

                <div class="grid gap-3">
                    <div
                        v-for="benefit in benefits"
                        :key="benefit.key"
                        class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur"
                    >
                        <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-400/15 text-emerald-200">
                            <component :is="benefit.icon" class="size-5" />
                        </div>
                        <span class="text-sm font-medium text-slate-100">{{ t(`auth.brand.benefits.${benefit.key}`) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="flex min-h-svh items-center justify-center bg-background px-6 py-10 text-foreground lg:rounded-l-[2rem] lg:px-10">
            <div class="w-full max-w-md">
                <div class="mb-8 flex flex-col items-center gap-4 text-center lg:hidden">
                    <Link :href="route('home')" class="flex items-center gap-3 font-medium">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                            <AppLogoIcon class="size-7 fill-current" />
                        </div>
                        <span>{{ t('brand.name') }}</span>
                    </Link>
                </div>

                <div class="rounded-3xl border border-border/80 bg-card p-6 shadow-xl shadow-slate-950/5 sm:p-8">
                    <div class="mb-8 space-y-2 text-center">
                        <h1 v-if="title" class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                        <p v-if="description" class="text-sm leading-6 text-muted-foreground">{{ description }}</p>
                    </div>
                    <slot />
                </div>
            </div>
        </section>
    </div>
</template>
