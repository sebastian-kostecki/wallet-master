# Transactions index Add/Edit modals — design spec

**Date:** 2026-08-26  
**Status:** Approved in brainstorming  
**Scope:** Income/expense Add/Edit as overlays on `/transakcje`. No list restyle. Transfer create stays a full page.  
**Depends on:** `.docs/superpowers/specs/2026-08-26-accounts-modals-design.md` (overlay-on-index pattern, shared `ui/dialog` chrome already shipped).  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md` (dialog as highest surface)

## Goal

Keep the user on `/transakcje` for creating and editing P&L transactions (income / expense / the same form for `adjustment`). Transfer, Import, and row delete stay as they are. The transactions list, summary cards, and filters are **not** restyled in this spec.

## Decisions log

| Topic | Decision |
|-------|----------|
| Spec slice | **A:** P&L Add/Edit overlay only. `transfers.create` stays a page |
| Edit modal body | **B:** Form + compact transfer/unlink banner + collapsible raw statement. Delete stays on the row |
| Hints column | **A:** Drop. Single column of fields. Transfer field hints on account/amount stay |
| List restyle | **A:** Out. Only rewire CTAs from `Link` to open the modal |
| `booked_at` | **A:** Keep, collapsible “advanced” inside the modal |
| Sidebar CTA | **C:** Already on Index → open locally. Otherwise visit `GET create` (redirect + `?new=1`) |
| Implementation | Overlay on index + one-shot `?new=1`. Edit hydrates from the current list row. No `?edit=` |
| GET edit | Redirect to index, **do not** auto-open (row may be on another page) |
| Dirty form | Close discards without a second confirm |
| Close while saving | Overlay, Esc, and X ignored while `form.processing` |

## Current state

- `/transakcje` is the list (filters, summary, Import modal, transfer-candidate banner, delete dialog). Add is a header-menu `Link` to `transactions.create` plus empty-state links. Edit is a pencil `Link` to `transactions.edit`. Sidebar primary button is `Link` to `transactions.create`.
- Create and Edit are full Inertia pages (`Create.vue`, `Edit.vue`). Create is a two-column form + hint cards. Edit adds a transfer unlink banner, a raw-statement panel, hint cards, and a danger zone (delete already exists on the index row).
- Index already ships `accounts` (all of the user’s accounts) and `categories`. It does **not** ship `pockets`. List `TransactionResource` already includes the fields the edit form needs (`account_id`, `booked_at`, `amount`, `type`, `description`, `subject`, `raw_statement_description`, `transfer_id`, `category_id`, `pocket_id`). `import_id` is on `TransactionEditResource` only and is unused in the Vue edit UI.
- Store/update/destroy already redirect via `TransactionsIndexQuery` (remembered filters). Unlink uses `back()` + toast.
- `pageRegistry` still has `transactions.create` and `transactions.edit`. Vitest ancestor/detail examples use those names (accounts create/edit were already removed).
- Telemetry `transaction_create_opened` fires on `Create.vue` `onMounted`.
- Shared dialog chrome (OpenShip) is already in `components/ui/dialog`.

## Target behavior

### URLs and controller

The only P&L **page** is `transactions.index`. Named routes `transactions.create` and `transactions.edit` remain so old bookmarks and isolation tests keep working.

| Request | Owner / allowed | Other user’s transaction |
|---------|-----------------|--------------------------|
| `GET transactions.create` | Redirect `transactions.index` with **remembered filter params** (`TransactionsIndexQuery::params()`) **plus** `new=1` | n/a |
| `GET transactions.edit` | Authorize `update`, then redirect `transactions.index` with remembered filters. **No** `new`, **no** edit id | **403** (authorize before redirect) |
| `POST store` / `PUT update` / `DELETE` | Unchanged: `TransactionsIndexQuery::redirect` + toast | Unchanged 403 |
| `POST transfers.unlink` | Unchanged: `back()` + toast. From the index that is the index | Unchanged 403 |

`new` is **not** added to `TransactionsIndexQuery::ALLOWED_KEYS`. It must never be remembered in session. Index consumes it once and strips it with `router.replace` (keep filter query params).

Guest `GET create` / `GET edit` still hit auth middleware (redirect to login). That does not change.

`create()` and `edit()` no longer `Inertia::render`. Delete `resources/js/pages/transactions/Create.vue` and `Edit.vue`. Delete `TransactionEditResource` (only the edit page used it).

`index` Inertia props: existing payload **plus** `pockets` from `ListPockets` (same as today’s create: active pockets, `PocketResource`).

Validation: FormRequest `back()` returns to the index. Frontend `useForm().post/put` uses `preserveState: true` and `preserveScroll: true` so the modal stays open and field errors show. On success, Inertia follows the redirect, the modal closes, toast from flash.

### Open-create signal

Two paths, one modal:

1. **Already on `transactions/Index`:** no visit. Sidebar (and any in-page CTA) calls a tiny client module (`requestOpenCreateTransaction()`). Index watches it and opens the create dialog, then clears the flag. Opening **resets** the form.
2. **Any other page:** `Link` / visit `transactions.create` → server redirect with `?new=1` (and remembered filters). Index `onMounted` / watcher sees `new=1`, opens create, `router.replace` to the same URL without `new`.

Do not auto-open edit from a URL.

### Chrome / registry

Remove `transactions.create` and `transactions.edit` from `pageRegistry`. Keep `transfers.create` as a child of `transactions`. In `pageChrome.test.ts`: ancestor example → `transfers.create`; detail title-override example → `pockets.edit` (param `pocket`, override title like today’s “Lunch” case). Do not leave dead transaction create/edit nodes.

Index chrome is unchanged: title **Transakcje**, header actions = filters + actions menu.

### Index wiring (no visual restyle)

Header menu “Dodaj transakcję” and both empty-state primary CTAs become **buttons** that open create (same handler as the client signal). “Dodaj transfer” stays a `Link` to `transfers.create` + current search. Import still opens `ImportDialog`.

Pencil on a row with `tx.account` opens the edit dialog with that row. Pencil stays hidden when the account is deleted (read-only), as today.

Trash stays on the row (`DeleteTransactionDialog`). Do not put delete in the form dialog.

### Transaction form modal

One component: `resources/js/components/transactions/modals/TransactionFormDialog.vue`. Modes `create` | `edit`. Default dialog width `max-w-lg` (shared primitive). Body scrolls (`max-h-[90vh]` already on the primitive). Footer: Cancel, Save.

| | Create | Edit |
|---|--------|------|
| Title | `transactions.create.title` | `transactions.edit.title` |
| Description | `transactions.create.description` | Transaction `description` (plain text; fall back to `subject` if description is empty) |
| Fields | Type toggle (income/expense), account, category, pocket, date, amount, subject, description | Same **without** the type toggle. Categories filtered by existing `type` (`income` vs everything else as expense, including `adjustment` — same as today’s Edit page) |
| Prefill | Expense, today (`DD-MM-YYYY`), `account_id` from the index `account_id` filter if set, else first account; first category for that type; pocket none | Selected list row, including `booked_at` (leave the advanced field empty in the UI when it equals `date`, same as today) |
| Extra | — | Compact transfer banner + unlink when `transfer_id` is set. Collapsible raw statement when `raw_statement_description` is non-empty (**collapsed** by default) |
| Advanced | `booked_at` inside an in-dialog `Collapsible` (copy: existing `advancedSection` / `transactions.form.advancedDatesHint`). **Do not** reuse `AdvancedSectionCard` — its bordered card would nest inside the dialog | Same |

Amount: existing `normalizeAmount` + create-only `applyAmountSign`. Empty `booked_at` is sent as `date` on submit (unchanged).

Linked transfer: account and amount disabled; existing `transactions.edit.transfer.accountHint` / `amountHint` on those fields. Banner uses existing transfer copy. Unlink opens the existing `UnlinkTransferDialog` (nested dialog). After confirm: `preserveState: true` (and `only: ['transactions']` if that is enough for the row to refresh) so the **edit modal stays open**; `transfer_id` clears from the row, account/amount unlock, toast. If the row disappeared from the current page, close the edit modal.

Autofocus the first field (type toggle on create, account on edit). Dismiss while `form.processing` is ignored. Dirty close does not confirm.

Posts go to existing `transactions.store` / `transactions.update` with `transactionsIndexSearch` appended, same as today’s pages.

Track `transaction_create_opened` when the create modal **opens** (including `?new=1` and the client signal), not on Index mount.

### Sidebar

`AppSidebar` “Dodaj transakcję”:

- If `usePage().component === 'transactions/Index'`: `Button` (not `Link`) that calls `requestOpenCreateTransaction()`.
- Else: `Link` to `route('transactions.create')` (no need to append index search; remembered filters are applied on the redirect).

### i18n

Reuse field labels, type labels, transfer banner/hints, statement, unlink, create title/description, edit title, toasts.

Remove unused keys after the pages are gone:

- `transactions.create.back`
- `transactions.create.hints.*`
- `transactions.edit.back`
- `transactions.edit.deleteAction`
- `transactions.edit.dangerZone.*`
- `transactions.edit.hints.*` (keep `transactions.edit.transfer.*` and `transactions.edit.statement.*`)

`transactions.edit.description` is unused in the dialog (replaced by the record’s description text) — remove it.

## Data flow

```
GET /transakcje[?filters] → TransactionController::index
                         → accounts, categories, pockets, transactions, …
                         → Index.vue; CTA / sidebar signal / ?new=1 opens TransactionFormDialog

