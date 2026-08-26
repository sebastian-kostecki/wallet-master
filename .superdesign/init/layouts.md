# Shared layouts

Default authenticated layout is **sidebar**, not header.

## Breadcrumb + headerActions flow

```
Page  --breadcrumbs prop-->  AppLayout
  --breadcrumbs-->  AppSidebarLayout
    --breadcrumbs-->  AppSidebarHeader
      renders ui/breadcrumb primitives

Page  --#headerActions slot-->  AppLayout
  --#headerActions-->  AppSidebarLayout
    --#actions-->  AppSidebarHeader (right of breadcrumbs)
```

There is **no** `Breadcrumbs.vue` wrapper. Crumbs are composed in `AppSidebarHeader.vue`. `AppHeader.vue` still references `<Breadcrumbs>` (starter-kit leftover; header layout is unused by AppLayout).

## AppLayout

- File: `resources/js/layouts/AppLayout.vue`
- Renders: Skip-to-content link, then delegates to `AppSidebarLayout`. Passes `breadcrumbs` prop and forwards `#headerActions` slot.

```vue
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
```

## AppSidebarLayout

- File: `resources/js/layouts/app/AppSidebarLayout.vue`
- Renders: Default authenticated chrome: `AppShell` (sidebar) + `AppSidebar` + `AppContent` + `AppSidebarHeader` (breadcrumbs + `#actions` from `#headerActions`) + page slot + toaster.

```vue
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
```

## AppHeaderLayout

- File: `resources/js/layouts/app/AppHeaderLayout.vue`
- Renders: Alternate header-nav chrome (`AppShell` flex-col + `AppHeader` + `AppContent`). Not used by `AppLayout` (sidebar is default). Starter-kit leftover.

```vue
<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell class="flex-col">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent>
            <slot />
        </AppContent>
    </AppShell>
</template>
```

## settings/Layout

- File: `resources/js/layouts/settings/Layout.vue`
- Renders: Nested settings chrome: Heading + side nav (profile/password/appearance) + slot. Used inside AppLayout on settings pages.

```vue
<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

const { t } = useI18n();
const page = usePage();

const sidebarNavItems = computed<NavItem[]>(() => [
    {
        title: t('settings.nav.profile'),
        href: route('profile.edit', undefined, false),
    },
    {
        title: t('settings.nav.password'),
        href: route('password.edit', undefined, false),
    },
    {
        title: t('settings.nav.appearance'),
        href: route('appearance', undefined, false),
    },
]);

const currentPath = computed(() => page.url.split('?')[0]);
</script>

<template>
    <div>
        <Heading :title="t('settings.title')" :description="t('settings.description')" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-x-0 space-y-1">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start rounded-xl', { 'bg-foreground/[0.07] text-foreground': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
```

## AppSidebar

- File: `resources/js/components/AppSidebar.vue`
- Renders: Floating inset sidebar card: logo, theme toggle, collapse, NavMain items, Add transaction CTA, NavUser footer.

```vue
<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import SidebarThemeToggle from '@/components/SidebarThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, useSidebar } from '@/components/ui/sidebar';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRight, LayoutGrid, PanelLeftClose, PanelLeftOpen, PieChart, Plus, Tags, Target, Wallet } from 'lucide-vue-next';
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
        title: t('categories.index.title'),
        href: route('categories.index'),
        icon: Tags,
    },
    {
        title: t('nav.pockets'),
        href: route('pockets.index'),
        icon: Target,
    },
]);

const createTransactionHref = computed(() => route('transactions.create'));
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
            <Button as-child :size="isCollapsed ? 'icon' : 'default'" class="w-full group-data-[collapsible=icon]:size-8">
                <Link :href="createTransactionHref" :title="t('nav.addTransaction')">
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
```

## AppSidebarHeader

- File: `resources/js/components/AppSidebarHeader.vue`
- Renders: Page header row: mobile SidebarTrigger + Breadcrumb primitives from `breadcrumbs` array; `#actions` slot on the right (wired from AppLayout `#headerActions`).

```vue
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
```

## AppHeader

- File: `resources/js/components/AppHeader.vue`
- Renders: Top navigation bar used only by AppHeaderLayout. References undeclared `<Breadcrumbs>` (no wrapper file in repo).

