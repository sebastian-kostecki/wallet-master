# Remove Breadcrumbs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove breadcrumb trails from every authenticated Vue view and delete related props, types, and `ui/breadcrumb` primitives.

**Architecture:** Breadcrumbs today flow page → `AppLayout` → `AppSidebarLayout` / `AppHeaderLayout` → `AppSidebarHeader` / `AppHeader`. Delete that prop chain end-to-end; keep `AppSidebarHeader` for mobile `SidebarTrigger` and `#actions`. No backend changes.

**Tech Stack:** Inertia Vue 3, TypeScript, Tailwind v3, shadcn-vue

**Spec:** `.docs/superpowers/specs/2026-08-20-remove-breadcrumbs-design.md`

## Global Constraints

- Frontend only — do not change PHP, routes, or Pest tests (none assert breadcrumbs)
- Keep `AppSidebarHeader` header row: mobile `SidebarTrigger` + `#actions` / `headerActions`
- Keep page `h1` titles and sidebar nav
- After removing breadcrumb-only code, drop unused imports (`BreadcrumbItem`, and `computed` / `route` only if they become unused on that file)
- Do not redesign chrome beyond breadcrumb removal
- Verification is grep + `npm run typecheck` (no new Pest tests)

---

## File map

| Path | Responsibility after change |
|------|-----------------------------|
| `resources/js/pages/**` (20 files listed in Task 1) | No breadcrumb data; `<AppLayout>` without `:breadcrumbs` |
| `resources/js/layouts/AppLayout.vue` | Pass-through shell; no breadcrumbs prop |
| `resources/js/layouts/app/AppSidebarLayout.vue` | Shell + header; no breadcrumbs prop |
| `resources/js/layouts/app/AppHeaderLayout.vue` | Alternate shell; no breadcrumbs prop |
| `resources/js/components/AppSidebarHeader.vue` | Trigger + actions only |
| `resources/js/components/AppHeader.vue` | Top nav without breadcrumb strip |
| `resources/js/types/index.ts` | No `BreadcrumbItem` / `BreadcrumbItemType` |
| `resources/js/components/ui/breadcrumb/*` | **Deleted** |

---

### Task 1: Strip breadcrumbs from all pages

**Files:**
- Modify:
  - `resources/js/pages/Dashboard.vue`
  - `resources/js/pages/accounts/Index.vue`
  - `resources/js/pages/accounts/Create.vue`
  - `resources/js/pages/accounts/Edit.vue`
  - `resources/js/pages/categories/Index.vue`
  - `resources/js/pages/categories/Create.vue`
  - `resources/js/pages/categories/Edit.vue`
  - `resources/js/pages/pockets/Index.vue`
  - `resources/js/pages/pockets/Create.vue`
  - `resources/js/pages/pockets/Edit.vue`
  - `resources/js/pages/transactions/Index.vue`
  - `resources/js/pages/transactions/Create.vue`
  - `resources/js/pages/transactions/Edit.vue`
  - `resources/js/pages/transfers/Create.vue`
  - `resources/js/pages/budget/Monthly.vue`
  - `resources/js/pages/budget/Yearly.vue`
  - `resources/js/pages/settings/Appearance.vue`
  - `resources/js/pages/settings/Password.vue`
  - `resources/js/pages/settings/Profile.vue`

**Interfaces:**
- Consumes: none
- Produces: pages call `<AppLayout>` (and optionally `#headerActions`) with **no** `:breadcrumbs` prop

- [ ] **Step 1: Apply the same removal pattern on every file above**

For each page:

1. Remove `import { type BreadcrumbItem } from '@/types'` (or drop `BreadcrumbItem` from a multi-import).
2. Remove the `breadcrumbs` / `breadcrumbItems` `computed(...)`.
3. Change `<AppLayout :breadcrumbs="...">` to `<AppLayout>` (keep other props/slots).
4. If `computed` or `route` is unused after that, remove those imports too.

Example — `Dashboard.vue` before/after for the breadcrumb bits:

```vue
<!-- BEFORE -->
import { type BreadcrumbItem } from '@/types';
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('dashboard.headTitle'), href: route('dashboard') },
]);
<AppLayout :breadcrumbs="breadcrumbs">

<!-- AFTER -->
<!-- BreadcrumbItem import and breadcrumbs computed gone -->
<!-- Keep other computed() usages (quickActions etc.) -->
<AppLayout>
```

Example — settings page that only used `computed`/`route` for breadcrumbs (`Appearance.vue`):

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <AppLayout>
        <Head :title="t('settings.appearance.title')" />
        <!-- rest unchanged -->
    </AppLayout>
</template>
```

- [ ] **Step 2: Confirm no page still passes breadcrumbs**

Run:

```bash
rg -n "breadcrumbs|BreadcrumbItem" resources/js/pages
```

Expected: no matches.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages
git commit -m "$(cat <<'EOF'
refactor: stop passing breadcrumbs from app pages

EOF
)"
```

---

### Task 2: Remove breadcrumbs from layouts and headers

**Files:**
- Modify: `resources/js/layouts/AppLayout.vue`
- Modify: `resources/js/layouts/app/AppSidebarLayout.vue`
- Modify: `resources/js/layouts/app/AppHeaderLayout.vue`
- Modify: `resources/js/components/AppSidebarHeader.vue`
- Modify: `resources/js/components/AppHeader.vue`

