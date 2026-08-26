# Pockets Index Modals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep add/edit on `/kieszenie` as dialogs; delete stays `window.confirm` on the list.

**Architecture:** `pockets.index` ships the pocket list plus form options (`icons`, `colors`, `currencies`). Vue opens `PocketFormDialog` with no extra URL. GET `create`/`edit` redirect to the index. Shared dialog chrome is already shipped. Mirror [categories modals](.docs/superpowers/plans/2026-08-26-categories-modals.md).

**Tech Stack:** Laravel 13, PHP 8.5, Inertia Vue 3, Ziggy, vue-i18n, Pest 4, Tailwind v3, shadcn-vue Dialog, Sail.

**Spec:** `.docs/superpowers/specs/2026-08-26-pockets-modals-design.md`  
**Suggested branch:** `improvement/pockets-index-modals`

## Global Constraints

- Variant A: no `app/Services/`, no `Data/*Result`. Reuse `PocketFormOptions` and existing store/update Actions.
- Overlay state is Vue-only on `pockets.index`. No `?new=1`.
- Named routes `pockets.create` and `pockets.edit` stay (redirects + isolation 403).
- Edit modal is the form only. Delete stays on the row (`window.confirm`).
- Do **not** edit the Cursor plan file under `~/.cursor/plans/` if present; only project docs under `.docs/`.
- After PHP: `vendor/bin/pint --dirty --format agent` + `./vendor/bin/sail artisan test --compact --filter=Pocket` (and confirm `Wave2IsolationTest` still 403 on foreign edit). After Vue: Prettier; Vitest for `pageChrome.test.ts`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `app/Http/Controllers/Pockets/PocketController.php` | Index props; `create`/`edit` redirects |
| Modify | `tests/Feature/Pockets/PocketCrudTest.php` | Options on index; GET redirects |
| Create | `resources/js/components/pockets/modals/PocketFormDialog.vue` | Create/edit overlay |
| Modify | `resources/js/pages/pockets/Index.vue` | Options, modal state, Button CTAs |
| Delete | `resources/js/pages/pockets/Create.vue` | Replaced by the dialog |
| Delete | `resources/js/pages/pockets/Edit.vue` | Replaced by the dialog |
| Modify | `resources/js/lib/pageRegistry.ts` | Drop create/edit nodes |
| Modify | `resources/js/lib/pageChrome.test.ts` | Replace `pockets.edit` detail example |
| Modify | `resources/js/locales/pl.json`, `en.json` | Remove `pockets.edit.description` |
| Modify | `.docs/checklist.md`, `.docs/prd.md` | Record list + modals |

---

### Task 1: Index ships form options; create/edit redirect

**Files:**
- Modify: `app/Http/Controllers/Pockets/PocketController.php`
- Modify: `tests/Feature/Pockets/PocketCrudTest.php`
- Verify: `tests/Feature/Authorization/Wave2IsolationTest.php` (foreign `GET pockets.edit` → 403)

**Interfaces:**
- Consumes: `PocketFormOptions::toArray()` → `icons`, `colors`, `currencies`
- Produces: Index Inertia props include those three keys; `create()` / `edit()` return `RedirectResponse` to `pockets.index`

- [x] **Step 1: Write the failing tests**

Append to `tests/Feature/Pockets/PocketCrudTest.php`:

```php
test('pockets index includes form options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pockets.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('icons')
            ->has('colors')
            ->has('currencies')
        );
});

test('pocket create redirects to index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pockets.create', absolute: false))
        ->assertRedirect(route('pockets.index', absolute: false));
});

test('pocket edit redirects to index', function () {
    $user = User::factory()->create();
    $pocket = Pocket::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('pockets.edit', $pocket, absolute: false))
        ->assertRedirect(route('pockets.index', absolute: false));
});
```

Also extend the existing `pockets index balance includes initial balance` assertion (or leave it) — new dedicated test above is enough.

- [x] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail artisan test --compact --filter="pockets index includes form options|pocket create redirects|pocket edit redirects"`

Expected: FAIL — create/edit still render Inertia pages; index missing options.

- [x] **Step 3: Update `PocketController`**

```php
use Illuminate\Http\RedirectResponse;
// ...

public function index(Request $request, ListPockets $listPockets, PocketFormOptions $options): Response
{
    $listPockets->handle($request->user());

    return Inertia::render('pockets/Index', [
        'pockets' => PocketResource::collection($listPockets->getPockets())->resolve(),
        ...$options->toArray(),
    ]);
}

public function create(): RedirectResponse
{
    return to_route('pockets.index');
}

