# Dashboard operator overview — design spec

**Date:** 2026-08-27  
**Status:** Approved in brainstorming  
**Scope:** Replace the dashboard launchpad (quick-action cards + `AccountsSummaryCard`) with an operator overview for the **current calendar month**. Four KPI tiles, a monthly budget snapshot (including one CSS plan-vs-actual chart), and recent transactions.  
**Depends on:** `.docs/superpowers/specs/2026-08-25-page-header-tabs-design.md` (`PageHeader`); `.docs/superpowers/specs/2026-08-27-budget-openship-design.md` (budget summary shape); `.docs/superpowers/specs/2026-08-26-accounts-wallets-tabs-design.md` (current dashboard summary placement — **superseded** for dashboard layout).  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md`; PRD §3.2 excludes charts as product-wide reports — this spec **allows one decorative chart on `/panel` only**.

## Goal

Make `/panel` the first-look screen for money **right now**: balances, this month’s P&L, this month’s budget plan vs actuals, and the latest activity. Navigation stays in the sidebar and the page header. No new domains of data — only compose what accounts, transactions, and monthly budget already compute.

## Decisions log

| Topic | Decision |
|-------|----------|
| Role | **A:** Operator overview — not a launchpad, not an onboarding hybrid |
| v1 widgets | **A:** Four KPI tiles + budget snapshot + recent transactions. No account list, no pockets list, no category-breakdown chart |
| Period | **A:** Current calendar month (`now()` app timezone). No period switcher on the dashboard |
| Charts | One CSS/SVG grouped bar in the budget card (plan vs actual for income and expense). **No** chart library, **no** category pie, **no** time-series |
| Cashflow tiles | P&L for the month, `transfer_id IS NULL` (same rule as the transactions index summary). Expense stored/shown as a **positive** absolute |
| Budget snapshot | Same `summary` as `/budżet` monthly for that year/month, **including** `BudgetSummary::withPockets` |
| Recent list | Last **8** transactions **across all time** (activity feed), not clipped to the month |
| Quick actions | **Remove** the three large nav cards |
| `AccountsSummaryCard` | **Remove** from dashboard; delete the component if nothing else imports it |
| Header | Title **Panel**; subtitle = formatted current month (e.g. `sierpień 2026`); primary action **Dodaj transakcję** → `transactions.create` |
| Totals | Compute PLN total and savings PLN **in PHP** (same rules as the old Vue card: PLN only; savings = `type === savings`) |

Locked after the user asked to skip further questions: recent = 8 all-time; budget card = compact numbers + progress chips + the one CSS chart; row click on recent goes to `transactions.index` (read-only; no edit/delete on the dashboard).

## Approaches considered

1. **Compose existing actions (recommended)** — `ShowDashboard` loads accounts totals, month P&L via `BudgetTransactionQuery`, monthly budget via `ListMonthlyBudget::handleForPeriod`, recent transactions via Eloquent `limit(8)`. Controller maps `TransactionResource` only. Reuses budget math; one extra dashboard action.
2. **Fat `DashboardController`** — queries and aggregations in the controller. Violates Variant A (controller must not own `selectRaw` / date-period workflow).
3. **Duplicate budget row-building in `ShowDashboard`** — copy-paste of `ListMonthlyBudget`. Diverges the moment pocket or plan rules change.

**Chosen:** 1.

## Current state

- `DashboardController` loads the user’s accounts and renders `Dashboard`.
- `Dashboard.vue`: three quick-action cards (accounts / transactions / pockets) plus `AccountsSummaryCard` (PLN total + savings PLN, computed in Vue).
- `PageHeader` title is `dashboard.title` (“Dzień dobry w Ogarniam Portfel”) with onboarding `dashboard.description`.
- Monthly budget already exposes `summary` (`plan` / `execution` / `progress`, with pockets folded into expense/income). Period helper: `BudgetPeriod::forMonth`. P&L date column: `COALESCE(booked_at, date)`.
- No chart package in `package.json`.
- PRD §3.2: “Wykresy i zaawansowane raporty” out of scope globally; user explicitly allowed a modest chart on this screen.

## Target behavior

### Surface composition

```
PageHeader
  Panel                              [ Dodaj transakcję ]
  sierpień 2026                      (formatted from year+month props)

grey floor, flex flex-col gap-6

┌ KPI ┐ ┌ KPI ┐ ┌ KPI ┐ ┌ KPI ┐     grid 1 → 2 → 4 cols
  Suma    Oszcz.  Wpływy  Wydatki     (month P&L on the last two)

lg: 2 columns, gap-6; stack on smaller screens