**Interfaces:**
- Consumes: pages no longer pass `:breadcrumbs` (Task 1)
- Produces: layout/header APIs with no breadcrumbs prop; `AppSidebarHeader` still exposes `#actions`

- [ ] **Step 1: Update `AppLayout.vue`**

Replace script + template with:

```vue
<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <a
        href="#main-content"
        class="sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:m-0 focus:flex focus:h-auto focus:w-auto focus:items-center focus:overflow-visible focus:rounded-md focus:border focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-foreground focus:shadow-md focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
    >
        {{ t('a11y.skip_to_content') }}
    </a>
    <AppLayout>
        <template #headerActions>
            <slot name="headerActions" />
        </template>
        <slot />
    </AppLayout>
</template>
```

- [ ] **Step 2: Update `AppSidebarLayout.vue`**

```vue
<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AppToaster from '@/components/notifications/AppToaster.vue';
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <div class="mx-auto w-full max-w-[1600px] px-4 pb-8 pt-6 sm:px-6">
                <AppSidebarHeader>
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

- [ ] **Step 3: Update `AppHeaderLayout.vue`**

```vue
<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
</script>

<template>
    <AppShell class="flex-col">
        <AppHeader />
        <AppContent>
            <slot />
        </AppContent>
    </AppShell>
</template>
```

- [ ] **Step 4: Update `AppSidebarHeader.vue`**

```vue
<script setup lang="ts">
import { SidebarTrigger } from '@/components/ui/sidebar';
</script>

<template>
    <header class="mb-6 flex items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="md:hidden" />
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
```

- [ ] **Step 5: Update `AppHeader.vue`**

1. Remove `BreadcrumbItem` from the `@/types` import.
2. Remove `breadcrumbs` from `Props` / `withDefaults`.
3. Delete the entire bottom block:

```vue
<div v-if="props.breadcrumbs.length > 1" class="flex w-full border-b border-sidebar-border/70">
    <div class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
        <Breadcrumbs :breadcrumbs="breadcrumbs" />
    </div>
</div>
```

Leave the rest of the header markup unchanged. Do not add a `Breadcrumbs` import (it was already missing / broken).

- [ ] **Step 6: Grep layouts/components**

Run:

```bash
rg -n "breadcrumb|Breadcrumb" resources/js/layouts resources/js/components/AppSidebarHeader.vue resources/js/components/AppHeader.vue
```

Expected: no matches.

- [ ] **Step 7: Commit**

```bash
git add resources/js/layouts resources/js/components/AppSidebarHeader.vue resources/js/components/AppHeader.vue
git commit -m "$(cat <<'EOF'
refactor: remove breadcrumbs from app layouts and headers

EOF
)"
```

---

### Task 3: Delete types and `ui/breadcrumb` primitives

**Files:**
- Modify: `resources/js/types/index.ts`
- Delete:
  - `resources/js/components/ui/breadcrumb/Breadcrumb.vue`
  - `resources/js/components/ui/breadcrumb/BreadcrumbEllipsis.vue`
  - `resources/js/components/ui/breadcrumb/BreadcrumbItem.vue`
  - `resources/js/components/ui/breadcrumb/BreadcrumbLink.vue`
  - `resources/js/components/ui/breadcrumb/BreadcrumbList.vue`
  - `resources/js/components/ui/breadcrumb/BreadcrumbPage.vue`
  - `resources/js/components/ui/breadcrumb/BreadcrumbSeparator.vue`
  - `resources/js/components/ui/breadcrumb/index.ts`

**Interfaces:**
- Consumes: no remaining imports of breadcrumb types or `@/components/ui/breadcrumb` (Tasks 1–2)
- Produces: types file without breadcrumb exports; directory gone

- [ ] **Step 1: Edit `resources/js/types/index.ts`**

Remove these exports entirely:

```ts
export interface BreadcrumbItem {
    title: string;
    href: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
```

Leave `Auth`, `NavItem`, `SharedData`, `User` unchanged.

- [ ] **Step 2: Delete the breadcrumb UI directory**

```bash
rm -rf resources/js/components/ui/breadcrumb
```

- [ ] **Step 3: Full-tree verification**

Run:

```bash
rg -n -i "breadcrumb" resources/js
```

Expected: no matches.

Then:

```bash
npm run typecheck
```

Expected: exit 0 (no TS errors related to breadcrumbs or unused props).

Optional:

```bash
npm run lint:check
```

Expected: exit 0 for touched files (or fix any leftover unused imports).

- [ ] **Step 4: Commit**

```bash
git add -u resources/js/types/index.ts resources/js/components/ui/breadcrumb
git commit -m "$(cat <<'EOF'
refactor: delete breadcrumb types and ui primitives

EOF
)"
```

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| Strip page definitions / `:breadcrumbs` | Task 1 |
| Drop prop from layouts | Task 2 |
| Keep SidebarTrigger + actions | Task 2 Step 4 |
| Clean AppHeader breadcrumb strip | Task 2 Step 5 |
| Remove types | Task 3 Step 1 |
| Delete `ui/breadcrumb` | Task 3 Step 2 |
| Grep + typecheck verification | Task 3 Step 3 |
| Out of scope (no header redesign / PHP) | Global Constraints |

No placeholders. Interfaces consistent: after Task 1, layouts must not require `:breadcrumbs`.
