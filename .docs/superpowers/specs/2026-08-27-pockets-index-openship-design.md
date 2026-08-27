# Pockets index OpenShip — design spec

**Date:** 2026-08-27  
**Status:** Approved in brainstorming  
**Scope:** OpenShip restyle of the full `/kieszenie` page content: list card, compact draggable rows, empty text, `PocketBadge` tile radius, `PocketProgressBar` tokens, status badges (OpenShip recipe), minor icon consistency on the header Add button.  
**Depends on:** `.docs/superpowers/specs/2026-08-26-pockets-modals-design.md` (modals already shipped). This spec **supersedes** that doc’s “list restyle: out of scope”.  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md` (surface ladder, lists, status color); `/konta` list card + `AccountRow`; `/kategorie` `CategoryRow` as the draggable-list reference.

## Goal

Make the pockets index read as the same quiet operator UI as the accounts and categories lists: grey page floor, one lifted white card for all rows, compact draggable rows, color only on pocket badges (user-chosen icon/color), progress, and status pills. Page chrome (`PageHeader`, breadcrumbs, Add button) stays structurally the same; modals are unchanged.

## Decisions log

| Topic | Decision |
|-------|----------|
| Scope | **B:** Full page content — list card, rows, empty, `PocketBadge`, `PocketProgressBar`, status badges, minor header icon polish; modals unchanged |
| Inner header | **A:** Remove `listTitle` — card with rows only (no redundant section title inside the card) |
| Density | **A:** Compact like `AccountRow` / `CategoryRow` (`px-3 py-2.5`) |
| Implementation | **A:** New `PocketRow`; refactor `Index.vue` |

## Current state

- `resources/js/pages/pockets/Index.vue` owns inline row markup (~80 lines) inside a legacy section wrapper.
- Section wrapper: `rounded-xl border border-sidebar-border/70`, inner title `listTitle` as `text-lg font-semibold` with `border-b`.
- Rows: `px-4 py-3`, `list-row-hover`, dividers via `divide-y divide-sidebar-border/70`.
- Row content: drag handle, `PocketBadge`, `PocketProgressBar`, balance, completed/overdue pills, edit/delete actions.
- Empty: one `text-sm text-muted-foreground` paragraph inside the bordered box.
- `PocketBadge` icon tile uses `rounded-md`.
- `PocketProgressBar` uses `h-2` track and solid `bg-emerald-500` fill.
- Status pills use legacy tints (`bg-emerald-100`, `bg-amber-100`).
- Drag handle reuses `categories.index.dragHandle` for `aria-label`.
- Add/Edit are index modals (`PocketFormDialog`, spec `2026-08-26-pockets-modals`). Delete stays `window.confirm` on the row.

## Target behavior

No PHP, routes, or Inertia props change. Reorder API, drag-and-drop, modal open/close, and delete confirm behavior are unchanged.

### Surface composition

```
[ PageHeader — unchanged: breadcrumbs, title, description, Add button ]

  empty (0 pockets):                     has rows:
  ┌─ card rounded-2xl bg-card ─┐         ┌─ card rounded-2xl bg-card ─┐
  │  „Brak kieszeni.” (muted)   │         │  PocketRow …               │
  └─────────────────────────────┘         │  ─ border-border/50 ─      │
                                          │  PocketRow …               │
                                          └────────────────────────────┘
