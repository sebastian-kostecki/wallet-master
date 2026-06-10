# Budget plan cell — icon alignment & align-to-actual action — design spec

**Status:** Approved in brainstorming (2026-06-10)  
**Builds on:** `.docs/superpowers/specs/2026-06-05-budget-ux-redesign-design.md`, `.docs/superpowers/specs/2026-06-10-budget-yearly-column-layout-design.md`  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Scope:** `resources/js/components/budget/EditableEstimateCell.vue`, `resources/js/components/budget/BudgetCategorySection.vue`, `resources/js/pages/budget/Monthly.vue`, `resources/js/pages/budget/Yearly.vue`, `resources/js/locales/{pl,en}.json`

## Summary

Align edit icons in the budget **Plan** column by giving amounts a fixed width with right alignment. On the **monthly** view, add a second icon-only action to pre-fill the plan input with the **actual (execution)** amount, using the same save/cancel flow as manual plan edit. On the **yearly** view, apply only the icon alignment fix.

## Problem

In `EditableEstimateCell`, the plan amount and pencil button sit in a `flex` row without a fixed amount width. Shorter and longer formatted amounts shift the edit icon horizontally per row. On monthly budget, users who want to set the plan to match actual spending must manually copy the execution value — there is no shortcut.

## Decisions log

| Topic | Decision |
|-------|----------|
| Amount block width | Fixed `w-28` (matches edit input width) |
| Amount alignment | **Right** (`text-right tabular-nums`) |
| Align button style | **Icon only** + `aria-label` (consistent with pencil) |
| Align button icon | `Equal` (lucide-vue-next) |
| Align when plan = actual | **Always active** — opens input with execution value; user may confirm or edit |
| Active row modes | `'plan' \| 'align' \| null` alongside existing `editingCategoryId` |
| Mutual exclusion | In edit/align mode: hide both action icons; show input + save + cancel only |
| Save endpoint (align) | Reuse `PATCH categories.estimates.monthly` with input value |
| Yearly align action | **Out of scope** — icon alignment only |
| Pocket section | **Out of scope** |
| Approach | **A** — extend `EditableEstimateCell` (minimal diff) |
| Monthly plan column width | Increase `--budget-col-plan` to `11.5rem` (two icon buttons) |
| Yearly plan column width | Keep `9rem` (one icon button) |
| Telemetry | Reuse existing `category_estimate_monthly_saved` event |

## UI design

### Display state (resting)

```
Monthly:  [  amount (w-28, right)  ] [Pencil] [Equal]
Yearly:   [  amount (w-28, right)  ] [Pencil]
```

- Amount span: `w-28 text-right tabular-nums shrink-0`
- Buttons: `h-8 w-8 shrink-0`, ghost variant (unchanged)
- All pencil icons vertically aligned across category rows

### Edit / align state

```
[ Input w-28 ] [Check save] [X cancel]
```

- Both action buttons hidden
- Keyboard: `Enter` → save, `Escape` → cancel (unchanged)
- Validation: same decimal rules as today (`/^\d+([.,]\d{1,2})?$/` or empty)

### Column widths

| View | `--budget-col-plan` | Reason |
|------|---------------------|--------|
| Monthly | `11.5rem` | `w-28` amount + 2 × `w-8` buttons + gaps |
| Yearly | `9rem` | `w-28` amount + 1 × `w-8` button + gap |

Set via scoped `.budget-page` CSS vars in each page component.

## State management (Monthly)

```ts
editingCategoryId: ref<number | null>(null)
editingMode: ref<'plan' | 'align' | null>(null)
```

| Action | Result |
|--------|--------|
| Click pencil | `editingCategoryId = row.id`, `editingMode = 'plan'`, draft = `monthly_plan` |
| Click align | `editingCategoryId = row.id`, `editingMode = 'align'`, draft = `actual` |
| Save | `PATCH categories.estimates.monthly` with trimmed input; reset both refs on finish |
| Cancel | Reset both refs to `null` |
| Edit another row | Only one row active at a time (existing behaviour) |

Yearly keeps only `editingCategoryId` — no `editingMode`, no align button.

## Component changes

### `EditableEstimateCell.vue`

New props:

| Prop | Type | Default | Purpose |
|------|------|---------|---------|
| `mode` | `'plan' \| 'align' \| null` | `null` | Which draft source to use when `isEditing` |
| `alignValue` | `string \| null` | `null` | Execution amount for align pre-fill |
| `showAlignButton` | `boolean` | `false` | Show second action button |

New emit: `start-align`.

`watch(isEditing)` logic:

- `mode === 'plan'` → `draft = plan ?? ''`
- `mode === 'align'` → `draft = alignValue ?? ''`

Display template structure:

```html
<div class="flex items-center gap-1">
  <span class="w-28 shrink-0 text-right tabular-nums">…</span>
  <Button pencil @click="start-edit" />
  <Button equal v-if="showAlignButton" @click="start-align" />
</div>
```

### `BudgetCategorySection.vue`

- Pass `show-align-button="variant === 'monthly'"`, `align-value="row.actual"`, `:mode="editingCategoryId === row.category_id ? editingMode : null"` (mode prop forwarded from parent)
- New prop `editingMode` from parent (monthly only; nullable)
- Emit `start-align` → parent

### `Monthly.vue`

- Add `editingMode` ref and `startAlign(categoryId)` handler
- Pass `editing-mode` to `BudgetCategorySection`
- Handle `@start-align` event
- `cancelEdit()` resets both refs
- `saveMonthlyEstimate` unchanged — works for both plan edit and align save
- Update `--budget-col-plan: 11.5rem`

### `Yearly.vue`

- Update `--budget-col-plan` if needed (stays `9rem`)
- No logic changes

## i18n

Add to `budget.estimate` in `pl.json` and `en.json`:

| Key | PL | EN |
|-----|----|----|
| `align` | Wyrównaj do wykonania — {name} | Align to actual — {name} |

## Data flow

```
Align click → editingMode='align', draft=actual
Save → router.patch(categories.estimates.monthly, { year, month, amount })
     → existing SaveMonthlyEstimate action
     → page reload via Inertia, editing state cleared
```

No backend changes required.

## Testing

### Automated

No new PHP/feature tests — UI-only change with existing PATCH endpoint.

### Manual checklist

- [ ] Monthly: pencil icons aligned across all income/expense rows
- [ ] Monthly: align button visible on every row, always enabled
- [ ] Monthly: align opens input pre-filled with execution amount
- [ ] Monthly: save from align updates plan to input value
- [ ] Monthly: cancel returns to display state with both action icons
- [ ] Monthly: starting plan edit hides align button (and vice versa)
- [ ] Monthly: only one row in edit mode at a time
- [ ] Yearly: pencil icons aligned; no align button
- [ ] Enter/Escape work in both monthly modes
- [ ] Invalid amount shows same error behaviour as plan edit

## Out of scope

- `BudgetPocketSection` alignment or align action
- Yearly align-to-actual
- New API endpoints or telemetry events
- Tooltip on align button (icon + aria-label only per decision)
