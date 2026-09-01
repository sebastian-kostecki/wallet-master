# Accounts index OpenShip + Add/Edit modals — design spec

**Date:** 2026-08-26  
**Status:** Approved in brainstorming  
**Scope:** OpenShip restyle of `/konta`, shared `ui/dialog` chrome, account Add/Edit as overlays on the index  
**Depends on:** `.docs/superpowers/specs/2026-08-26-accounts-wallets-tabs-design.md` (tabs, `kind`, create bank prefill). This spec **supersedes** that doc’s Add CTA → `accounts.create` URL: the CTA opens a modal on the index instead.  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md` (surface ladder, empty state, dialog as highest layer)

## Goal

Keep the user on `/konta` for create and edit. Restyle the list to OpenShip. Give every dialog the same chrome by changing the shared primitive. Other domains’ Add/Edit pages stay full pages until a later spec.

## Decisions log

| Topic | Decision |
|-------|----------|
| Spec slice | Modal pattern + `/konta` only; transactions / categories / pockets Add/Edit later |
| Create/edit URL | **A:** Overlay on `accounts.index` only — no modal query string, no Inertia create/edit pages |
| Edit modal body | Form fields only. Delete and adjust stay on the card/row (existing dialogs) |
| List restyle | Full OpenShip on cards, list container, empty state, header CTA. Card **action layout** unchanged |
| Dialog chrome | Change `components/ui/dialog` so **all** existing dialogs inherit it (Import, delete transaction, etc.) |
| Implementation | Overlay on index; index ships `currencies` + `banks` + `accountTypes`; centered dialog on mobile (no sheet) |
| Stale GET URLs | Redirect to `accounts.index` (not 404) |
| Dirty form | Close discards without a second confirm |
| Close while saving | Overlay, Esc, and X ignored while `form.processing` |

## Current state

- `/konta` is tabbed (Rachunki / Portfele). Add is a `Link` to `accounts.create?kind=`. Edit is `router.visit(accounts.edit)`.
- Create and Edit are full Inertia pages (`Create.vue`, `Edit.vue`). Edit also shows current balance, adjust, and a danger zone — those actions already exist on `AccountCard` / `AccountRow`.
- Delete and adjust are already dialogs on the index. After adjust, `AccountBalanceController` redirects to `accounts.edit`.
- `ui/dialog` is stock shadcn: `max-w-lg`, `sm:rounded-lg`, overlay `bg-black/80`. Import overrides width with `sm:max-w-3xl`.
- Index `AccountResource` query does not select `opening_balance`, so the edit form cannot be filled from list props today.
- `transfers/Create.vue` links to `accounts.create` when the user has fewer than two accounts.
- `pageRegistry` still has `accounts.create` and `accounts.edit` child nodes (chrome / breadcrumbs tests).

## Target behavior

### URLs and controller

The only accounts **page** is `accounts.index`. Named resource routes `accounts.create` and `accounts.edit` remain so old bookmarks and isolation tests keep working:

| Request | Owner / allowed | Other user’s account |
|---------|-----------------|----------------------|
| `GET accounts.create` | Redirect `accounts.index`; forward `kind` query if present | n/a |
| `GET accounts.edit` | Authorize `update`, then redirect `accounts.index` with `kind` from that account’s `bank` (same rule as store: cash → `wallets`, else `accounts`) | **403** (authorize before redirect) |
| `POST store` / `PATCH update` / `DELETE` | Unchanged: redirect index + `kind` from saved/deleted account + toast | Unchanged 403 |
| `PATCH accounts.balance.update` | Redirect **index** + `kind` from that account (no longer `accounts.edit`) | Unchanged 403 |

`index` Inertia props:

- `accounts` — same resource as today, but the query **must include `opening_balance`** so the edit modal can prefill.
- `currencies` — same payload as today’s `create()` (id, code, name, symbol, precision).
- `banks`, `accountTypes` — `AccountFormOptions::toArray()` (same as create/edit).

`create()` and `edit()` no longer `Inertia::render`. Delete `resources/js/pages/accounts/Create.vue` and `Edit.vue`.

Validation: FormRequest `back()` returns to the index. Frontend `useForm().post/patch` uses `preserveState: true` and `preserveScroll: true` so the modal stays open and field errors show. On success, Inertia follows the redirect, the modal closes, toast from flash.

### Chrome / registry

Remove `accounts.create` and `accounts.edit` from `pageRegistry` (and the matching `pageChrome.test.ts` cases). Index chrome is unchanged: title **Konta**, tabs, `headerActions` CTA.

### Index list (OpenShip)

Header CTA is a **Button**, not a `Link`. Click opens the create modal. Label still follows the tab (`accounts.index.addAccount` / `addWallet`). Empty-state CTA does the same.

**Empty state (per tab):** no bordered card on the grey floor. Centered heading `text-2xl font-medium`, description `text-sm text-muted-foreground max-w-sm`, one primary button. Existing i18n keys. No illustration.

**Grid / list toggle:** unchanged behavior; only when the current tab has rows.

**`AccountCard`:** same structure (bank icon + name + type, trash top-right, balance, Edytuj / Ustaw saldo). Surface: `rounded-2xl border-transparent bg-card`, no resting shadow. The card is not a single click target — only the buttons. Edytuj opens the edit modal. Bank logos and the amber cash well stay.

**`AccountRow`:** same actions (edit, adjust, delete). One lifted container (`rounded-2xl bg-card`) wrapping all rows; row hover `hover:bg-foreground/[0.03]`; separators `border-border/50`. Mobile: balance still under the name.

After a successful write, the list shows the tab for the **saved** `bank` (existing redirect).

### Shared dialog chrome

Change `DialogContent.vue` and `DialogScrollContent.vue` (not a parallel wrapper):

- Overlay: `bg-black/40`
- Panel: `bg-card`, `rounded-2xl`, hairline `border-border`, real shadow (highest surface)
- Default width `max-w-lg`; callers may override (`ImportDialog` keeps `sm:max-w-3xl`)
- `p-6`; `max-h-[90vh]` with scrollable body so the footer stays reachable on small screens
- Header: title `font-semibold tracking-tight`; description `text-sm text-muted-foreground`; left-aligned from `sm:`
- Footer: Cancel (secondary/outline) then primary; destructive stays red
- Close: X, Esc, overlay click (Radix defaults)
- Motion: keep current fade/zoom, `duration-200`

Dismiss while saving is **not** a primitive flag. `AccountFormDialog` and `AdjustAccountBalanceDialog` ignore `open → false` while `form.processing`. Other dialogs keep current close behavior.

All current dialogs inherit this: account delete/adjust, Import, transaction/category delete, unlink transfer, pocket archive, delete-user. **No content or flow changes** except accounts Add/Edit (new) and the balance redirect above. Inner nested boxes in account delete/adjust: `rounded-xl` instead of `rounded-lg`.

No wizard variant. No Vaul/sheet.

### Account form modal

One component: `resources/js/components/accounts/modals/AccountFormDialog.vue`. Modes `create` | `edit`.

| | Create | Edit |
|---|--------|------|
| Title | `accounts.create.title` or `titleWallet` from active tab `kind` | `accounts.edit.title` (“Edytuj konto”) |
| Description | `accounts.create.description` / `descriptionWallet` | Account **name** (plain text, not a new i18n key) |
| Fields | name, bank, type, currency, opening balance | name, bank, type, opening balance + `accounts.edit.openingBalanceHint`. **No** currency field (unchanged from today’s edit page) |
| Prefill | `bank` from tab `kind` (wallets → `cash`; otherwise first non-cash `Bank`), same helper as today’s Create page; user may change bank | Selected account, including `opening_balance` |
| Footer | Cancel, Save | Cancel, Save |

Title/description follow the **tab that opened** create (`kind` in the URL), not the live `form.bank`. Autofocus name. Opening the modal resets the form. Closing a dirty form does not ask for confirmation.

Entry points: header CTA and empty CTA → create; card/row Edytuj → edit. Delete and adjust are **not** inside this modal.

### Other entry points

`transfers/Create.vue`: the “add account” link goes to `accounts.index` (Rachunki, default `kind`). The create modal does **not** open by itself.

### i18n

Reuse create/edit field labels, tab-specific create titles, adjust, and delete strings.

Remove unused keys after the edit page is gone:

- `accounts.create.back`
- `accounts.edit.back`
- `accounts.edit.description` (replaced by the account name in the dialog)
- `accounts.edit.deleteAction`
- `accounts.edit.dangerZone.title`
- `accounts.edit.dangerZone.description`

No new keys unless copy review finds a gap; none required for the title/description pattern above.

## Data flow

```
GET /konta[?kind=] → AccountController::index
                  → accounts (+ opening_balance) + currencies + banks + accountTypes
                  → Index.vue filters by kind; CTA opens AccountFormDialog

