# Categories index Add/Edit modals — design spec

**Date:** 2026-08-26  
**Status:** Approved in brainstorming  
**Scope:** Category Add/Edit as overlays on `/kategorie`. No list restyle. Pocket create/edit stays pages.  
**Depends on:** `.docs/superpowers/specs/2026-08-26-accounts-modals-design.md` (overlay-on-index pattern, shared `ui/dialog` chrome already shipped).  
**Related:** `.cursor/skills/openship-inspired-ui/SKILL.md` (dialog as highest surface)

## Goal

Keep the user on `/kategorie` for creating and editing P&L categories. Delete stays on the row. The categories list is **not** restyled in this spec.

## Decisions log

| Topic | Decision |
|-------|----------|
| Spec slice | Add/Edit overlay only; delete stays on the row |
| Create/edit URL | Overlay on `categories.index` only — no modal query string |
| Open-create signal | No `?new=1` — no sidebar CTA for new category |
| GET edit | Redirect to index, do not auto-open |
| Dirty form | Close discards without a second confirm |
| Close while saving | Overlay, Esc, and X ignored while `form.processing` |
| List restyle | Out of scope |

## Target behavior

### URLs and controller

The only categories **page** is `categories.index`. Named routes `categories.create` and `categories.edit` remain for bookmarks and isolation tests.

| Request | Owner / allowed | Other user’s category |
|---------|-----------------|----------------------|
| `GET categories.create` | Redirect `categories.index` | n/a |
| `GET categories.edit` | Authorize `update`, then redirect `categories.index` | **403** |
| `POST store` / `PATCH update` / `DELETE` | Unchanged: index / back + toast | Unchanged 403 |

`index` Inertia props: existing `categories` plus `icons` and `colors` from `CategoryFormOptions::toArray()`.

`create()` and `edit()` no longer `Inertia::render`. Delete `resources/js/pages/categories/Create.vue` and `Edit.vue`. Drop unused `has_transactions` from the old edit payload.

Validation: FormRequest `back()` returns to the index. Frontend `useForm().post/patch` uses `preserveState: true` and `preserveScroll: true`. On success, modal closes, toast from flash.

### Category form modal

One component: `resources/js/components/categories/modals/CategoryFormDialog.vue`. Modes `create` | `edit`.

| | Create | Edit |
|---|--------|------|
| Title | `categories.create.title` | `categories.edit.title` |
| Description | `categories.create.description` | Category **name** (plain text) |
| Fields | name, type, color, icon + preview | name, color, icon + preview; system note; **no** type |
| Prefill | expense, icon `tag`, color null | Selected list row |
| Footer | Cancel, Save | Cancel, Save |

Client color-required check on create stays. Delete is **not** inside this modal.

### Chrome / i18n

Remove `categories.create` and `categories.edit` from `pageRegistry`. Remove unused `categories.edit.description`.

## Testing

| Check | Method |
|-------|--------|
| Index includes `icons`, `colors` | Pest `CategoryCrudTest` |
| Owner `GET create` / `GET edit` → redirect index | Pest |
| Other user `GET edit` → 403 | `Wave2IsolationTest` |
| Store/update/destroy | Existing feature tests |

## Out of scope

- OpenShip restyle of the categories list
- Pocket Add/Edit modals
- Persistent modal URLs / auto-open edit from GET
- Changing delete or type-change rules

## Success criteria

- User can add and edit a category without leaving `/kategorie`.
- Direct visits to create/edit URLs do not 404; edit does not auto-open.
- Delete remains on the row.
