<script setup lang="ts">
defineProps<{
    label: string;
    value: string;
    tone?: 'default' | 'income' | 'expense';
}>();
</script>

<template>
    <article class="rounded-2xl border border-transparent bg-card p-5 sm:p-6">
        <p class="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">{{ label }}</p>
        <p
            class="mt-2 text-2xl font-semibold tabular-nums tracking-tight"
            :class="{
                'text-emerald-600 dark:text-emerald-400': tone === 'income',
                'text-red-600 dark:text-red-400': tone === 'expense',
            }"
        >
            {{ value }}
        </p>
    </article>
</template>
