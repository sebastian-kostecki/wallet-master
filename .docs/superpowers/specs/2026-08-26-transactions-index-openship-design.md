# Transactions index list OpenShip — design spec

**Date:** 2026-08-26  
**Status:** Approved in brainstorming  
**Scope:** OpenShip restyle of the `/transakcje` list block only: desktop table, mobile rows, empty states, pagination.  
**Depends on:** `.docs/superpowers/specs/2026-08-26-transactions-modals-design.md` (modals already shipped). This spec **supersedes** that doc’s “list restyle: out”.  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md` (surface ladder, tables/lists, empty state); `/konta` list card + `AccountRow` as the visual reference.

## Goal

Make the transactions list read as the same quiet operator UI as the accounts list: grey page, one lifted white card for rows, empty state on the floor, compact rows, no zebra, color only for money and type. Columns, sort, filters, banners, summary cards, and modals stay as they are.

## Decisions log

| Topic | Decision |
|-------|----------|
| Slice | **B:** List block = table + mobile rows + empty + pagination. Summary income/expense cards, header filters, banners, modals out |
| Density | **A:** Compact like `AccountRow` (`px-3 py-2.5`) |
| Empty copy | **A:** New short heading + existing sentence as description; secondary CTAs become `ghost` |
| Implementation | **1:** New `TransactionRow` (`variant` desktop / mobile). Restyle `PaginationBar` in place. No shared `DataTable` |

## Current state

- `resources/js/pages/transactions/Index.vue` owns the full list markup (~desktop `<table>` and a duplicated mobile stack).
- One wrapper `rounded-xl border border-sidebar-border/70` contains skeleton, empty, table, mobile list, and (via `TablePage`) pagination.
- Rows: `px-6 py-4`, `list-row-hover`, dividers `border-sidebar-border/50`. Expense/amount use `rose`. Adjustment badge uses `amber-50` / `amber-200`. Type icon wells use solid-tint backgrounds (`bg-blue-50`, `bg-rose-50`, …).
- Empty: one `text-sm text-muted-foreground` sentence inside the bordered box; primary + `variant="secondary"` actions.
- `TablePage` and `PaginationBar` are used **only** by this index. Pager page links are `rounded-md`; `PaginationBar` hardcodes `route('transactions.index')`.
- Add/Edit are already index modals. Row pencil/trash already emit to those dialogs.

## Target behavior

No PHP, routes, or Inertia props change. Behavior of sort, filters, pagination query, telemetry, tooltips, and read-only rows (deleted account) is unchanged.

### Surface composition

```
[ import / transfer banners — unchanged ]
[ income / expense summary cards — unchanged ]

  empty:                              has rows:
  grey floor                          ┌─ card rounded-2xl bg-card ─┐
  heading + description               │ thead + TransactionRow…    │
  [primary]  [ghost]                  └────────────────────────────┘
                                      PaginationBar on the grey floor