```

**Card** (`overflow-hidden rounded-2xl border border-transparent bg-card`, no resting shadow): draggable list **or** empty text. No inner `listTitle`.

**Floor:** grey `bg-background` from the app shell only — no extra bordered wrappers around the card.

### Empty state

When `list.length === 0`, render inside the card (not on the floor):

```html
<p class="px-3 py-4 text-sm text-muted-foreground">{{ t('pockets.index.empty') }}</p>
```

No new i18n keys. No heading + CTA empty state on the floor (accounts-style empty rejected in brainstorming).

When the list has rows, use `VueDraggable` as today.

### `PocketRow`

New `resources/js/components/pockets/PocketRow.vue`.

| Aspect | Decision |
|--------|----------|
| Root | `<li>` (parent is `<ul>` from `VueDraggable`) |
| Padding / hover | `list-row-hover px-3 py-2.5 hover:bg-foreground/[0.03]` |
| Divider | `border-b border-border/50 last:border-b-0` on the row; `<ul>` has **no** `divide-y` |
| Layout | `flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between` |
| Drag handle | `drag-handle`, `rounded-lg p-1`, `hover:bg-foreground/[0.06]`; `GripVertical` `size-4` `stroke-width="1.7"` |
| Left column | handle + `PocketBadge` + `PocketProgressBar` in `min-w-0 flex-1` |
| Right column | balance, status pills, edit/delete — wrapper has `no-drag` |
| Balance | `text-sm font-medium tabular-nums` — always visible on the right |
| Actions | Ghost icon edit/delete; icons `stroke-width="1.7"` |
| Props | `pocket` (same shape as Index row type) |
| Emits | `edit`, `delete` (pocket object) |

Delete confirmation stays in `Index.vue` (`window.confirm` + `router.delete`).

### `PocketBadge`

| Aspect | Was | Target |
|--------|-----|--------|
| Icon tile radius | `rounded-md` | `rounded-lg` |
| Size `md` | `h-8 w-8` | unchanged |

User color tint (`backgroundColor: ${color}26`) stays — data color, not chrome.

### `PocketProgressBar`

| Element | Was | Target |
|---------|-----|--------|
| Track height | `h-2` | `h-1.5` |
| Fill | `bg-emerald-500` | `bg-emerald-500/90` |
| Caption | `balance / target` | unchanged copy; add `tabular-nums` on caption |
| No target | component hidden | unchanged |

Progress toward a savings goal uses emerald (positive / savings status), not chrome mint.

### Status badges

Replace legacy solid tints with OpenShip status recipe:

| Status | Classes |
|--------|---------|
| Completed | `inline-flex items-center rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400` |
| Overdue | `inline-flex items-center rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400` |

Copy: existing `pockets.status.completed` / `pockets.status.overdue`.

### `Index.vue` refactor

- Replace legacy `<section>` with one card wrapper.
- Remove `listTitle` heading entirely.
- Empty **or** `VueDraggable` with `<PocketRow>` items.
- `VueDraggable` options unchanged: `handle=".drag-handle"`, `filter=".no-drag"`, `:animation="150"`, `ghost-class="opacity-50"`, `chosen-class="bg-muted/30"`, `@end` → `onReorderEnd`.
- Header Add button: optional `Plus` `stroke-width="1.7"` for icon consistency; no layout change.
- Drag handle `aria-label`: use new `pockets.index.dragHandle` (stop reusing `categories.index.dragHandle`).

### i18n

Add to `pl.json` and `en.json`:

```text
pockets.index.dragHandle
```

Suggested copy:

- PL: „Przeciągnij, aby zmienić kolejność”
- EN: „Drag to reorder”

Keep `pockets.index.listTitle` in locale files (unused in UI after this change; safe to remove in a follow-up cleanup).

## Data flow

Unchanged:

```
GET pockets.index → PocketController::index
  → ListPockets + PocketFormOptions
  → Inertia pockets/Index { pockets, icons, colors, currencies }
```

Frontend: `watch` syncs props into `list`; reorder patches `pockets.reorder`; modals use existing handlers; delete uses `window.confirm` then `router.delete`.

## Files

| File | Change |
|------|--------|
| `resources/js/pages/pockets/Index.vue` | One card, `PocketRow`, remove `listTitle` and legacy borders |
| `resources/js/components/pockets/PocketRow.vue` | **New** |
| `resources/js/components/pockets/PocketBadge.vue` | `rounded-lg` on icon tile |
| `resources/js/components/pockets/PocketProgressBar.vue` | Track height, fill token, `tabular-nums` on caption |
| `resources/js/locales/pl.json`, `en.json` | `pockets.index.dragHandle` |

Do not add PHP Actions/Resources. Do not change `PocketFormDialog`.

## Testing

| Check | Method |
|-------|--------|
| Index still renders with pockets | Existing Pest `PocketCrudTest` (smoke) |
| Reorder still works | `PocketReorderTest` |
| Visual: card, drag, empty, progress, status pills, dark, mobile | Manual (browser) |

No new Pest/Vitest tests for CSS classes.

## Out of scope

- `PocketFormDialog` interior (already OpenShip dialog chrome)
- `BudgetPocketSection` styling in monthly budget
- Floor empty state with heading + CTA (brainstorming option C)
- Delete confirmation dialog (stays `window.confirm`)
- Card grid / tile layout instead of list
- Shared draggable-list primitive for categories + pockets
- PHP / new Inertia props

## Implementation notes (for plan)

1. Add `PocketRow` first; wire `Index.vue` to use it inside existing `VueDraggable`.
2. Move card classes to a single outer wrapper; remove all `border-sidebar-border/70` and `divide-sidebar-border` usage on this page.
3. Update `PocketBadge` and `PocketProgressBar` in the same PR so rows look consistent immediately.
4. Add `pockets.index.dragHandle` to both locale files.
5. After Vue: Prettier. No PHP expected — skip Pint unless touched.
6. Verify in browser: drag reorder, edit modal, delete confirm, progress bar with/without target, completed/overdue pills, empty state, dark mode, mobile row layout.

## Success criteria

- All pocket rows sit on one lifted `rounded-2xl` card with no inner section title.
- Row density matches `/konta` and `/kategorie` lists (`px-3 py-2.5`).
- Drag-and-drop, edit, delete, progress bar, and status pills still work.
- `PocketBadge` icon tile uses `rounded-lg`.
- Status pills use OpenShip opacity/border recipe.
- Page header and modals show no regression.
