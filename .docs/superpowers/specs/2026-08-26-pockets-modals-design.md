# Pockets index Add/Edit modals — design spec

**Date:** 2026-08-26  
**Status:** Approved in brainstorming  
**Scope:** Pocket Add/Edit as overlays on `/kieszenie`. No list restyle. Delete stays `window.confirm` on the row.  
**Depends on:** `.docs/superpowers/specs/2026-08-26-categories-modals-design.md` (same overlay-on-index contract); accounts/transactions modals for shared `ui/dialog` chrome.  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md` (dialog as highest surface)

## Goal

Keep the user on `/kieszenie` for creating and editing savings pockets. Delete stays on the row. The pockets list is **not** restyled in this spec.

## Decisions log

| Topic | Decision |
|-------|----------|
| Spec slice | Add/Edit overlay only; delete stays `window.confirm` on the row (no new delete dialog) |
| Create/edit URL | Overlay on `pockets.index` only — no modal query string |
| Open-create signal | No `?new=1` — no sidebar CTA for new pocket |
| GET edit | Redirect to index, do not auto-open |
| Dirty form | Close discards without a second confirm |
| Close while saving | Overlay, Esc, and X ignored while `form.processing` |
| Dialog width | Default dialog width (`max-w-lg`); form scrolls inside dialog (longer than categories) |
| List restyle | Out of scope |
| Edit payload | Prefill from the selected index row (`PocketResource` already includes edit fields + `currency` via `ListPockets`) |

## Target behavior

### URLs and controller

The only pockets **page** is `pockets.index`. Named routes `pockets.create` and `pockets.edit` remain for bookmarks and isolation tests.

| Request | Owner / allowed | Other user’s pocket |
|---------|-----------------|---------------------|
| `GET pockets.create` | Redirect `pockets.index` | n/a |
| `GET pockets.edit` | Authorize `update`, then redirect `pockets.index` | **403** |
| `POST store` / `PATCH update` / `DELETE` | Unchanged: index / back + toast | Unchanged 403 |

`index` Inertia props: existing `pockets` plus `icons`, `colors`, and `currencies` from `PocketFormOptions::toArray()`.

`create()` and `edit()` no longer `Inertia::render`. Delete `resources/js/pages/pockets/Create.vue` and `Edit.vue`.

Validation: FormRequest `back()` returns to the index. Frontend `useForm().post/patch` uses `preserveState: true` and `preserveScroll: true`. On success, modal closes, toast from flash.

### Pocket form modal

One component: `resources/js/components/pockets/modals/PocketFormDialog.vue`. Modes `create` | `edit`.

| | Create | Edit |
|---|--------|------|
| Title | `pockets.create.title` | `pockets.edit.title` |
| Description | `pockets.create.description` | Pocket **name** (plain text) |
| Fields | Same as today’s Create page: name, currency, initial balance, target, monthly contribution, color, icon, planning block when target &gt; 0, preview | Same as today’s Edit page: name; currency + initial balance **read-only**; target / planning / color / icon; recommended monthly + projected completion (from row) |
| Prefill | icon `flag`, color null, first currency, empty amounts, `planning_mode: monthly` | Selected list row |
| Footer | Cancel, Save | Cancel, Save |

Client color-required check on create stays. Planning nulling rules (no target → clear planning fields; monthly vs by_date) stay as on the pages. Delete is **not** inside this modal.

Index wiring: header CTA → create; pencil → edit. No row-click-to-edit requirement beyond matching current Index affordances (today only the pencil navigates to edit).

### Chrome / i18n / docs

- Remove `pockets.create` and `pockets.edit` from `pageRegistry`.
- Update `pageChrome` tests that still assert those nodes as ancestors / detail examples.
- Remove unused `pockets.edit.description` from `pl.json` / `en.json` (edit modal description is the pocket name). Keep `recommendedMonthly` / `projectedCompletionDate`.
- Checklist / PRD: pockets Add/Edit in modal (same wording style as categories).

## Testing

| Check | Method |
|-------|--------|
| Index includes `icons`, `colors`, `currencies` | Pest `PocketCrudTest` (or focused index assertion) |
| Owner `GET create` / `GET edit` → redirect index | Pest |
| Other user `GET edit` → 403 | `Wave2IsolationTest` / `PocketAuthorizationTest` |
| Store/update/destroy / planning | Existing feature tests |

No Pest assertion that Vue opened the dialog.

## Out of scope

- OpenShip restyle of the pockets list / empty state
- Replacing `window.confirm` delete with a dialog
- Persistent modal URLs / auto-open edit from GET
- Changing currency after create, initial-balance mutability, or planning validation rules
- Archive/unarchive UI (already removed from product)

## Success criteria

- User can add and edit a pocket without leaving `/kieszenie`.
- Direct visits to create/edit URLs do not 404; edit does not auto-open.
- Delete remains on the row via existing confirm.
