# OpenShip page chrome Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the authenticated app shell’s main area match OpenShip (flat scroll surface, light breadcrumbs chrome, mobile-only sidebar trigger) without editing page content.

**Architecture:** Reskin only shell layout components (`SidebarInset`, `AppSidebarLayout`, `AppSidebarHeader`). Keep `headerActions` and `breadcrumbs` props/slots so existing pages need zero changes.

**Tech Stack:** Vue 3, Inertia, Tailwind v3, existing shadcn sidebar primitives.

## Global Constraints

- Do **not** modify `resources/js/pages/**`
- Do **not** change sidebar nav items / CTA from the prior OpenShip sidebar work except where inset spacing must align
- Preserve `id="main-content"` for skip-to-content
- Preserve `#headerActions` slot plumbing through `AppLayout` → `AppSidebarLayout` → header
- Spec: `.docs/superpowers/specs/2026-08-11-openship-page-chrome-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `resources/js/components/ui/sidebar/SidebarInset.vue` | Flat main surface + scroll |
| `resources/js/layouts/app/AppSidebarLayout.vue` | Padded max-width container wrapping chrome + page slot |
| `resources/js/components/AppSidebarHeader.vue` | Light breadcrumbs row; `SidebarTrigger` `md:hidden` |
| `resources/js/components/AppContent.vue` | Passthrough only if inset classes need help |

---

### Task 1: Flatten `SidebarInset` (remove content card)

**Files:**
- Modify: `resources/js/components/ui/sidebar/SidebarInset.vue`

**Interfaces:**
- Consumes: shadcn peer-data inset variant attrs (unchanged)
- Produces: flat `main#main-content` suitable for OpenShip-style page bg

- [ ] **Step 1: Update inset classes**

Remove desktop card treatment (`rounded-2xl`, `border`, `shadow-sm`). Keep background and min-height behavior that leaves room for the floating sidebar. Prefer scrollable column:

```vue
<main
  id="main-content"
  :class="
    cn(
      'relative flex min-h-svh flex-1 flex-col bg-background',
      'peer-data-[variant=inset]:min-h-[calc(100svh-theme(spacing.4))] md:peer-data-[variant=inset]:m-2 md:peer-data-[state=collapsed]:peer-data-[variant=inset]:ml-2 md:peer-data-[variant=inset]:ml-0',
      'md:peer-data-[variant=inset]:min-h-0 md:peer-data-[variant=inset]:overflow-y-auto',
      props.class,
    )
  "
>
  <slot />
</main>
```

Tune margins so the floating sidebar still has breathing room without looking like a second card. If `m-2` without radius looks odd, keep margin only on the sidebar side / rely on sidebar’s own `p-2` gap from the inset sidebar primitive.

- [ ] **Step 2: Visual check**

Load dashboard desktop: main should not read as a bordered white card; page bg + sidebar card only.

---

### Task 2: Restyle `AppSidebarHeader` into light chrome

**Files:**
- Modify: `resources/js/components/AppSidebarHeader.vue`

**Interfaces:**
- Consumes: `breadcrumbs?: BreadcrumbItemType[]`, slot `actions`
- Produces: chrome row used inside padded container

- [ ] **Step 1: Replace header chrome**

Target structure:

```vue
<header class="mb-6 flex items-center justify-between gap-3">
  <div class="flex min-w-0 items-center gap-2">
    <SidebarTrigger class="md:hidden" />
    <!-- existing Breadcrumb list; text-sm muted / current font-medium -->
  </div>
  <div class="flex shrink-0 items-center gap-2">
    <slot name="actions" />
  </div>
</header>
```

Remove: `h-16`, `border-b`, `border-sidebar-border/70`, dense `px-6` (padding moves to parent container).

- [ ] **Step 2: Confirm pages with `#headerActions`**

Spot-check transactions/accounts index: actions still appear on the right without page edits.

---

### Task 3: Wire padded container in `AppSidebarLayout`

**Files:**
- Modify: `resources/js/layouts/app/AppSidebarLayout.vue`
- Modify only if needed: `resources/js/components/AppContent.vue`

**Interfaces:**
- Consumes: `breadcrumbs`, slots `headerActions` + default
- Produces: OpenShip-like content column

- [ ] **Step 1: Wrap chrome + page slot**

Inside `AppContent variant="sidebar"`:

```vue
<div class="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">
  <AppSidebarHeader :breadcrumbs="breadcrumbs">
    <template #actions>
      <slot name="headerActions" />
    </template>
  </AppSidebarHeader>
  <slot />
</div>
```

- [ ] **Step 2: Ensure `AppLayout` skip-link still targets `#main-content`**

No change expected if `SidebarInset` keeps the id.

---

### Task 4: Verify + lint

**Files:** none new

- [ ] **Step 1: Manual QA**

  - Desktop: no top border bar; flat main; breadcrumbs light; sidebar card intact
  - Mobile width: hamburger opens sheet
  - Page with `headerActions` (e.g. transactions index)
  - Page with only breadcrumbs (e.g. budget)
  - Collapsed sidebar spacing still OK

- [ ] **Step 2: Lint/format touched Vue files**

```bash
npx eslint resources/js/layouts/app/AppSidebarLayout.vue resources/js/components/AppSidebarHeader.vue resources/js/components/ui/sidebar/SidebarInset.vue
npx prettier --write resources/js/layouts/app/AppSidebarLayout.vue resources/js/components/AppSidebarHeader.vue
```

- [ ] **Step 3: Done**

No PHP tests required (Vue chrome only) unless a Pest browser smoke already covers layout.

---

## Spec self-review (done)

- No TBDs; scope explicitly excludes `pages/**`
- `headerActions` relocation is in layout chrome, not pages
- Consistent with approved sidebar floating card
