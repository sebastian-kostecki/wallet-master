<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, LayoutGrid, PieChart, Tags, Target, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { transactionsIndexHref } = useTransactionsIndexSearch();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Konta',
        href: '/accounts',
        icon: Wallet,
    },
    {
        title: 'Transakcje',
        href: transactionsIndexHref.value,
        icon: ArrowLeftRight,
    },
    {
        title: 'Budżet',
        href: route('budget.monthly'),
        icon: PieChart,
    },
    {
        title: 'Kategorie',
        href: route('categories.index'),
        icon: Tags,
    },
    {
        title: 'Cele',
        href: route('goals.index'),
        icon: Target,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
