<script setup lang="ts">
import PageTabs from '@/components/page/PageTabs.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCurrentPage } from '@/composables/useCurrentPage';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    title?: string;
    description?: string;
}>();

const chrome = useCurrentPage(computed(() => ({ title: props.title, description: props.description })));

const parentCrumb = computed(() => {
    const { crumbs } = chrome.value;

    return crumbs.length > 1 ? crumbs[crumbs.length - 2] : null;
});
</script>

<template>
    <Head v-if="chrome.documentTitle" :title="chrome.documentTitle" />

    <header class="mb-6">
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="md:hidden" />

            <Link
                v-if="parentCrumb?.href"
                :href="parentCrumb.href"
                class="flex items-center gap-1 text-sm text-muted-foreground transition-colors duration-150 hover:text-foreground sm:hidden"
            >
                <ChevronLeft class="size-4" :stroke-width="1.7" aria-hidden="true" />
                {{ parentCrumb.title }}
            </Link>

            <Breadcrumb v-if="chrome.crumbs.length > 0" class="hidden sm:block">
                <BreadcrumbList>
                    <template v-for="(crumb, index) in chrome.crumbs" :key="index">
                        <BreadcrumbItem>
                            <BreadcrumbLink v-if="crumb.href" :href="crumb.href">{{ crumb.title }}</BreadcrumbLink>
                            <BreadcrumbPage v-else class="font-medium">{{ crumb.title }}</BreadcrumbPage>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator v-if="index !== chrome.crumbs.length - 1" />
                    </template>
                </BreadcrumbList>
            </Breadcrumb>
        </div>

        <div class="mt-2 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 v-if="chrome.title" class="text-xl font-semibold tracking-tight">{{ chrome.title }}</h1>
                <p v-if="chrome.description" class="mt-1 text-sm text-muted-foreground">{{ chrome.description }}</p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <PageTabs v-if="chrome.tabs.length > 0" :tabs="chrome.tabs" class="mt-4" />
    </header>
</template>
