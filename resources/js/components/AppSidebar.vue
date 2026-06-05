<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, LayoutGrid, PieChart, Tags, Target, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from './AppLogo.vue';

const { t } = useI18n();
const { transactionsIndexHref } = useTransactionsIndexSearch();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: t('nav.dashboard'),
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: t('accounts.index.title'),
        href: '/accounts',
        icon: Wallet,
    },
    {
        title: t('transactions.index.title'),
        href: transactionsIndexHref.value,
        icon: ArrowLeftRight,
    },
    {
        title: t('budget.nav'),
        href: route('budget.monthly'),
        icon: PieChart,
    },
    {
        title: t('categories.index.title'),
        href: route('categories.index'),
        icon: Tags,
    },
    {
        title: t('goals.index.title'),
        href: route('goals.index'),
        icon: Target,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-sidebar-border/80">
        <SidebarHeader class="border-b border-sidebar-border/70">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                    >
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
