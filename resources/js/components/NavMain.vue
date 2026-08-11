<script setup lang="ts">
import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();

function pathOf(url: string): string {
    if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
            return new URL(url).pathname;
        } catch {
            return url.split('?')[0];
        }
    }

    return url.split('?')[0];
}

function isNavItemActive(href: string): boolean {
    const current = pathOf(page.url);
    const target = pathOf(href);

    if (target === '/' || /\/dashboard\/?$/.test(target)) {
        return current === target || /\/dashboard\/?$/.test(current);
    }

    return current === target || current.startsWith(`${target}/`);
}
</script>

<template>
    <SidebarGroup class="p-0">
        <SidebarMenu class="gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isNavItemActive(item.href)"
                    :tooltip="item.title"
                    class="h-auto rounded-xl px-3 py-2.5 text-[15px] font-medium text-muted-foreground transition-colors hover:bg-foreground/[0.04] hover:text-foreground data-[active=true]:bg-foreground/[0.07] data-[active=true]:font-medium data-[active=true]:text-foreground group-data-[collapsible=icon]:!size-9 group-data-[collapsible=icon]:!p-2 [&>svg]:!size-[18px]"
                >
                    <Link :href="item.href" :aria-current="isNavItemActive(item.href) ? 'page' : undefined">
                        <component :is="item.icon" v-if="item.icon" :stroke-width="1.7" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
