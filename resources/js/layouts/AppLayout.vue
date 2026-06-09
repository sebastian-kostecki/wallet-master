<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { useI18n } from 'vue-i18n';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { t } = useI18n();
</script>

<template>
    <a
        href="#main-content"
        class="sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:m-0 focus:flex focus:h-auto focus:w-auto focus:items-center focus:overflow-visible focus:rounded-md focus:border focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-foreground focus:shadow-md focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
    >
        {{ t('a11y.skip_to_content') }}
    </a>
    <AppLayout :breadcrumbs="breadcrumbs">
        <template #headerActions>
            <slot name="headerActions" />
        </template>
        <slot />
    </AppLayout>
</template>