public function edit(Pocket $pocket): RedirectResponse
{
    return to_route('pockets.index');
}
```

Remove `Inertia::render` bodies from `create`/`edit`. Keep `authorizeResource` — foreign edit still 403 before redirect.

- [x] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/sail artisan test --compact --filter=Pocket`

Also: `./vendor/bin/sail artisan test --compact --filter=Wave2Isolation`

Expected: PASS (foreign edit still 403).

- [x] **Step 5: Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit** (only if the user asked for commits)

```bash
git add app/Http/Controllers/Pockets/PocketController.php tests/Feature/Pockets/PocketCrudTest.php
git commit -m "$(cat <<'EOF'
feat(pockets): ship form options on index; redirect create/edit

EOF
)"
```

---

### Task 2: PocketFormDialog + Index wiring

**Files:**
- Create: `resources/js/components/pockets/modals/PocketFormDialog.vue`
- Modify: `resources/js/pages/pockets/Index.vue`
- Delete: `resources/js/pages/pockets/Create.vue`
- Delete: `resources/js/pages/pockets/Edit.vue`

**Interfaces:**
- Consumes: Index props `icons`, `colors`, `currencies`, `pockets[]` (full `PocketResource` fields already present via `ListPockets` + `with('currency')`)
- Produces: `PocketFormDialog` props `{ open, mode: 'create'|'edit', pocket, icons, colors, currencies }`; emit `update:open`

- [x] **Step 1: Create `PocketFormDialog.vue`**

Port fields from current `Create.vue` / `Edit.vue` into the same chrome as `CategoryFormDialog.vue`:

- `Dialog` / `DialogContent` / header / footer
- `onOpenChange`: ignore close while `form.processing`
- `watch(() => props.open)` → `resetForm()`
- Submit: `preserveScroll: true`, `preserveState: true`, `onSuccess` → close
- Create: client color required (`categories.validation.colorRequired`); `form.post(route('pockets.store'), visit)`
- Edit: `form.patch(route('pockets.update', pocket.id), visit)` — do **not** send `currency_id` / `initial_balance`
- Planning nulling: copy exact logic from Create/Edit `submit()`
- Description: create → `pockets.create.description`; edit → pocket name
- Prefill create: `icon: 'flag'`, `color: null`, `currency_id: currencies[0]?.id ?? null`, empty amounts, `planning_mode: 'monthly'`
- Prefill edit: from `props.pocket`
- Edit-only UI: disabled currency input; initial-balance info block when `> 0`; recommended monthly + projected completion from row
- Create-only UI: currency select, editable initial balance, monthly contribution field placement as on Create page

Skeleton (fill template body from Create/Edit):

```vue
<script setup lang="ts">
import CategoryColorPicker from '@/components/categories/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/categories/CategoryIconPicker.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import SegmentedControl, { type SegmentedControlOption } from '@/components/forms/SegmentedControl.vue';
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/formatMoney';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

// types: IconOption, ColorOption, Currency, Pocket (edit fields from Edit.vue)

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    pocket: Pocket | null;
    icons: IconOption[];
    colors: ColorOption[];
    currencies: Currency[];
}>();

const emit = defineEmits<{ 'update:open': [open: boolean] }>();

// useForm fields union of create+edit; resetForm(); onOpenChange; submit with visit options
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <!-- header, form fields per mode, DialogFooter Cancel/Save -->
        </DialogContent>
    </Dialog>
</template>
```

Do **not** put delete controls in this dialog.

- [x] **Step 2: Wire `Index.vue`**

1. Extend props:

```ts
const props = defineProps<{
    pockets: Pocket[];
    icons: IconOption[];
    colors: ColorOption[];
    currencies: Currency[];
}>();
```

Expand the local `Pocket` type to include edit fields used by the dialog (`currency_id`, `initial_balance`, `planning_mode`, `monthly_contribution`, `target_date`, `recommended_monthly`, `projected_completion_date`) — they are already on the resource.

2. Modal state:

```ts
const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingPocket = ref<Pocket | null>(null);

function openCreateDialog(): void {
    formMode.value = 'create';
    editingPocket.value = null;
    formOpen.value = true;
}

function openEditDialog(pocket: Pocket): void {
    formMode.value = 'edit';
    editingPocket.value = pocket;
    formOpen.value = true;
}
```

3. Header CTA: replace `Link` with `<Button type="button" @click="openCreateDialog">`.

4. Pencil: replace `Link` with `<Button variant="ghost" size="icon" type="button" @click="openEditDialog(pocket)">`.

5. Mount dialog:

```vue
<PocketFormDialog
    v-model:open="formOpen"
    :mode="formMode"
    :pocket="editingPocket"
    :icons="icons"
    :colors="colors"
    :currencies="currencies"
/>
```

Keep drag-reorder and `deletePocket` / `window.confirm` unchanged.

- [x] **Step 3: Delete page components**

Delete `resources/js/pages/pockets/Create.vue` and `resources/js/pages/pockets/Edit.vue`.

- [x] **Step 4: Prettier**

Format touched Vue files with the project Prettier config.

- [ ] **Step 5: Commit** (only if the user asked for commits)

```bash
git add resources/js/components/pockets/modals/PocketFormDialog.vue resources/js/pages/pockets/Index.vue
git rm resources/js/pages/pockets/Create.vue resources/js/pages/pockets/Edit.vue
git commit -m "$(cat <<'EOF'
feat(pockets): add/edit via PocketFormDialog on index

EOF
)"
```

---

### Task 3: Chrome, i18n, docs

**Files:**
- Modify: `resources/js/lib/pageRegistry.ts`
- Modify: `resources/js/lib/pageChrome.test.ts`
- Modify: `resources/js/locales/pl.json`, `en.json`
- Modify: `.docs/checklist.md` (§19)
- Modify: `.docs/prd.md` (FR-P1 / §7 screens — “dodaj/edytuj w modalu”)

- [x] **Step 1: Drop registry nodes**

In `pageRegistry.ts`, remove:

```ts
'pockets.create': { titleKey: 'pockets.create.title', parent: 'pockets' },
'pockets.edit': { titleKey: 'pockets.edit.title', parent: 'pockets' },
```

- [x] **Step 2: Fix `pageChrome.test.ts`**

Replace the `pockets.edit` detail-override example with `profile.edit` (resolves to node `settings.profile`, parent `settings`):

```ts
it('lets a detail page override the leaf, the title and the document title', () => {
    const chrome = resolvePageChrome(
        at('profile.edit'),
        { title: 'Jan Kowalski', description: 'settings.profile.heading' },
        deps,
    );

    expect(chrome.crumbs).toEqual([
        { title: 'settings.title', href: '/profile.edit' },
        { title: 'Jan Kowalski', href: null },
    ]);
    expect(chrome.title).toBe('Jan Kowalski');
    expect(chrome.description).toBe('settings.profile.heading');
    expect(chrome.documentTitle).toBe('Jan Kowalski');
});
```

Do **not** leave a reference to `pockets.edit`.

Run: `npm run test -- resources/js/lib/pageChrome.test.ts` (or the project’s Vitest script from `package.json`).

Expected: PASS.

- [x] **Step 3: i18n**

In `pl.json` and `en.json`, under `pockets.edit`, remove the `description` key only. Keep `title`, `recommendedMonthly`, `projectedCompletionDate`.

- [x] **Step 4: Checklist + PRD**

Checklist §19 — add a checked line analogous to categories:

```markdown
- [x] Dodanie i edycja kieszeni z listy `/kieszenie` (modal; GET create/edit → redirect na index) **[plan 2026-08-26-pockets-modals]**
```

Update the CRUD line that still cites `Create.vue` / `Edit.vue` so it points at `Index.vue` + modal.

PRD FR-P1 / screens §7: replace “osobne ekrany tworzenia/edycji” with list + add/edit in modal (same wording style as Konta / Kategorie).

- [ ] **Step 5: Commit** (only if the user asked for commits)

```bash
git add resources/js/lib/pageRegistry.ts resources/js/lib/pageChrome.test.ts resources/js/locales/pl.json resources/js/locales/en.json .docs/checklist.md .docs/prd.md
git commit -m "$(cat <<'EOF'
docs(pockets): registry and PRD for index modals

EOF
)"
```

---

## Verification (manual UI)

After Sail frontend is up: on `/kieszenie` — add from header, edit from pencil, 422 leaves modal open, Esc does not close while saving, delete still confirm, old `/kieszenie/utworz` (or localized create URL) lands on list.

---

## Self-review vs spec

| Spec requirement | Task |
|------------------|------|
| Index + `PocketFormOptions` | Task 1 |
| GET create/edit → redirect; foreign 403 | Task 1 |
| `PocketFormDialog` create/edit fields | Task 2 |
| Index CTA / pencil; no `?new=1` | Task 2 |
| Delete Create/Edit pages | Task 2 |
| Delete stays confirm | Task 2 (unchanged) |
| pageRegistry + pageChrome + i18n description | Task 3 |
| Checklist / PRD | Task 3 |
| No list restyle / no delete dialog | Out of scope — not tasked |