POST store / PATCH update → back to index on 422 (modal stays)
                         → redirect index?kind= from saved bank on 200 (modal closes, toast)

GET /konta/utworz → redirect index (optional kind)
GET /konta/{id}/edytuj → 403 or redirect index?kind=
PATCH saldo → redirect index?kind=
```

## Files

| File | Change |
|------|--------|
| `app/Http/Controllers/Accounts/AccountController.php` | Index props; `create`/`edit` redirects |
| `app/Http/Controllers/Accounts/AccountBalanceController.php` | Redirect to index + `kind` |
| `resources/js/pages/accounts/Index.vue` | Options props; modal state; Button CTA; OpenShip empty |
| `resources/js/pages/accounts/Create.vue` | Delete |
| `resources/js/pages/accounts/Edit.vue` | Delete |
| `resources/js/components/accounts/modals/AccountFormDialog.vue` | New |
| `resources/js/components/accounts/AccountCard.vue` | OpenShip surface; `@edit` still emitted |
| `resources/js/components/accounts/AccountRow.vue` | List-in-one-card; same actions |
| `resources/js/components/accounts/modals/DeleteAccountDialog.vue` | Inner `rounded-xl` |
| `resources/js/components/accounts/modals/AdjustAccountBalanceDialog.vue` | Inner `rounded-xl`; processing blocks dismiss |
| `resources/js/components/ui/dialog/DialogContent.vue` | Shared chrome |
| `resources/js/components/ui/dialog/DialogScrollContent.vue` | Same chrome |
| `resources/js/lib/pageRegistry.ts` | Drop create/edit nodes |
| `resources/js/lib/pageChrome.test.ts` | Drop create/edit cases |
| `resources/js/pages/transfers/Create.vue` | Link to `accounts.index` |
| `resources/js/locales/pl.json`, `en.json` | Remove unused keys listed above |
| `tests/Feature/Accounts/AccountIndexTest.php` | `opening_balance`; form option props |
| `tests/Feature/Accounts/` (new or existing) | GET create/edit redirects; owner vs 403 |
| `tests/Feature/Accounts/AdjustAccountBalanceTest.php` | Redirect to index + `kind` |

Named route URIs stay (`accounts.create`, `accounts.edit` still resolve). Do not add `Services/` or `Data/*Result`. Form options stay `AccountFormOptions`; currencies stay a query on the controller (same as today’s create).

## Testing

| Check | Method |
|-------|--------|
| Index includes `banks`, `accountTypes`, `currencies`, and `opening_balance` on accounts | Pest `AccountIndexTest` |
| Owner `GET create` → redirect index (preserves `kind` if sent) | Pest |
| Owner `GET edit` → redirect index with `kind` from that account’s bank | Pest |
| Other user `GET edit` → 403 | Existing `AccountIsolationTest` (still GET edit) |
| Store/update/destroy redirects and validation | Existing feature tests |
| Adjust balance → redirect index + `kind` | `AdjustAccountBalanceTest` |
| `pageRegistry` / chrome has no create/edit nodes | Vitest `pageChrome.test.ts` |
| Dialog primitive classes (radius, overlay) | Manual; optional snapshot not required |

No Pest assertion that Vue opened a dialog (client-only). Manual smoke: add from each tab; edit from card and row; 422 keeps modal; Esc does not close while saving; delete/adjust/Import still open and look like the new chrome; old `/konta/utworz` lands on the list.

## Out of scope

- Add/Edit modals for transactions, transfers, categories, pockets
- Query-string or path URLs that represent an open modal
- Inertia modal package / keeping Create and Edit as Inertia visit overlays
- Mobile bottom sheet (Vaul)
- Changing Import wizard steps or copy
- Changing delete/adjust **business** rules
- Making the whole account card clickable
- Per-tab summary metrics
- Opening the create modal automatically from `transfers/Create`

## Implementation notes (for plan)

1. Put `opening_balance` on the index query before wiring the edit modal.
2. Restyle `ui/dialog` in the same PR as accounts so chrome and new form dialog ship together; Import width override must still win.
3. Share the `kind` from `bank` helper with `AccountBalanceController` (extract a tiny method or duplicate the one-liner already on `AccountController` — do not add a new Action).
4. `AccountFormDialog` posts to existing `accounts.store` / `accounts.update`; reuse field UI from today’s Create/Edit (DropdownSelect, FormField, amount normalize).
5. Block dialog dismiss while processing in the form dialog and in adjust (delete already short-lived).
6. After PHP: Pint + Sail `--filter=Accounts` (and isolation test if not covered by that filter). After Vue: Prettier; Vitest page chrome.
7. Before merge: `.docs/checklist.md` §3 (add/edit from the list). PRD §8 screens: Konta = list + modals, not separate add/edit pages.

## Success criteria

- User can add and edit an account without leaving `/konta`.
- Direct visits to create/edit URLs do not 404.
- List and empty state match OpenShip (lifted cards, no boxed empty region).
- Every dialog in the app uses the new chrome; Import is still wider.
- Edit modal has no delete/adjust; those remain on the row/card.
