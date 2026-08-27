<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import SidebarThemeToggle from '@/components/SidebarThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, useSidebar } from '@/components/ui/sidebar';
import { requestOpenCreateTransaction } from '@/composables/useTransactionFormDialog';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeftRight, LayoutGrid, PanelLeftClose, PanelLeftOpen, PieChart, Plus, Settings, Target, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';
import AppLogo from './AppLogo.vue';

const { t } = useI18n();
const { transactionsIndexHref } = useTransactionsIndexSearch();
const { state, toggleSidebar, isMobile } = useSidebar();

const isCollapsed = computed(() => state.value === 'collapsed' && !isMobile.value);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: t('nav.dashboard'),
        href: route('dashboard'),
        icon: LayoutGrid,
    },
    {
        title: t('nav.accounts'),
        href: route('accounts.index'),
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
        title: t('nav.pockets'),
        href: route('pockets.index'),
        icon: Target,
    },
    {
        title: t('nav.settings'),
        href: route('categories.index'),
        icon: Settings,
    },
]);

const page = usePage();
const isTransactionsIndex = computed(() => page.component === 'transactions/Index');
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-0">
        <SidebarHeader class="gap-0 p-0">
            <div class="flex items-center px-4 py-5" :class="isCollapsed ? 'flex-col gap-3 pb-3' : 'justify-between gap-2'">
                <Link
                    :href="route('dashboard')"
                    class="flex min-w-0 items-center gap-2.5 overflow-hidden"
                    :class="isCollapsed ? 'justify-center' : ''"
                >
                    <AppLogo compact />
                </Link>

                <div class="flex shrink-0 items-center" :class="isCollapsed ? 'flex-col gap-1' : 'gap-1'">
                    <SidebarThemeToggle />
                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-foreground/[0.06] hover:text-foreground"
                        :aria-label="isCollapsed ? t('nav.expand') : t('nav.collapse')"
                        :title="isCollapsed ? t('nav.expand') : t('nav.collapse')"
                        @click="toggleSidebar"
                    >
                        <PanelLeftOpen v-if="isCollapsed" class="size-4" />
                        <PanelLeftClose v-else class="size-4" />
                    </button>
                </div>
            </div>
            <div class="mx-3 h-px bg-sidebar-border/60" />
        </SidebarHeader>

        <SidebarContent class="relative min-h-0 gap-0 overflow-hidden px-0 pb-0 pt-0">
            <div class="h-full overflow-y-auto px-3 pb-12 pt-3 group-data-[collapsible=icon]:overflow-hidden">
                <NavMain :items="mainNavItems" />
            </div>
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-sidebar to-transparent" aria-hidden="true" />
        </SidebarContent>

        <div class="px-3 pb-2 pt-1">
            <Button
                v-if="isTransactionsIndex"
                type="button"
                :size="isCollapsed ? 'icon' : 'default'"
                class="w-full group-data-[collapsible=icon]:size-8"
                :title="t('nav.addTransaction')"
                @click="requestOpenCreateTransaction()"
            >
                <Plus class="size-4 shrink-0" :stroke-width="1.7" />
                <span class="group-data-[collapsible=icon]:hidden">{{ t('nav.addTransaction') }}</span>
            </Button>
            <Button v-else as-child :size="isCollapsed ? 'icon' : 'default'" class="w-full group-data-[collapsible=icon]:size-8">
                <Link :href="route('transactions.create')" :title="t('nav.addTransaction')">
                    <Plus class="size-4 shrink-0" :stroke-width="1.7" />
                    <span class="group-data-[collapsible=icon]:hidden">{{ t('nav.addTransaction') }}</span>
                </Link>
            </Button>
        </div>

        <SidebarFooter class="gap-0 px-3 pb-4 pt-1">
            <div class="mx-2 mb-3 h-px bg-sidebar-border/60" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
