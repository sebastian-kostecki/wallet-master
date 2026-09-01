# Accounts: Rachunki / Portfele tabs — design spec

**Date:** 2026-08-26  
**Status:** Approved in brainstorming  
**Scope:** Accounts index IA (tabs), create prefill, dashboard summary placement  
**Depends on:** `.docs/superpowers/specs/2026-08-25-page-header-tabs-design.md` (`PageTabs` + `pageRegistry`)  
**Related:** `.docs/superpowers/specs/2026-06-11-accounts-savings-summary-design.md` (card metrics unchanged; **placement** moves from `/konta` to dashboard)

## Goal

Split `/konta` into two tabs so bank accounts and cash wallets are not mixed in one list. Remove the summary card from the accounts page and show the same card on the dashboard, in place of the branded preview block.

## Decisions log

| Topic | Decision |
|-------|----------|
| Split criterion | **Bank:** Portfele = `bank === cash`; Rachunki = every other bank (BNP, mBank, future banks) |
| `checking` / `savings` | Unchanged; still an account field, does **not** drive tabs |
| Tabs mechanism | **A:** Query on the same `accounts.index` route (`?kind=`) via `PageTabs`, like pockets |
| Summary on `/konta` | Remove |
| Summary on dashboard | Same `AccountsSummaryCard` (PLN total + savings PLN); replace the preview block; quick actions stay above |
| Add CTA | Tab-specific label + create URL with `kind`; form **prefills** bank; user may change it |
| List filtering | Client-side in Vue; backend still returns all of the user's accounts |
| After save redirect | Tab matching the **saved** `bank`, not the tab the user came from |

## Current state

- `/konta` (`accounts.index`) lists all accounts in one grid/list. `AccountsSummaryCard` sits above the list (PLN total, savings PLN, PLN count).
- `AccountType` is `checking` \| `savings`. `Bank` includes `cash`. Sidebar label is already “Rachunki i portfele”.
- Tabs exist in `pageRegistry` for pockets (`?filter=`) and budget (two routes). Accounts has no `tabGroup`.
- Dashboard is a closure in `routes/web.php` with no account props; preview is decorative.

## Target behavior

### URLs and chrome

One named route: `accounts.index` (localized path `/konta`). Query `kind`:

| Tab | Query | Shown accounts |
|-----|--------|----------------|
| Rachunki (default) | `?kind=accounts` or missing / invalid `kind` | `bank !== 'cash'` |
| Portfele | `?kind=wallets` | `bank === 'cash'` |

- `h1` stays the node title (**Konta**). Tab labels are **Rachunki** / **Portfele**.
- Document title follows existing chrome: node title · active tab label.
- Sidebar (`nav.accounts`) unchanged.
- `pageNodes.accounts` gets `tabGroup: 'accounts'`. Tab params are static `{ kind: 'accounts' }` / `{ kind: 'wallets' }`; `defaultKey` is `accounts` so `/konta` without query activates Rachunki (same fallback as pockets). `useCurrentPage` already merges query-string keys into `current.params`, so `kind` matches the pockets `filter` pattern.

### Index list

- `Index.vue` reads `kind` from the page URL query (same source as chrome). Missing or invalid `kind` → Rachunki filter (`bank !== 'cash'`).
- Header CTA: Rachunki → “Dodaj rachunek” → `accounts.create?kind=accounts`. Portfele → “Dodaj portfel” → `accounts.create?kind=wallets`.
- Empty state is **per tab**. Accounts on the other tab do not suppress it. Copy and CTA match the active tab.
- Grid/list toggle stays; shared `localStorage` key `accounts.viewMode`. Toggle only when the **current tab** has rows.
- `AccountCard` / `AccountRow`, adjust/delete dialogs: unchanged.
- `AccountsSummaryCard` is not rendered on this page.

### Create (and edit)

Single create/store form. `kind` is read from the query in Vue (no extra controller field):

| Entry | Default `bank` |
|-------|----------------|
| `?kind=wallets` | `cash` |
| `?kind=accounts` or missing | first `Bank` case that is **not** `cash` (today: BNP Paribas) |

Create passes chrome overrides so the `h1` is “Dodaj rachunek” or “Dodaj portfel” according to `kind` (missing `kind` uses the rachunek copy). Fields stay the same. User can change bank. Edit is unchanged (no `kind`). `checking` / `savings` unchanged.

### Redirect after write

`store`, `update`, and `destroy` redirect with `kind` derived from that account’s **saved** `bank` (the account being written or deleted):

`to_route('accounts.index', ['kind' => $account->bank === Bank::Cash ? 'wallets' : 'accounts'])`

