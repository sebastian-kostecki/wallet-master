<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header class="mb-6 flex items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="md:hidden" />
            <Breadcrumb v-if="breadcrumbs.length > 0">
                <BreadcrumbList>
                    <template v-for="(item, index) in breadcrumbs" :key="index">
                        <BreadcrumbItem>
                            <template v-if="index === breadcrumbs.length - 1">
                                <BreadcrumbPage class="font-medium">{{ item.title }}</BreadcrumbPage>
                            </template>
                            <template v-else>
                                <BreadcrumbLink :href="item.href">
                                    {{ item.title }}
                                </BreadcrumbLink>
                            </template>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                    </template>
                </BreadcrumbList>
            </Breadcrumb>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
