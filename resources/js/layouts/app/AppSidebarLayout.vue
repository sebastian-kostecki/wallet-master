<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AppToaster from '@/components/notifications/AppToaster.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <div class="mx-auto w-full max-w-[1600px] px-4 pb-8 pt-6 sm:px-6">
                <AppSidebarHeader :breadcrumbs="breadcrumbs">
                    <template #actions>
                        <slot name="headerActions" />
                    </template>
                </AppSidebarHeader>
                <slot />
            </div>
        </AppContent>
    </AppShell>
    <AppToaster />
</template>