GET /transakcje/utworz   → redirect index + remembered filters + new=1
GET /transakcje/{id}/edytuj → 403 or redirect index (filters only)

POST store / PUT update  → back to index on 422 (modal stays)
                         → redirect index + filters on 200 (modal closes, toast)

POST unlink              → back(); edit modal stays if preserveState kept Index alive
```

## Files

| File | Change |
|------|--------|
| `app/Http/Controllers/Transactions/TransactionController.php` | Index: `ListPockets` + `pockets` prop. `create`/`edit` redirects |
| `app/Http/Resources/Transactions/TransactionEditResource.php` | Delete |
| `resources/js/pages/transactions/Index.vue` | `pockets` prop; modal state; CTA buttons; `?new=1`; client signal |
| `resources/js/pages/transactions/Create.vue` | Delete |
| `resources/js/pages/transactions/Edit.vue` | Delete |
| `resources/js/components/transactions/modals/TransactionFormDialog.vue` | New |
| `resources/js/composables/useTransactionFormDialog.ts` | New — module-level create-open request for the sidebar |
| `resources/js/components/AppSidebar.vue` | Conditional Button vs Link |
| `resources/js/components/transfers/UnlinkTransferDialog.vue` | `preserveState` (and `only`) so Index/modal survive unlink |
| `resources/js/lib/pageRegistry.ts` | Drop create/edit transaction nodes |
| `resources/js/lib/pageChrome.test.ts` | Ancestor → `transfers.create`; drop `transactions.edit` case |
| `resources/js/locales/pl.json`, `en.json` | Remove unused keys listed above |
| `tests/Feature/Transactions/TransactionIndexTest.php` (or equivalent) | `pockets` on index |
| `tests/Feature/Transactions/TransactionCreatePageTest.php` | GET create → redirect index + `new=1` |
| `tests/Feature/Transactions/TransactionEditTest.php` | GET edit → redirect index; bank_icon_url assertion moves to index accounts if still needed |
| `tests/Feature/Transactions/TransactionPocketTest.php` | Pockets asserted on **index**, not create/edit pages |
| `tests/Feature/Transactions/TransactionEditTransferUnlinkTest.php` | Inertia Edit page gone; unlink `from` index (or `back()` to index) |
| `tests/Feature/Transactions/TransactionAuthorizationTest.php` | GET edit still 403 for other user |
| `.docs/checklist.md` | Add/edit from the list; unlink in the edit **modal**; delete UI index-only |
| `.docs/prd.md` §7 | Transakcje = lista + modal P&L; transfer nadal stroną |

Named route URIs stay. Do not add `Services/` or `Data/*Result`. Pockets stay `ListPockets` + `PocketResource`. Form options that are already on the index (`accounts`, `categories`) are reused — do not add a parallel fetch.

## Testing

| Check | Method |
|-------|--------|
| Index includes `pockets` (active, current user) | Pest `TransactionPocketTest` / index test |
| Owner `GET create` → redirect index with `new=1` (and remembered filters when session has them) | Pest (rewrite `TransactionCreatePageTest`) |
| Owner `GET edit` → redirect index, query has no `new` | Pest (rewrite `TransactionEditTest`) |
| Other user `GET edit` → 403 | Existing authorization / isolation test |
| Store/update/destroy redirects and validation | Existing feature tests |
| Unlink from index → `back()` to index, toast, `transfer_id` cleared | `TransactionEditTransferUnlinkTest` / `TransfersUnlinkTest` |
| `pageRegistry` / chrome has no transaction create/edit nodes | Vitest `pageChrome.test.ts` |
| Dialog open, 422, unlink-in-modal, `?new=1` strip | Manual; no Pest assertion that Vue opened a dialog |

Manual smoke: add from header menu, empty state, sidebar while already on Index, sidebar from dashboard; edit from desktop and mobile row; 422 keeps modal; Esc/X/overlay ignored while saving; unlink keeps edit modal and unlocks fields; statement collapsible; delete still on the row; old `/transakcje/utworz` lands on the list with the create modal open once; transfer create page unchanged.

## Out of scope

- Add/Edit modal for **transfers** (and categories / pockets)
- OpenShip restyle of the transactions table, summary cards, empty state, or filters
- Persistent modal URLs (`?edit=id`) or auto-open edit from `GET edit`
- Inertia modal package / keeping Create and Edit as Inertia visit overlays
- Mobile bottom sheet (Vaul)
- Changing Import wizard, delete business rules, or transfer matching
- Changing unlink **domain** rules (only the return path / preserveState so the overlay works)
- Prefetching a transaction that is not on the current page

## Implementation notes (for plan)

1. Put `pockets` on the index before wiring the form dialog.
2. Redirect `create`/`edit` in the same slice as deleting the Vue pages so nothing 500s.
3. `TransactionFormDialog` posts to existing store/update; reuse field UI from today’s Create/Edit (DropdownSelect, CategoryBadge, DatePickerInput, amount helpers).
4. Block dialog dismiss while processing (same pattern as `AccountFormDialog`).
5. Sidebar must not be a `Link` when the current Inertia component is already `transactions/Index`, or C is violated (full round-trip).
6. After PHP: Pint + Sail `--filter=Transactions` (plus unlink tests if they live under `Transfers`). After Vue: Prettier; Vitest page chrome.
7. Before merge: `.docs/checklist.md` §4 (add/edit from the list, unlink location, delete UI). PRD §7 screens: Transakcje = list + P&L modals, transfer still a page.

## Success criteria

- User can add and edit a P&L transaction without leaving `/transakcje`.
- Sidebar add opens the form in one click on the list, and from other pages via `GET create` → `?new=1`.
- Direct visits to create/edit URLs do not 404; edit does not try to open a row that is not on the page.
- Transfer create, Import, and row delete are unchanged in behavior.
- Edit modal has no danger zone; unlink and statement are in the modal; delete remains on the row.
- List look-and-feel is unchanged except CTA elements that used to be links.