┌─ budget card ─────────────────┐  ┌─ recent card ──────────────┐
│ BUDŻET · sierpień 2026    →   │  │ OSTATNIE              →    │
│ plan vs actual bars (CSS)     │  │ row …                      │
│ Przychody  plan | actual | %  │  │ row …                      │
│ Wydatki    plan | actual | %  │  │ empty: muted line          │
│ Saldo      plan | actual      │  └────────────────────────────┘
└───────────────────────────────┘
```

**Floor:** `bg-background` from the app shell. Cards: `rounded-2xl border border-transparent bg-card`, no resting shadow. Gap `gap-6`.

**Not on this page:** account/wallet list, pocket progress list, category pie, import/transfer banners, period pager, quick-action marketing cards.

### Period

- `year` / `month` from `now()` (application timezone), same default as `MonthlyBudgetRequest` when query params are absent.
- KPI cashflow and budget snapshot use `BudgetPeriod::forMonth($year, $month)` and `COALESCE(booked_at, date)`.
- Changing month happens only on `/budżet`. Dashboard always shows “this month”.
- Header subtitle is a formatted locale month+year from those props (Vue `toLocaleDateString` with `vue-i18n` locale). Not a static i18n sentence.

### KPI tiles

Four equal lifted cards (`p-5 sm:p-6`). Label: OpenShip section style (`text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60`). Value: `text-2xl font-semibold tabular-nums`.

| Tile | Source | Color |
|------|--------|--------|
| Suma sald (PLN) | Sum of `current_balance` where account currency is PLN | default foreground |
| Oszczędności (PLN) | Same sum where `type === savings` | default foreground |
| Wpływy | Month P&L income, `transfer_id IS NULL` | income green on the number (`signedMoneyClass` / emerald recipe) |
| Wydatki | Month P&L expense **absolute**, `transfer_id IS NULL` | expense red on the number |

Zero is valid (`0,00 zł`). Missing accounts → zeros, no blocking empty-state hero.

**Cashflow vs budget:** Tiles use the transactions-index P&L rule (no transfers, no pocket add-back). The budget card uses monthly `summary` **with pockets**. They can differ when the user saved/released envelopes this month. Do not hide that; do not add a footnote in v1.

Uncategorized P&L still counts in tiles (SUM over all non-transfer rows). Budget snapshot only includes categorized actuals plus pocket add-back — same as `/budżet`.

Reuse i18n: `accounts.summary.totalPln`, `accounts.summary.savingsPln`, `budget.summary.income`, `budget.summary.expense`. New keys only for dashboard-specific chrome (see i18n).

### Budget snapshot

Card title row: section label + text link to `budget.monthly` with `{ year, month }` (“Otwórz budżet”).

Body:

1. **Chart (the allowed one):** two groups (income, expense). Each group is two horizontal bars — plan (muted `bg-foreground/15`) and actual (income emerald / expense red at ~40% opacity). Scale **per group** to `max(plan, actual)`; both zero → empty tracks. Bars are `aria-hidden`; the numbers below are the accessible data. No tooltip library.
2. **Numbers:** three rows — Przychody, Wydatki, Saldo — columns Plan, Wykonanie, Wykonanie % (omit % on saldo). Reuse `formatMoney`, `signedMoneyClass` on saldo, `BudgetProgressCell` for percents. Do **not** embed the full `BudgetSummaryCard` table (no forecast column, no shared budget colgroup).

Empty plans and zero actuals: still render the card (zeros + empty bars). No second empty-state illustration.

### Recent transactions

Card title row: section label + text link to `transactions.index`.

Up to 8 rows, newest first: `orderByRaw COALESCE(booked_at, date) desc`, then `id desc`. Eager-load `account.currency`, `currency`, `category`, `pocket` so `TransactionResource` matches the index.

Compact read-only row (new `DashboardTransactionRow` — **do not** mount `TransactionRow` with edit/delete):

- Date (`booked_at` display date, `DD-MM-YYYY` or existing relative if cheap — prefer the same `date_relative` from the resource)
- Description (truncate)
- Category badge when present
- Amount + currency, `tabular-nums`, signed color

The row is not a button that opens the edit modal (`transactions.edit` only redirects to the index). Whole-card “see all” is the header link. Rows themselves are not required to be links.

Empty: one muted sentence inside the card (`dashboard.recent.empty`).

### Header chrome

| Piece | Behavior |
|-------|----------|
| Title | Change `dashboard.title` to **Panel** / **Dashboard** (same sense as `nav.dashboard`) |
| Description | Remove `descriptionKey` from the `dashboard` pageRegistry node. `Dashboard.vue` passes `:description` as the formatted month |
| Actions | Solid primary `Button` as-child `Link` to `route('transactions.create')`, label `transactions.index.addTransaction`, `Plus` icon `stroke-width="1.7"` |
| Tabs | None |

`dashboard.description`, `dashboard.quickActions`, and `dashboard.preview` become unused — **delete** those keys from `pl.json` / `en.json` when the last caller is gone.

### Empty / sparse data

No full-page empty. Tiles stay. Budget card stays at zero. Recent card shows the empty sentence. Adding the first account or transaction is already covered by sidebar + header CTA.

## Architecture

```
GET /panel (dashboard)
  DashboardController
    ShowDashboard::handle(User): void
      accounts → PLN total + savings total
      BudgetTransactionQuery + BudgetPeriod → cashflow
      ListMonthlyBudget::handleForPeriod(user, year, month) → summary + currency
      Transaction::…→limit(8) → recent
    getters → Inertia props (controller maps TransactionResource)
