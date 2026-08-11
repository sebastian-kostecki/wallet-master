# OpenShip-style app page chrome — design spec

**Status:** Approved in brainstorming (2026-08-11)  
**Depends on:** OpenShip-style sidebar (`.docs/superpowers/specs/2026-08-11-openship-sidebar-design.md`)  
**Scope:** App shell layout only — **do not change page content** in `resources/js/pages/**`

## Summary

Align the main application chrome (background, content surface, top bar / breadcrumbs, padding) with OpenShip’s dashboard shell: soft page background, floating sidebar card, **flat scrolling main** (no content card), light breadcrumbs row, mobile-only sidebar trigger. Page bodies and domain UI stay untouched.

## Decisions log

| Topic | Decision |
|-------|----------|
| Main surface | **A:** Flat on page background (no inset content card) |
| Top bar | **B:** Remove desktop sticky header bar; light breadcrumbs chrome; `SidebarTrigger` only on mobile |
| Implementation | **1:** Reskin shell files only (`AppSidebarLayout`, `AppSidebarHeader`, `SidebarInset`, maybe `AppContent`) |
| `headerActions` | Stay in layout chrome row (right side) — pages keep passing the slot |
| `pages/**` | **No edits** to page markup/content |

## Target composition

```text
┌─ page bg (soft) ─────────────────────────────────────┐
│ ┌ sidebar card ┐  ┌─ main (flat, scroll) ───────────┐ │
│ │ (existing)   │  │ [☰ mobile] crumbs …   actions   │ │
│ │              │  │ max-w-[1600px] padded container  │ │
│ │              │  │ <page slot unchanged>            │ │
│ └──────────────┘  └─────────────────────────────────┘ │
└──────────────────────────────────────────────────────┘
```

## Visual / interaction details

### Page + inset

- Soft app background via existing `--background` / `bg-background`
- `SidebarInset`: remove desktop card chrome (`rounded-2xl`, border, shadow) added for the sidebar reskin
- Main scrolls (`flex-1 min-h-0 overflow-y-auto`); keep accessible `id="main-content"` for skip link

### Content container

- Inner wrapper: `max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6` (OpenShip `PageContainer` spacing)
- Hosts chrome row + default slot

### Chrome row (`AppSidebarHeader` restyle)

- No `border-b`, no fixed `h-16` desktop bar look
- `flex items-center justify-between gap-3 mb-6`
- Left: `SidebarTrigger` with `md:hidden` + breadcrumbs
- Right: `#headerActions` / `actions` slot
- Breadcrumbs: `text-sm text-muted-foreground`; current page `text-foreground font-medium`
- If no breadcrumbs and no actions on desktop: chrome row may collapse to empty / minimal spacing; mobile still shows trigger when needed

### Out of scope

- Editing any `resources/js/pages/**` templates or domain components
- Settings inner secondary nav redesign
- Auth / guest layouts
- Token palette overhaul beyond what shell classes need
- Dim theme

## Files

| File | Change |
|------|--------|
| `resources/js/layouts/app/AppSidebarLayout.vue` | Wire flat main + padded container around chrome + slot |
| `resources/js/components/AppSidebarHeader.vue` | Light chrome row; mobile-only trigger |
| `resources/js/components/ui/sidebar/SidebarInset.vue` | Strip card chrome; scroll-friendly flex |
| `resources/js/components/AppContent.vue` | Only if needed for scroll/class passthrough |
| `resources/js/pages/**` | **No change** |

## Success criteria

- Desktop: no top border bar; breadcrumbs sit in content padding; main is not a second card
- Mobile: sidebar opens via trigger in chrome row
- Pages using `#headerActions` still show actions in the chrome row without page edits
- Sidebar floating card from prior work remains
