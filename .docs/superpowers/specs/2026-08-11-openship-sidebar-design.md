# OpenShip-style sidebar — design spec

**Status:** Approved in brainstorming (2026-08-11)  
**Next step:** Implementation (branch `ui/sideber`)  
**Reference UI:** [oblien/openship](https://github.com/oblien/openship) `apps/dashboard/src/components/sidebar.tsx`  
**App files:** `resources/js/components/AppSidebar.vue`, `NavMain.vue`, `NavUser.vue`, shadcn `ui/sidebar/*`

## Summary

Reskin the existing shadcn Sidebar (`variant="inset"`) to match OpenShip’s floating-card chrome: header with theme + collapse, flat nav, scroll fade, ink CTA (“Add transaction”), and a polished user footer. Keep current Lucide icons (optionally restyle size/stroke). Do not rewrite the sidebar shell away from shadcn.

## Decisions log

| Topic | Decision |
|-------|----------|
| Scope | Full OpenShip layout on shadcn inset Sidebar |
| CTA | Ink `Button` linking to `transactions.create` (not a gradient) |
| Nav grouping | Flat list — no section labels |
| Icons | Keep current set; `~18px`, stroke ~1.7 |
| Theme toggle | Cycle `light → dark → system` via `useAppearance` (no `dim`) |
| Collapse | In-sidebar header button + keep `SidebarTrigger` in content for mobile |
| Implementation | Reskin existing components (not a custom sidebar rewrite) |

## Target composition (top → bottom)

1. **Header** — logo + brand · theme cycle · collapse  
2. **Nav** — flat items, soft active/hover  
3. **Scroll fade** — gradient into card background  
4. **CTA** — “Add transaction” ink `Button` (not a gradient)  
5. **Footer** — user menu (settings + logout)

## Out of scope

Org switcher, nav counts, dim theme, replacing mobile Sheet with a custom drawer.

## Files

| File | Change |
|------|--------|
| `AppSidebar.vue` | Header controls, CTA, assembly |
| `NavMain.vue` | Flat list, active match, icon style |
| `NavUser.vue` | Softer footer trigger |
| `AppLogo.vue` | Compact brand (hide shortPromise in sidebar header) |
| `SidebarThemeToggle.vue` (new) | Appearance cycle control |
| `ui/sidebar/Sidebar.vue` | Card chrome for inset |
| Locales `en.json` / `pl.json` | CTA + aria labels |