```

- Controller stays `app/Http/Controllers/DashboardController.php` (no `Http/Controllers/Dashboard/` folder required).
- Action: `app/Actions/Dashboard/ShowDashboard.php` — `handle(User $user): void` + typed getters. **No** `Http\Resources` inside the action.
- `ListMonthlyBudget`: extract `handleForPeriod(User $user, int $year, int $month): void`; existing `handle(MonthlyBudgetRequest)` delegates. Do not invent a fake Form Request for the dashboard.
- Frontend: `resources/js/pages/Dashboard.vue` + `resources/js/components/dashboard/*`. Not `components/ui/`.
- Tests: `tests/Feature/Dashboard/DashboardTest.php` (move the existing file). Isolation: other user’s accounts, P&L, and recent rows never appear. Freeze time with `Carbon::setTestNow` so “current month” is deterministic.

### Inertia props

```
period: { year: int, month: int }
balances: { total_pln: string, savings_pln: string }
cashflow: { income: string, expense: string }  // expense absolute, 2 dp
budget_summary: same array as ListMonthlyBudget::getSummary() for that month
currency: BudgetCurrency::pln()
recent_transactions: list of resolved TransactionResource
```

Drop the `accounts` collection from the page.

## i18n (new keys)

| Key | PL | EN |
|-----|----|----|
| `dashboard.title` (replace) | Panel | Dashboard |
| `dashboard.budget.title` | Budżet | Budget |
| `dashboard.budget.open` | Otwórz budżet | Open budget |
| `dashboard.recent.title` | Ostatnie | Recent |
| `dashboard.recent.open` | Wszystkie transakcje | All transactions |
| `dashboard.recent.empty` | Brak transakcji. | No transactions yet. |
| `dashboard.chart.plan` | Plan | Plan |
| `dashboard.chart.actual` | Wykonanie | Actual |

Month subtitle is formatted in Vue, not a translation string. Chart group labels reuse `budget.summary.income` / `budget.summary.expense`.

Keep `dashboard.headTitle` only if something still reads it; `PageHeader` uses `dashboard.title` for the document title. Remove `headTitle` if unused.

## Out of scope

- Period switcher, last-30-days, or yearly dashboard
- Account list, pocket list, category breakdown, import/transfer banners
- Chart.js / Unovis / any new npm chart dependency
- Edit/delete/create modals mounted on the dashboard (create goes through `transactions.create` → existing index modal flow)
- Computing totals in a currency other than PLN
- Drive-by restyle of sidebar, budget pages, or transaction index

## Testing

| Case | How |
|------|-----|
| Guest → login | Existing |
| Auth 200, `Dashboard` component | Existing, extend props |
| Isolation of balances, cashflow, recent | Pest: two users |
| Cashflow ignores other months and internal transfers | `setTestNow` + transfer pair |
| `budget_summary` matches monthly budget for current month | Same user, compare to `ListMonthlyBudget` or known estimate + actual |
| Recent capped at 8, newest first | Pest |
| `accounts` prop gone | Pest `missing('accounts')` |
| Chrome: title key still `dashboard.title`; no descriptionKey | Vitest `pageChrome.test.ts` if registry changes |

Manual smoke: light + dark; desktop 4 KPI + 2 columns; mobile stack; zeros with no data; month label in PL/EN.

## Risks

- **Two expense numbers** (P&L tile vs budget wykonanie with pockets). Accepted for v1.
- **Dashboard load ≈ monthly budget load** (category rows + pocket metrics). Acceptable; do not add caching in v1.
- **`EnsureUserCategories` side effect** when `ListMonthlyBudget` runs. Same as visiting `/budżet`; acceptable.