```

**Card** (`overflow-hidden rounded-2xl border border-transparent bg-card`, no resting shadow): desktop table, mobile rows, **or** the loading skeleton. Not empty, not the pager.

**`TablePage`:** keep the current API. Empty slot renders on the floor (no card). Default slot is the card. Pager already sits after the default slot (`gap-4`) — that is the floor placement.

**`aria-busy`:** keep on a region that wraps skeleton + `TablePage` (the current outer wrapper, without the old border).

### `TransactionRow`

New `resources/js/components/transactions/TransactionRow.vue`.

| | Desktop | Mobile |
|---|---------|--------|
| Root | `<tr>` | `<div>` |
| Shown by | Index `hidden md:block` table | Index `md:hidden` stack |
| Padding | `px-3 py-2.5` | `px-3 py-2.5` |
| Hover / divider | `list-row-hover`; `border-b border-border/50 last:border-b-0` | same |

Index still renders **two** lists (`hidden md:block` table vs `md:hidden` stack), one `v-for` each. Each item is `<TransactionRow variant="desktop" | "mobile">` — not a single instance that switches on `matchMedia`.

Props: the transaction, `variant`, `deleteProcessing`, and the same kind of formatters `AccountRow` takes (`formatAmount`, date helpers, `formatCurrencySymbol`, `resolveAccountTypeIcon`). Emits `edit` / `delete`. Type-icon variant logic (`transactionIcon`) **lives in the row**, not on the page.

Mobile wrapper drops `divide-y`; the row owns `border-b border-border/50 last:border-b-0` (desktop `<tr>` too).

Index keeps `<thead>`, sort buttons, `caption`, column widths (`w-36` date, `w-56` category, …), and the `md` visibility split. Thead labels: `text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60`. Sort icons and `aria-sort` unchanged.

**Columns and content hierarchy stay.** Desktop: date (+ relative), type well + subject/description (+ raw-statement tooltip), category / transfer / pocket, account (+ read-only badge), amount, actions. Mobile: no type well (as today); description/subject, date, category, account, amount + actions on the right.

**Status color (only here):**

| Element | Recipe |
|---------|--------|
| Type wells (`h-9 w-9 rounded-lg`) | `bg-{hue}-500/10` + `text-{hue}-600 dark:text-{hue}-400`. Transfer `blue`, adjustment `amber`, expense `red` (replace `rose`), income `emerald`. No extra border on the well |
| Adjustment pill | `rounded-full border border-amber-500/20 bg-amber-500/10` + amber text; existing `transactions.index.badges.adjustment` |
| Amount | `tabular-nums font-medium`; `>= 0` emerald, `< 0` red (`text-red-600 dark:text-red-400`) |

Account logo `h-9 w-9 rounded-lg`, `CategoryBadge`, ghost icon edit/delete: same layout as today. Deleted account: no actions, existing read-only copy.

### Empty states

OpenShip recipe (same as `/konta`): `py-16 text-center`; heading `text-2xl font-medium tracking-tight`; description `mt-2 max-w-sm text-sm text-muted-foreground`; actions `mt-8` primary + optional ghost.

| Variant | Heading (new) | Description (existing sentence) | Actions |
|---------|---------------|--------------------------------|---------|
| Filtered | PL „Brak wyników” / EN „No results” | `empty.filtered` → `.message` | Dodaj transakcję → `openCreateDialog`; Wyczyść filtry → `clearFilters`, `variant="ghost"` |
| First use | PL „Brak transakcji” / EN „No transactions” | `empty.firstUse` → `.message` | Dodaj transakcję; Zaimportuj plik **only if** `imports.index` exists, still a `Link` to that route (not `ImportDialog`), `variant="ghost"` |

i18n shape (replace the current string values):

```text
transactions.index.empty.filtered.heading
transactions.index.empty.filtered.message
transactions.index.empty.firstUse.heading
transactions.index.empty.firstUse.message
transactions.index.empty.import          (unchanged)
```

`filters.clear` reused. Update both `pl.json` and `en.json`. Any `t('transactions.index.empty.filtered')` / `firstUse` call sites must use the nested keys.

### Pagination and skeleton

Restyle `resources/js/components/pagination/PaginationBar.vue` in place (safe: transactions-only). Do **not** generalize the hardcoded `transactions.index` route.

- Page number links: `h-9 min-w-9 rounded-xl` (not `rounded-md`); inactive `text-muted-foreground hover:bg-foreground/[0.06] hover:text-foreground`; active `bg-primary text-primary-foreground`.
- Prev / Next: keep `Button variant="outline" size="sm"`.
- “Na stronę” / “Strona x z y”: keep `text-xs text-muted-foreground` and existing keys.
- Query, `preserveScroll`, `transactions_page_changed`: unchanged.

Loading: five skeleton bars inside the **same card** as the table, `p-5`.

## Data flow

Unchanged: `TransactionIndexRequest` → `ListTransactions` → `TransactionController::index` → Inertia `transactions/Index`. Row actions still open the existing form/delete dialogs.

## Files

| File | Change |
|------|--------|
| `resources/js/pages/transactions/Index.vue` | Card vs floor split; empty headings; drop inlined row markup; pass formatters into `TransactionRow` |
| `resources/js/components/transactions/TransactionRow.vue` | **New** — desktop `<tr>` + mobile stack |
| `resources/js/components/pagination/PaginationBar.vue` | Radius + hover/active tokens |
| `resources/js/locales/pl.json`, `en.json` | Nested empty heading/message keys |
| `resources/js/components/page/TablePage.vue` | No change |

Do not add `components/ui/` table primitives. Do not add PHP Actions/Resources.

## Testing

| Check | Method |
|-------|--------|
| Index still renders with filters/sort/pagination props | Existing Pest `TransactionIndexTest` (and sibling index tests) as smoke |
| Nested empty i18n keys used in Vue | Grep / compile; both locale files |
| Visual: card, compact rows, empty floor, pager, dark, mobile | Manual (browser) |

No new Pest test for CSS classes. No new Vitest harness for the table.

## Out of scope

- Income / expense summary cards
- Header filters (`TransactionsIndexHeaderFilters`) and the Add menu
- Import failed-rows banner, transfer-candidate banner, Import / form / delete dialogs
- Changing `TablePage` API or teaching `PaginationBar` other routes
- Budget tables, categories/pockets lists, a shared `DataTable`
- Column set, sort fields, or query persistence

## Implementation notes (for plan)

1. Extract `TransactionRow` first so Index class changes are not applied twice to duplicated markup.
2. Move the card class from around `TablePage` to around the **default slot only**; empty must not sit in `bg-card`.
3. Replace `rose` with `red` only inside `TransactionRow` (type well + desktop/mobile amount). Summary cards keep `rose` (out of scope).
4. After Vue: Prettier. After any PHP (none expected): Pint + Sail `--filter=Transactions`.
5. Verify in the browser: desktop table, mobile stack, filtered empty, first-use empty, pager, row hover, dark mode.

## Success criteria

- List rows sit on one lifted `rounded-2xl` card; empty and pager sit on the grey page.
- Row density matches `/konta` list (`px-3 py-2.5`).
- Empty states have a heading + description and ghost secondary actions.
- Sort, filters, pagination, tooltips, and read-only rows still work.
- Summary cards and chrome are visually untouched.
