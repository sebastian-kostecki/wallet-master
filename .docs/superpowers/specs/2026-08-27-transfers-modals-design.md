# Transfer create modal on transactions index — design spec

**Date:** 2026-08-27  
**Status:** Approved in brainstorming  
**Scope:** Transfer **create** as an overlay on `/transakcje`. No transfer-pair edit. No list restyle.  
**Depends on:** `.docs/superpowers/specs/2026-08-26-transactions-modals-design.md` (overlay-on-index, `?new=1` for P&L, shared `ui/dialog`, index already ships `accounts` + `pockets`).  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md` (dialog as highest surface)

## Goal

Keep the user on `/transakcje` when creating an internal transfer (one action → two linked transactions). Editing a transfer **leg** stays in `TransactionFormDialog` (locked amount/account + unlink). Import, P&L add/edit, row delete, and the list look-and-feel are unchanged.

## Decisions log

| Topic | Decision |
|-------|----------|
| Spec slice | **A:** Create overlay only. No “edit pair as one entity” |
| Open-create signal | **A:** Already on Index → open locally. `GET transfers.create` → redirect index + remembered filters + one-shot `transfer=1` |
| Query param | `transfer=1`, never `new=1` (`new` stays P&L-only) |
| Empty accounts (&lt; 2 active) | **A:** Empty state **inside** the modal + link to `accounts.index` |
| Prefill | **A:** From = index `account_id` filter when that account is active, else first active. To = first **other** active account |
| Hints column | Drop (same as P&L modal). Dialog description = `transfers.create.description` |
| Sidebar / empty-state CTA | No new transfer buttons. Header menu only + `GET create` |
| Client composable | **None** — there is no off-index CTA that must open locally |
| Dirty form | Close discards without a second confirm |
| Close while saving | Overlay, Esc, and X ignored while `form.processing` |
| Mutual exclusion | P&L form dialog and transfer dialog are never both open |

## Current state

- Transfer create is a full Inertia page (`resources/js/pages/transfers/Create.vue`) at `GET transfers.create` (`/transfery/utworz`). Two-column layout: form + hint cards. Empty state when fewer than two **active** accounts (soft-deleted excluded from selects).
- Header menu “Dodaj transfer” is a `Link` to `transfers.create` + current index search. Empty states on the list do **not** offer transfer. Sidebar has no transfer CTA.
- `POST transfers.store` already redirects via `TransactionsIndexQuery` + toast `transfers.toast.created`. Validation `back()` already returns to the previous page (today: the create page).
- `transactions/Index` already ships `accounts` (active only, with `bank` / `type` / `bank_icon_url`) and `pockets` (`ListPockets` + `PocketResource`). No extra index props are required.
- `pageRegistry` still has `transfers.create` as a child of `transactions`. `pageChrome.test.ts` uses that node as the ancestor/crumb example.
- Telemetry: `transfer_created` on store success; `transfer_failed_validation` from `StoreTransferRequest`. There is no `transfer_create_opened` event.
- Named route, URI, and `LegacyRouteRedirector` mapping `transfers/create` → `transfers.create` stay.

## Target behavior

### URLs and controller

The only **page** is `transactions.index`. Named route `transfers.create` remains so bookmarks, isolation tests, and the legacy English path keep working.

| Request | Behavior |
|---------|----------|
| `GET transfers.create` (auth) | Redirect `transactions.index` with **remembered filter params** (`TransactionsIndexQuery::params()`) **plus** `transfer=1` |
| `GET transfers.create` (guest) | Unchanged: auth middleware → login |
| `POST transfers.store` | Unchanged: `TransactionsIndexQuery::redirect` + toast |
| `POST unlink` / candidates | Unchanged |

`transfer` is **not** added to `TransactionsIndexQuery::ALLOWED_KEYS`. It must never be remembered in session. Index consumes it once and strips it with `router.replace` (keep filter query params).

`new` stays P&L-only. If a URL has both `new=1` and `transfer=1` (malformed bookmark), open the **transfer** dialog and strip **both** keys.

`TransferController::create` no longer `Inertia::render` and no longer loads accounts or pockets. Delete `resources/js/pages/transfers/Create.vue`.

Validation: FormRequest `back()` returns to the index (the POST origin). Frontend `useForm().post` uses `preserveState: true` and `preserveScroll: true` so the modal stays open and field errors show. On success, Inertia follows the redirect, the modal closes, toast from flash.

### Open-create signal

Two paths, one modal:

1. **Already on `transactions/Index`:** header menu item is a **button** (not `Link`). It opens the transfer dialog locally and resets the form. No Inertia visit.
2. **Any other page / bookmark:** visit `GET transfers.create` → server redirect with `?transfer=1` (and remembered filters). Index `onMounted` / URL watcher sees `transfer=1`, opens the transfer dialog, `router.replace` to the same URL without `transfer`.

Do not add `useTransferFormDialog` / `requestOpenCreateTransfer()`. P&L still uses `requestOpenCreateTransaction()` for the sidebar.

### Chrome / registry

Remove `transfers.create` from `pageRegistry`. Index chrome is unchanged: title **Transakcje**, header = filters + actions menu.

In `pageChrome.test.ts`, the ancestor/crumb example that currently uses `transfers.create` moves to `settings.profile` (settings → profile: remaining parent/child). Do not leave a dead `transfers.create` node.

### Index wiring

Header menu “Dodaj transfer” becomes a button that opens `TransferFormDialog` (same handler as consuming `?transfer=1`). “Dodaj transakcję” and Import are unchanged.

Opening the transfer dialog **closes** the P&L form dialog if it was open (dirty discard, no confirm). Opening P&L create/edit **closes** the transfer dialog the same way. Opening transfer while it is already open **resets** the form (same `formResetKey` idea as P&L create).

Empty-state CTAs stay P&L (+ import on first-use). No transfer button there.

### Transfer form modal

One component: `resources/js/components/transfers/modals/TransferFormDialog.vue`. Create only. Default dialog width `max-w-lg` (shared primitive). Body scrolls (`max-h-[90vh]` already on the primitive). Footer: Cancel, Make transfer (`transfers.form.submit` / `saving`).

| | Behavior |
|---|----------|
| Title | `transfers.create.title` |
| Description | `transfers.create.description` |
| &lt; 2 active accounts | Empty copy `transfers.create.empty` + primary `Link` to `accounts.index` labeled `accounts.index.addAccount`. No form fields |
| Fields (when ≥ 2 active) | From \| to, pocket, Odłożenie/Wypłata when a pocket is selected, amount \| date, subject, description |
| Prefill | Only when the form is shown. From = `filters.account_id` when that account is in the **active** list, else first active. To = first active whose id ≠ from. Date = today (`DD-MM-YYYY`). Amount `0,00`. Pocket none; `pocket_movement` null until a pocket is chosen, then `suggestedPocketMovement` (existing helper; watch accounts like today’s page) |
| Same-account | Inline error `transfers.form.sameAccountError`; submit disabled |
| Account selects | Active accounts only; bank / cash icon leading slots as on today’s page |
| Advanced / hints | None. Do not reuse `AdvancedSectionCard`. Drop the create-page hint cards (`transfers.form.hint` unused after — remove the key) |

Autofocus the from-account control, or the “Add account” button in the empty state. Dismiss while `form.processing` is ignored. Dirty close does not confirm. Cancel **closes the dialog** (not a `Link` to the index).

Post to existing `transfers.store` with `transactionsIndexSearch` appended, same as today’s page (`normalizeAmount`; omit `pocket_movement` when `pocket_id` is null).

No new telemetry on open. Keep `transfer_created` / `transfer_failed_validation`.

### i18n

Reuse field labels, empty copy, create title/description, submit/saving, same-account error, pocket copy, toast.

Remove after the page is gone:

- `transfers.form.hint` (hint-column only)

Do not add deleted accounts to the modal selects. Leave already-unused `transfers.form.deletedNotice` / `deletedSuffix` alone (unrelated cleanup).

## Data flow

```
GET /transakcje[?filters] → TransactionController::index
                         → accounts, pockets, … (unchanged)
                         → Index.vue; menu / ?transfer=1 opens TransferFormDialog