### Dashboard

Replace the `web.php` closure with `App\Http\Controllers\DashboardController`:

- Same account query as `AccountController::index` (user scope, currency, order by name).
- Inertia prop `accounts` via `AccountResource` (same shape as the list).
- No new Action, no server-side totals.

Layout: quick-action cards unchanged and **above**. Preview section **removed**. `AccountsSummaryCard` in its place, same metrics and empty/zero copy as today (`0,00 zł`, hint when no PLN accounts). Isolation: only the authenticated user's accounts.

## Data flow

```
GET /konta[?kind=] → AccountController::index → all accounts
                 → Index.vue filters by bank; PageTabs from registry

GET /konta/create?kind= → AccountController::create (unchanged props)
                       → Create.vue prefills bank from kind

POST store / PATCH update / DELETE → redirect with kind from that account’s bank

GET /panel (dashboard) → DashboardController → accounts → AccountsSummaryCard
```

Invalid `kind` on index: treat as Rachunki. Create without `kind`: first non-cash bank. Store validation unchanged.

## i18n (new keys)

Add PL/EN keys for: tab labels (`accounts.tabs.accounts`, `accounts.tabs.wallets`), tab-specific add CTAs, per-tab empty heading/message/cta, create title/description variants. Reuse `accounts.summary.*` on the dashboard (no new summary strings).

## Files

| File | Change |
|------|--------|
| `resources/js/lib/pageRegistry.ts` | `pageTabGroups.accounts`; `pageNodes.accounts.tabGroup` |
| `resources/js/lib/pageRegistry.test.ts` | Assert two tabs, default `accounts`, `kind` params |
| `resources/js/pages/accounts/Index.vue` | Filter by `kind`; tab CTAs; per-tab empty; drop summary |
| `resources/js/pages/accounts/Create.vue` | Prefill bank from `kind`; chrome title/description overrides |
| `resources/js/pages/Dashboard.vue` | Remove preview; render `AccountsSummaryCard` |
| `resources/js/locales/pl.json`, `en.json` | Tab / CTA / empty / create copy |
| `app/Http/Controllers/DashboardController.php` | New; query + `AccountResource` |
| `routes/web.php` | Point `dashboard` at the controller |
| `app/Http/Controllers/Accounts/AccountController.php` | Redirect `kind` on store/update/destroy |
| `tests/Feature/Accounts/AccountIndexTest.php` | Still all accounts in props |
| `tests/Feature/Accounts/AccountStoreTest.php` (and update) | Redirect query `kind` |
| New dashboard feature test | 200 + `accounts` shape + isolation |

`AccountsSummaryCard.vue` — **no metric changes**; only its parent moves.

## Testing

| Check | Method |
|-------|--------|
| Index props still include cash and bank accounts | Pest feature (`AccountIndexTest`) |
| Store cash → `kind=wallets`; other bank → `kind=accounts` | Pest feature |
| Update that changes bank to/from cash → matching `kind` | Pest feature |
| Dashboard 200, `accounts` for current user only | Pest feature |
| Tab group default and `kind` params | Vitest `pageRegistry.test.ts` (and `pageChrome.test.ts` fallback when `kind` is missing) |

No Pest assertion on which rows Vue displays (filter is client-side). Manual smoke: switch tabs, empty tab with data on the other tab, create from each tab, change bank on create and land on the other tab, dashboard totals match the old `/konta` card.

## Out of scope

- Separate routes `/konta/rachunki` and `/konta/portfele`
- Backend filtering of the index query
- Changing summary metrics (per-tab totals, non-PLN, checking subtotal)
- New account type or renaming `checking` / `savings`
- Session persistence of `kind`
- Localized query values (`kind` stays `accounts` \| `wallets`)
- Sidebar rename
- Grid/list toggle redesign

## Implementation notes (for plan)

1. Register `accounts` tab group before changing `Index.vue`, so chrome and list agree on `kind`.
2. Classify a wallet with `account.bank === 'cash'` only (string from `AccountResource`), not `type`.
3. Default non-cash bank: skip `Bank::Cash` when iterating `Bank::cases()` — do not assume index `0`.
4. Dashboard controller lives at `Http/Controllers/DashboardController.php` (no new domain folder). Duplicate the small index query; do not introduce `Actions/Accounts` for this.
5. After PHP changes: Pint + Sail tests scoped to Accounts + the new dashboard test. After Vue: Prettier on touched files; Vitest for `pageRegistry`.
6. Reconcile `.docs/checklist.md` on the feature branch before merge (UI split; dashboard summary). PRD glossary **Konto** stays; this is presentation by `bank`, not a new entity.