```vue
<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem, NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Menu, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage<SharedData>();
const auth = computed(() => page.props.auth);

const isCurrentRoute = (url: string) => {
    return page.url === url;
};

const activeItemStyles = computed(() => (url: string) => (isCurrentRoute(url) ? 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100' : ''));

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
        icon: LayoutGrid,
    },
];

const rightNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];
</script>

<template>
    <div>
        <div class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="mr-2 h-9 w-9">
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6">
                            <SheetTitle class="sr-only">Navigation Menu</SheetTitle>
                            <SheetHeader class="flex justify-start text-left">
                                <AppLogoIcon class="size-6 fill-current text-black dark:text-white" />
                            </SheetHeader>
                            <div class="flex h-full flex-1 flex-col justify-between space-y-4 py-6">
                                <nav class="-mx-3 space-y-1">
                                    <Link
                                        v-for="item in mainNavItems"
                                        :key="item.title"
                                        :href="item.href"
                                        class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                        :class="activeItemStyles(item.href)"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="h-5 w-5" />
                                        {{ item.title }}
                                    </Link>
                                </nav>
                                <div class="flex flex-col space-y-4">
                                    <a
                                        v-for="item in rightNavItems"
                                        :key="item.title"
                                        :href="item.href"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center space-x-2 text-sm font-medium"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="h-5 w-5" />
                                        <span>{{ item.title }}</span>
                                    </a>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="route('dashboard')" class="flex items-center gap-x-2">
                    <AppLogo class="hidden h-6 xl:block" />
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1">
                    <NavigationMenu class="ml-10 flex h-full items-stretch">
                        <NavigationMenuList class="flex h-full items-stretch space-x-2">
                            <NavigationMenuItem v-for="(item, index) in mainNavItems" :key="index" class="relative flex h-full items-center">
                                <Link :href="item.href">
                                    <NavigationMenuLink
                                        :class="[navigationMenuTriggerStyle(), activeItemStyles(item.href), 'h-9 cursor-pointer px-3']"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="mr-2 h-4 w-4" />
                                        {{ item.title }}
                                    </NavigationMenuLink>
                                </Link>
                                <div class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"></div>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <div class="relative flex items-center space-x-1">
                        <Button variant="ghost" size="icon" class="group h-9 w-9 cursor-pointer">
                            <Search class="size-5 opacity-80 group-hover:opacity-100" />
                        </Button>

                        <div class="hidden space-x-1 lg:flex">
                            <template v-for="item in rightNavItems" :key="item.title">
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Button variant="ghost" size="icon" as-child class="group h-9 w-9 cursor-pointer">
                                                <a :href="item.href" target="_blank" rel="noopener noreferrer">
                                                    <span class="sr-only">{{ item.title }}</span>
                                                    <component :is="item.icon" class="size-5 opacity-80 group-hover:opacity-100" />
                                                </a>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ item.title }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </template>
                        </div>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar class="size-8 overflow-hidden rounded-full">
                                    <AvatarImage :src="auth.user.avatar ?? ''" :alt="auth.user.name" />
                                    <AvatarFallback class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div v-if="props.breadcrumbs.length > 1" class="flex w-full border-b border-sidebar-border/70">
            <div class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>
```

## AppShell

- File: `resources/js/components/AppShell.vue`
- Renders: If variant=header: full-height column. Else SidebarProvider wrapping slot, persisting open state in localStorage.

```vue
<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { onMounted, ref } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const isOpen = ref(true);

onMounted(() => {
    isOpen.value = localStorage.getItem('sidebar') !== 'false';
});

const handleSidebarChange = (open: boolean) => {
    isOpen.value = open;
    localStorage.setItem('sidebar', String(open));
};
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen" :open="isOpen" @update:open="handleSidebarChange">
        <slot />
    </SidebarProvider>
</template>
```

## AppContent

- File: `resources/js/components/AppContent.vue`
- Renders: If variant=sidebar: SidebarInset. Else `<main>` max-w-7xl.

```vue
<script setup lang="ts">
import { SidebarInset } from '@/components/ui/sidebar';
import { computed } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
    class?: string;
}

const props = defineProps<Props>();
const className = computed(() => props.class);
</script>

<template>
    <SidebarInset v-if="props.variant === 'sidebar'" :class="className">
        <slot />
    </SidebarInset>
    <main v-else class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl" :class="className">
        <slot />
    </main>
</template>
```

## AppLogo

- File: `resources/js/components/AppLogo.vue`
- Renders: Ink rounded-xl mark + brand name (i18n). Compact mode hides tagline. Uses AppLogoIcon.

```vue
<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useI18n } from 'vue-i18n';

interface Props {
    class?: string;
    compact?: boolean;
}

withDefaults(defineProps<Props>(), {
    compact: false,
});

const { t } = useI18n();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-xl bg-primary text-primary-foreground">
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <div class="ml-1 grid min-w-0 flex-1 text-left text-sm group-data-[collapsible=icon]:hidden">
        <span class="truncate font-semibold leading-none tracking-tight">{{ t('brand.name') }}</span>
        <span v-if="!compact" class="truncate text-xs leading-none text-muted-foreground">
            {{ t('brand.shortPromise') }}
        </span>
    </div>
</template>
```

## Heading

- File: `resources/js/components/Heading.vue`
- Renders: Page title + optional description + Separator. Used by settings layout.

```vue
<script setup lang="ts">
import { Separator } from '@/components/ui/separator';

interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="mb-8 space-y-0.5">
        <h2 class="text-xl font-semibold tracking-tight">{{ title }}</h2>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </div>
    <Separator class="my-6" />
</template>
```

