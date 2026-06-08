# Accounts view toggle styling — design spec

**Status:** Approved in brainstorming (2026-06-08)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Reference UI:** `resources/js/pages/accounts/Index.vue`, `resources/js/components/accounts/AccountsViewToggle.vue`

## Summary

Align the accounts grid/list view toggle with the application's established segmented-control pattern (design-system tokens + `Button` variants). Keep icon + text labels. Change is limited to `AccountsViewToggle.vue` styling and markup — no behavior, routing, or i18n changes.

## Decisions log

| Topic | Decision |
|-------|----------|
| Reference pattern | **A:** Match `SegmentedControl` / `transactions/Create.vue` income/expense toggle |
| Label format | **A:** Keep icon + text (`LayoutGrid` / `List` + i18n labels) |
| Implementation approach | **A:** Update `AccountsViewToggle` in place (no `SegmentedControl` extension) |
| Scope | Single file: `AccountsViewToggle.vue` |

## Current state

`AccountsViewToggle.vue` uses raw `<button>` elements with Tailwind `neutral-*` colors (`bg-neutral-100`, `dark:bg-neutral-800`, `bg-white shadow-sm` for active). This is inconsistent with the design system defined in `resources/css/app.css` (`muted`, `secondary`, `border-input` tokens).

## Target appearance

### Container

```html
<div class="inline-flex gap-1 rounded-lg border border-input bg-muted/30 p-1" role="group" aria-label="…">
```

Matches `SegmentedControl.vue` and the transaction type toggle in `transactions/Create.vue`.

### Buttons

Two `Button` components (`type="button"`, `class="h-9"`):

| State | Variant | ARIA |
|-------|---------|------|
| Active | `secondary` | `aria-pressed="true"` |
| Inactive | `ghost` | `aria-pressed="false"` |

### Button content (unchanged)

- Icon: `LayoutGrid` or `List` (`h-4 w-4`, `aria-hidden="true"`)
- Text: `t('accounts.index.view.grid')` / `t('accounts.index.view.list')`

### Removed

All custom `neutral-*` classes, raw `<button>` elements, manual active/inactive color logic, and per-state `dark:*` overrides.

## Files

| File | Change |
|------|--------|
| `resources/js/components/accounts/AccountsViewToggle.vue` | Replace markup and classes per target appearance |
| `resources/js/pages/accounts/Index.vue` | **No change** |
| `resources/js/components/forms/SegmentedControl.vue` | **No change** |
| `locales/*.json` | **No change** |

## Behavior (unchanged)

- `v-model` API: `modelValue: 'grid' | 'list'`, emit `update:modelValue`
- `localStorage` persistence remains in `Index.vue` (`accounts.viewMode`)
- Toggle visible only when `accounts.length > 0`
- Grid/list layout switching unchanged

## Accessibility improvements

- `aria-pressed` on each option (new — not present on raw buttons today)
- Focus ring from `Button` component (`focus-visible:ring-2`)
- Container `aria-label` from existing i18n key `accounts.index.view.toggleAria`

## Dark mode

Handled automatically via design-system CSS variables (`--muted`, `--secondary`, `--input`, `--border`). No explicit `dark:*` classes required.

## Verification

| Check | Method |
|-------|--------|
| Visual match with transaction type toggle | Manual: compare `/accounts` toggle vs `/transactions/create` toggle in light and dark mode |
| Toggle switches views | Manual: click grid/list; confirm layout changes |
| Persistence | Manual: refresh page; confirm last selection restored from `localStorage` |
| Focus | Manual: keyboard Tab + Enter/Space on toggle options |

No new PHP/Pest tests — pure presentational Vue change with no backend or business logic.

## Out of scope

- Extending `SegmentedControl` with icon support
- Icon-only compact variant
- Repositioning toggle on the accounts index page
- Extracting a shared `IconSegmentedControl` component

## Implementation notes (for plan)

1. Update imports in `AccountsViewToggle.vue` (`Button` from `@/components/ui/button`)
2. Replace container and button markup per target appearance
3. Visual smoke test on `/accounts` (light + dark)
4. No Pint/PHP tests required
