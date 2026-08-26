# Remove breadcrumbs from all views

**Date:** 2026-08-20  
**Status:** Superseded by `.docs/superpowers/specs/2026-08-25-page-header-tabs-design.md`  
**Scope:** Frontend (Inertia Vue) only

## Goal

Remove the breadcrumb trail from every authenticated app view and delete the related prop plumbing, types, and UI primitives so no dead breadcrumb API remains.

## Context

Breadcrumbs are defined on ~20 page components and passed through `AppLayout` → `AppSidebarLayout` (or `AppHeaderLayout`) into `AppSidebarHeader` / `AppHeader`. Active shell is sidebar layout; page titles (`h1`) and the sidebar already provide orientation. No Pest/Feature tests assert breadcrumbs.

## Decision

Full removal (not hide-only): stop rendering, remove page definitions and layout props, delete `BreadcrumbItem` / `BreadcrumbItemType`, and delete `resources/js/components/ui/breadcrumb/*`.

## What stays

- `AppSidebarHeader`: mobile `SidebarTrigger` and `#actions` / `headerActions` slot
- Existing page `h1` titles and sidebar navigation

## Changes

1. **Pages** — Remove `BreadcrumbItem` imports, breadcrumb `computed`s, and `:breadcrumbs` on `<AppLayout>` for: Dashboard; accounts/categories/pockets/transactions Create|Edit|Index; transfers Create; budget Monthly|Yearly; settings Appearance|Password|Profile.
2. **Layouts** — Drop `breadcrumbs` prop from `AppLayout.vue`, `AppSidebarLayout.vue`, `AppHeaderLayout.vue`.
3. **Headers** — `AppSidebarHeader.vue`: remove Breadcrumb UI; keep trigger + actions. `AppHeader.vue`: remove breadcrumb strip and related prop (unused/broken `Breadcrumbs` reference).
4. **Types** — Remove `BreadcrumbItem` and `BreadcrumbItemType` from `resources/js/types/index.ts`.
5. **Primitives** — Delete `resources/js/components/ui/breadcrumb/` (all files).

## Out of scope

- Redesigning page headers beyond breadcrumb removal
- Adding alternate navigation chrome
- Backend / i18n key cleanup unrelated to breadcrumbs (title keys used by `h1` stay)

## Verification

- No remaining `breadcrumb` / `Breadcrumb` references under `resources/js` except unrelated words if any
- Typecheck / ESLint clean for touched files
- Manual smoke: sidebar trigger (mobile) and header actions still work; no trail above content

## Success criteria

Every app view shows no breadcrumb trail; layouts and pages no longer accept or define breadcrumbs; `ui/breadcrumb` and breadcrumb types are gone.
