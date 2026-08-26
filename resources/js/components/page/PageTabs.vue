<script setup lang="ts">
import type { ChromeTab } from '@/lib/pageChrome';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
    tabs: ChromeTab[];
}>();

const { t } = useI18n();
</script>

<template>
    <nav :aria-label="t('a11y.page_tabs')" class="-mb-px flex gap-1 overflow-x-auto border-b border-border/50">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            preserve-scroll
            :aria-current="tab.isActive ? 'page' : undefined"
            class="relative whitespace-nowrap px-3 py-2 text-sm transition-colors duration-150"
            :class="tab.isActive ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
        >
            {{ tab.label }}
            <span v-if="tab.isActive" class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary" aria-hidden="true" />
        </Link>
    </nav>
</template>
