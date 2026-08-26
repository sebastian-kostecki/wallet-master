# Categories Index Modals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep add/edit on `/kategorie` as dialogs; delete stays on the list.

**Architecture:** `categories.index` ships the category list plus form options (`icons`, `colors`). Vue opens `CategoryFormDialog` with no extra URL. GET `create`/`edit` redirect to the index. Shared dialog chrome is already shipped.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Vue 3, Ziggy, vue-i18n, Pest 4, Tailwind v3, shadcn-vue Dialog, Sail.

**Spec:** `.docs/superpowers/specs/2026-08-26-categories-modals-design.md`  
**Suggested branch:** `improvement/categories-index-modals`

## Global Constraints

- Variant A: no `app/Services/`, no `Data/*Result`. Reuse `CategoryFormOptions` and existing store/update Actions.
- Overlay state is Vue-only on `categories.index`. No `?new=1`.
- Named routes `categories.create` and `categories.edit` stay (redirects + isolation).
- Edit modal is the form only. Delete stays on the row.
- After PHP: Pint + Sail `--filter=Categor`. After Vue: Prettier.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `app/Http/Controllers/Categories/CategoryController.php` | Index props; `create`/`edit` redirects |
| Modify | `tests/Feature/Categories/CategoryCrudTest.php` | Icons/colors on index; GET redirects |
| Create | `resources/js/components/categories/modals/CategoryFormDialog.vue` | Create/edit overlay |
| Modify | `resources/js/pages/categories/Index.vue` | Options, modal state, Button CTAs |
| Delete | `resources/js/pages/categories/Create.vue` | Replaced by the dialog |
| Delete | `resources/js/pages/categories/Edit.vue` | Replaced by the dialog |
| Modify | `resources/js/lib/pageRegistry.ts` | Drop create/edit nodes |
| Modify | `resources/js/locales/pl.json`, `en.json` | Remove `categories.edit.description` |
| Modify | `.docs/checklist.md`, `.docs/prd.md` | Record list + modals |

---

### Task 1: Index ships form options; create/edit redirect

- [x] Extend index Inertia props with `CategoryFormOptions`
- [x] `create()` / `edit()` return `RedirectResponse` to `categories.index`
- [x] Update `CategoryCrudTest`; keep isolation 403 on foreign `GET edit`
- [x] Pint + Sail tests

### Task 2: CategoryFormDialog + Index wiring

- [x] Create `CategoryFormDialog` (create/edit, preserveState, block dismiss while processing)
- [x] Wire Index CTAs (header add, row click, pencil)
- [x] Delete `Create.vue` / `Edit.vue`
- [x] Prettier on touched Vue files

### Task 3: Chrome, i18n, docs

- [x] Drop `categories.create` / `categories.edit` from `pageRegistry`
- [x] Remove `categories.edit.description`
- [x] Checklist §18 + PRD §7
- [x] Spec + this plan under `.docs/superpowers/`
