<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppToaster from '@/components/notifications/AppToaster.vue';
import PageHeader from '@/components/page/PageHeader.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <div class="mx-auto w-full max-w-[1600px] px-4 pb-8 pt-6 sm:px-6">
                <PageHeader :title="title" :description="description">
                    <template #actions>
                        <slot name="headerActions" />
                    </template>
                    <template v-if="$slots.headerTabsTrailing" #tabsTrailing>
                        <slot name="headerTabsTrailing" />
                    </template>
                </PageHeader>
                <slot />
            </div>
        </AppContent>
    </AppShell>
    <AppToaster />
</template>
