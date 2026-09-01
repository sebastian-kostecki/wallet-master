# Categories index OpenShip — design spec

**Date:** 2026-08-27  
**Status:** Approved in brainstorming  
**Scope:** OpenShip restyle of the full `/kategorie` page content: single list card (income + expense sections), compact rows, section labels, empty text, `CategoryBadge` tile radius, minor icon consistency on the header Add button.  
**Depends on:** `.docs/superpowers/specs/2026-08-26-categories-modals-design.md` (modals already shipped). This spec **supersedes** that doc’s “list restyle: out of scope”.  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md` (surface ladder, lists, section labels); `/konta` list card + `AccountRow` as the visual reference.

## Goal

Make the categories index read as the same quiet operator UI as the accounts and transactions lists: grey page floor, one lifted white card for all rows, compact draggable rows, OpenShip section labels, color only on category badges (user-chosen icon/color). Page chrome (`PageHeader`, breadcrumbs, settings tab, Add button) stays structurally the same; modals are unchanged.

## Decisions log

| Topic | Decision |
|-------|----------|
| Scope | **C:** Full page content — list card, rows, empty, `CategoryBadge`, minor header icon polish; modals unchanged |
| Layout | **B:** One card, two sections inside (Przychody / Wydatki) |
| Empty | **B:** Always render the card; empty section shows short muted text inside (`categories.index.empty`) |
| Density | **A:** Compact like `AccountRow` (`px-3 py-2.5`) |
| Section headers | **A:** OpenShip label — `text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60` |
| Implementation | **1:** New `CategoryRow`; refactor `Index.vue` to dedupe draggable blocks |

## Current state

- `resources/js/pages/categories/Index.vue` owns two nearly identical `VueDraggable` blocks (~100 lines duplicated) for expense and income.
- Two separate section wrappers: `rounded-xl border border-sidebar-border/70`, section titles `text-lg font-semibold` with `border-b`.
- Rows: `px-4 py-3`, `list-row-hover`, dividers via `divide-y divide-sidebar-border/70`.
- Empty: one `text-sm text-muted-foreground` paragraph per empty section inside the bordered box.
- `CategoryBadge` icon tile uses `rounded-md`.
- Add/Edit/Delete are index modals (spec `2026-08-26-categories-modals`). `PageHeader` and shared `ui/dialog` chrome are already OpenShip.

## Target behavior

No PHP, routes, or Inertia props change. Reorder API, drag-and-drop, system-category delete guard, and modal open/close behavior are unchanged.

### Surface composition

```
[ PageHeader — unchanged: breadcrumbs, title, description, Add button ]