GET /transfery/utworz    → redirect index + remembered filters + transfer=1

POST store               → back to index on 422 (modal stays)
                         → redirect index + filters on 200 (modal closes, toast)
```

## Files

| File | Change |
|------|--------|
| `app/Http/Controllers/Transfers/TransferController.php` | `create()` redirect only |
| `resources/js/pages/transfers/Create.vue` | Delete |
| `resources/js/components/transfers/modals/TransferFormDialog.vue` | New |
| `resources/js/pages/transactions/Index.vue` | Transfer dialog state; menu button; `?transfer=1`; mutual exclusion with P&L dialog |
| `resources/js/lib/pageRegistry.ts` | Drop `transfers.create` |
| `resources/js/lib/pageChrome.test.ts` | Ancestor example → `settings.profile` |
| `resources/js/locales/pl.json`, `en.json` | Remove unused keys listed above |
| `tests/Feature/Transfers/TransferCreatePageTest.php` | GET create → redirect index + `transfer=1`; drop Inertia page assertions |
| `tests/Feature/Transfers/TransferPocketTest.php` | Remove “create page includes pockets” (already covered on index by `TransactionPocketTest`) |
| `.docs/checklist.md` | §5 UI: create from the list as a modal |
| `.docs/prd.md` §7 screens | Transakcje = list + P&L **and transfer** modals |

Named route URIs stay. Do not add `Services/` or `Data/*Result`. Do not add pockets/accounts to index again. Do not change `CreateTransfer` / `StoreTransferRequest`.

## Testing

| Check | Method |
|-------|--------|
| Owner `GET create` → redirect index with `transfer=1` (and remembered filters when session has them) | Pest rewrite `TransferCreatePageTest` |
| Guest `GET create` → login | Existing |
| Store / validation / pocket / currency / unlink | Existing feature tests under `Transfers/` |
| Index still includes `pockets` | Existing `TransactionPocketTest` — do not duplicate |
| `pageRegistry` / chrome has no `transfers.create` | Vitest `pageChrome.test.ts` |
| Dialog open, 422, `?transfer=1` strip, empty &lt; 2 accounts, prefill, mutual exclusion with P&L | Manual; no Pest assertion that Vue opened a dialog |

Manual smoke: add transfer from the header menu; bookmark `/transfery/utworz` lands on the list with the transfer modal open once; `transfer` gone from the URL after open; `?new=1` still opens P&L only; 422 keeps the transfer modal; Esc/X/overlay ignored while saving; fewer than two accounts shows empty + Add account; account filter prefills from and a different to; success returns to the list with toast; Import and P&L edit/unlink unchanged.

## Out of scope

- Edit of a transfer **pair** as one form (legs stay in `TransactionFormDialog`)
- Transfer CTA in the list empty state or sidebar
- OpenShip restyle of the transactions table, summary, or filters
- Persistent modal URLs (`?edit=`) or Inertia modal packages
- Mobile bottom sheet (Vaul)
- Changing store, unlink, matcher, or candidate banner behavior
- `withTrashed` on index accounts (modal only needs active accounts)
- New telemetry event when the create modal opens

## Implementation notes (for plan)

1. Redirect `create()` in the same slice as deleting `Create.vue` so nothing 500s.
2. Reuse field UI from today’s page (`DropdownSelect`, bank/cash leading slots, `DatePickerInput`, `suggestedPocketMovement`, `normalizeAmount`).
3. Block dialog dismiss while processing (same pattern as `TransactionFormDialog` / `AccountFormDialog`).
4. Consume `transfer=1` next to existing `new=1` handling; do not treat `transfer` as a remembered filter.
5. After PHP: Pint + Sail `--filter=Transfers` (and `TransactionPocketTest` if touched). After Vue: Prettier; Vitest page chrome.
6. Before merge: `.docs/checklist.md` §5; PRD §7 screens: transfer create is a modal on the transactions list.

## Success criteria

- User can create a transfer without leaving `/transakcje`.
- Direct visit to `GET transfers.create` does not 404; it opens the create modal once via `?transfer=1`.
- P&L `?new=1`, Import, unlink, and row delete are unchanged.
- Fewer than two active accounts: modal explains and links to accounts; form is hidden.
- List look-and-feel is unchanged except the transfer menu item (button instead of `Link`).