┌─ card: overflow-hidden rounded-2xl border-transparent bg-card ────┐
│  PRZYCHODY   ← OpenShip section label                              │
│  CategoryRow …                                                     │
│  ─────────── border-border/50 between rows ───────────            │
│  ─────────── border-t border-border/50 ──────────────             │
│  WYDATKI                                                           │
│  CategoryRow …                                                     │
│  or: „Brak kategorii.” (px-3 py-4 text-sm muted) in empty section │
└────────────────────────────────────────────────────────────────────┘
```

**Card** (`overflow-hidden rounded-2xl border border-transparent bg-card`, no resting shadow): both sections always visible. Income first, expense second. Section separator: `border-t border-border/50` on the expense section block (not a second outer card).

**Floor:** grey `bg-background` from the app shell only — no extra bordered wrappers around the card.

### Section headers

| Element | Classes |
|---------|---------|
| Label | `px-3 pt-4 pb-2 text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60` |
| Copy | Existing `budget.sections.income` / `budget.sections.expense` |

### Empty sections

When a section has zero categories, render inside the card (not on the floor):

```html
<p class="px-3 py-4 text-sm text-muted-foreground">{{ t('categories.index.empty') }}</p>
```

No new i18n keys. No heading + CTA empty state on the floor (both sections may be empty simultaneously; card still renders).

When the section has rows, use `VueDraggable` as today.

### `CategoryRow`

New `resources/js/components/categories/CategoryRow.vue`.

| Aspect | Decision |
|--------|----------|
| Root | `<li>` (parent is `<ul>` from `VueDraggable`) |
| Padding / hover | `list-row-hover px-3 py-2.5 hover:bg-foreground/[0.03]` |
| Divider | `border-b border-border/50 last:border-b-0` on the row; `<ul>` has **no** `divide-y` |
| Layout | `flex items-center gap-2 sm:gap-3` |
| Drag handle | `drag-handle`, `rounded-lg p-1`, `hover:bg-foreground/[0.06]`; `GripVertical` `size-4` `stroke-width="1.7"` |
| Main click target | Badge + name button → emit `edit` |
| System hint | `(system)` in `text-xs text-muted-foreground` when `is_system` |
| Actions | Ghost icon edit/delete; delete hidden for system categories; wrapper has `no-drag` |
| Props | `category`, `deleteDisabled` |
| Emits | `edit`, `delete` (category object) |

### `CategoryBadge`

| Aspect | Was | Target |
|--------|-----|--------|
| Icon tile radius | `rounded-md` | `rounded-lg` |
| Size `md` | `h-8 w-8` | unchanged |

User color tint (`backgroundColor: ${color}26`) stays — data color, not chrome.

### `Index.vue` refactor

- Replace two section `<section class="rounded-xl border…">` blocks with one card wrapper.
- Loop `v-for="sectionKey in ['income', 'expense'] as const"` for section label + list/empty.
- Expense section wrapper gets `border-t border-border/50`.
- Single draggable pattern per section; each item is `<CategoryRow>`.
- `VueDraggable` options unchanged: `handle=".drag-handle"`, `filter=".no-drag"`, `:animation="150"`, `ghost-class="opacity-50"`, `chosen-class="bg-muted/30"`, `@end` → `onReorderEnd(type, $event)`.
- Header Add button: optional `Plus` `stroke-width="1.7"` for icon consistency; no layout change.

## Data flow

Unchanged:

```
GET categories.index → CategoryController::index
  → ListCategories + CategoryFormOptions
  → Inertia categories/Index { categories, icons, colors }
```

Frontend: `syncLists()` splits props into `expenseList` / `incomeList`; reorder patches `categories.reorder`; modals use existing handlers.

## Files

| File | Change |
|------|--------|
| `resources/js/pages/categories/Index.vue` | One card, section loop, `CategoryRow` |
| `resources/js/components/categories/CategoryRow.vue` | **New** |
| `resources/js/components/categories/CategoryBadge.vue` | `rounded-lg` on icon tile |

Do not add PHP Actions/Resources. Do not change locales unless copy gaps appear during implementation.

## Testing

| Check | Method |
|-------|--------|
| Index still renders with categories | Existing Pest `CategoryCrudTest` (smoke) |
| Reorder still works | `CategoryReorderTest` |
| Visual: card, sections, drag, empty, dark, mobile | Manual (browser) |

No new Pest/Vitest tests for CSS classes.

## Out of scope

- Category Add/Edit/Delete modal interiors (already OpenShip dialog chrome)
- `/kieszenie` list (same legacy styling — separate migration)
- Budget monthly/yearly `BudgetCategorySection` styling
- Floor empty state with heading + CTA
- Tabs to switch income vs expense
- PHP / new Inertia props
- Shared draggable-list primitive for categories + pockets

## Implementation notes (for plan)

1. Add `CategoryRow` first; wire one section, then loop both sections.
2. Move card classes to a single outer wrapper; remove all `border-sidebar-border/70` and `divide-sidebar-border` usage on this page.
3. Update `CategoryBadge` radius in the same PR so rows look consistent immediately.
4. After Vue: Prettier. No PHP expected — skip Pint unless touched.
5. Verify in browser: both sections, drag reorder, edit/delete, system category delete hidden, empty section text, dark mode, mobile row layout.

## Success criteria

- All category rows sit on one lifted `rounded-2xl` card with income and expense sections inside.
- Section labels use OpenShip uppercase muted typography.
- Row density matches `/konta` list (`px-3 py-2.5`).
- Drag-and-drop, edit, delete, and system-category rules still work.
- `CategoryBadge` icon tile uses `rounded-lg`.
- Page header and modals show no regression.
