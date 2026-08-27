# Categories Index OpenShip Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle `/kategorie` page content to the OpenShip language used on `/konta` and `/transakcje`: one lifted card with income/expense sections, compact draggable rows, OpenShip section labels, without changing PHP, modals, or reorder behavior.

**Architecture:** Extract `CategoryRow` (`<li>` per row) so row tokens live in one file. Single `rounded-2xl bg-card` wrapper in `Index.vue` with a section loop; dedupe the two large draggable blocks. Minor `CategoryBadge` radius fix. No PHP.

**Tech Stack:** Inertia Vue 3, Tailwind v3, shadcn-vue Button, vue-draggable-plus, vue-i18n, Lucide, Pest 4 (smoke only), Prettier, `vue-tsc`.

**Spec:** `.docs/superpowers/specs/2026-08-27-categories-index-openship-design.md`  
**Suggested branch:** `improvement/categories-index-openship`

## Global Constraints

- Variant A architecture: domain UI in `components/categories/`, not `components/ui/`.
- No PHP, routes, Inertia props, or reorder API changes.
- Modals (`CategoryFormDialog`, `DeleteCategoryDialog`) and `PageHeader` are **untouched**.
- Card: `overflow-hidden rounded-2xl border border-transparent bg-card` — no resting shadow.
- Row density: `px-3 py-2.5`, hover `hover:bg-foreground/[0.03]`, dividers `border-border/50`.
- Section labels: `text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60`.
- Empty section: short muted text inside card (`categories.index.empty`); no floor empty with CTA.
- Code, comments, commits in English. User-facing copy unchanged (existing i18n keys).
- UI: `.cursor/rules/wallet-ui-design.mdc` and skill `openship-inspired-ui`.
- After Vue: `npx prettier --write` on touched files; `npm run typecheck`. No PHP expected — skip Pint. Smoke: `./vendor/bin/sail artisan test --compact tests/Feature/Categories/CategoryCrudTest.php tests/Feature/Categories/CategoryReorderTest.php`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `resources/js/components/categories/CategoryRow.vue` | Compact draggable row: drag handle, badge, system hint, edit/delete |
| Modify | `resources/js/components/categories/CategoryBadge.vue` | Icon tile `rounded-md` → `rounded-lg` |
| Modify | `resources/js/pages/categories/Index.vue` | One card, section loop, wire `CategoryRow`, header icon polish |
| Modify | `.docs/checklist.md` | Record OpenShip list restyle under §18 |

---

### Task 1: `CategoryBadge` icon tile radius

**Files:**
- Modify: `resources/js/components/categories/CategoryBadge.vue`

**Interfaces:**
- Consumes: nothing new.
- Produces: icon tile with `rounded-lg` (unchanged props API).

- [ ] **Step 1: Update tile radius**

In `resources/js/components/categories/CategoryBadge.vue`, change the icon tile span class from `rounded-md` to `rounded-lg`:

```vue
<span class="inline-flex shrink-0 items-center justify-center rounded-lg" :class="tileSizeClass" :style="tileStyle" aria-hidden="true">
```

- [ ] **Step 2: Format and typecheck**

Run:

```bash
npx prettier --write resources/js/components/categories/CategoryBadge.vue
npm run typecheck
```

Expected: Prettier exits 0; typecheck exits 0.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/categories/CategoryBadge.vue
git commit -m "style(categories): round category badge icon tile to OpenShip"
```

---

### Task 2: Extract `CategoryRow`

**Files:**
- Create: `resources/js/components/categories/CategoryRow.vue`

**Interfaces:**
- Consumes: category object with `id`, `name`, `icon`, `color`, `is_system` (same shape as Index `Category` type).
- Produces:

```ts
export type CategoryRowCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    is_system: boolean;
};

defineProps<{
    category: CategoryRowCategory;
    deleteDisabled: boolean;
}>();

defineEmits<{
    edit: [category: CategoryRowCategory];
    delete: [category: CategoryRowCategory];
}>();
```

- [ ] **Step 1: Create `CategoryRow.vue`**

Create `resources/js/components/categories/CategoryRow.vue`:

```vue
<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { Button } from '@/components/ui/button';
import { GripVertical, Pencil, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

export type CategoryRowCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    is_system: boolean;
};

defineProps<{
    category: CategoryRowCategory;
    deleteDisabled: boolean;
}>();

defineEmits<{
    edit: [category: CategoryRowCategory];
    delete: [category: CategoryRowCategory];
}>();

const { t } = useI18n();
</script>

<template>
    <li class="list-row-hover flex items-center gap-2 border-b border-border/50 px-3 py-2.5 last:border-b-0 hover:bg-foreground/[0.03] sm:gap-3">
        <button
            type="button"
            class="drag-handle shrink-0 cursor-grab touch-none rounded-lg p-1 text-muted-foreground hover:bg-foreground/[0.06] active:cursor-grabbing"
            :aria-label="t('categories.index.dragHandle')"
        >
            <GripVertical class="size-4" :stroke-width="1.7" aria-hidden="true" />
        </button>

        <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-2 text-left sm:gap-3"
            @click="$emit('edit', category)"
        >
            <CategoryBadge :name="category.name" :icon="category.icon" :color="category.color" size="md" />
            <span v-if="category.is_system" class="shrink-0 text-xs text-muted-foreground">
                ({{ t('categories.index.system') }})
            </span>
        </button>

        <div class="no-drag flex shrink-0 items-center gap-1">
            <Button variant="ghost" size="icon" type="button" :aria-label="t('actions.edit')" @click="$emit('edit', category)">
                <Pencil class="size-4 text-muted-foreground" :stroke-width="1.7" aria-hidden="true" />
            </Button>
            <Button
                v-if="!category.is_system"
                variant="ghost"
                size="icon"
                type="button"
                class="no-drag"
                :disabled="deleteDisabled"
                :aria-label="t('categories.index.delete')"
                @click="$emit('delete', category)"
            >
                <Trash2 class="size-4 text-muted-foreground" :stroke-width="1.7" aria-hidden="true" />
            </Button>
        </div>
    </li>
</template>
```

- [ ] **Step 2: Format and typecheck**

Run:

```bash
npx prettier --write resources/js/components/categories/CategoryRow.vue
npm run typecheck
```

Expected: exits 0.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/categories/CategoryRow.vue
git commit -m "feat(categories): add OpenShip CategoryRow component"
```

---

### Task 3: Restyle `categories/Index.vue`

**Files:**
- Modify: `resources/js/pages/categories/Index.vue`

**Interfaces:**
- Consumes: `CategoryRow` with props/emits from Task 2.
- Produces: single-card index page; script helpers `sectionKeys`, `onReorderEnd` unchanged in behavior.

- [ ] **Step 1: Update script imports**

Replace icon/row imports at the top of `resources/js/pages/categories/Index.vue`:

```ts
import CategoryRow from '@/components/categories/CategoryRow.vue';
import CategoryFormDialog from '@/components/categories/modals/CategoryFormDialog.vue';
import DeleteCategoryDialog from '@/components/categories/modals/DeleteCategoryDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { filterCategoriesByType, type CategoryOption } from '@/lib/categories';
import { router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
```

Add after `syncLists` / watch block:

```ts
const sectionKeys = ['income', 'expense'] as const;
```

Remove unused imports: `CategoryBadge`, `GripVertical`, `Pencil`, `Trash2`.

- [ ] **Step 2: Update header Add button icon**

In the `#headerActions` template slot, add stroke width on `Plus`:

```vue
<Plus class="size-4" :stroke-width="1.7" aria-hidden="true" />
```

- [ ] **Step 3: Replace list template with one OpenShip card**

Replace the entire `<div class="flex flex-col gap-6">…</div>` block (both section loops and duplicated draggables) with:

```vue
<div class="overflow-hidden rounded-2xl border border-transparent bg-card">
    <div
        v-for="sectionKey in sectionKeys"
        :key="sectionKey"
        :class="{ 'border-t border-border/50': sectionKey === 'expense' }"
    >
        <h2 class="px-3 pb-2 pt-4 text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
            {{ t(`budget.sections.${sectionKey}`) }}
        </h2>

        <p
            v-if="(sectionKey === 'expense' ? expenseList : incomeList).length === 0"
            class="px-3 py-4 text-sm text-muted-foreground"
        >
            {{ t('categories.index.empty') }}
        </p>

        <VueDraggable
            v-else-if="sectionKey === 'expense'"
            v-model="expenseList"
            tag="ul"
            handle=".drag-handle"
            filter=".no-drag"
            :animation="150"
            ghost-class="opacity-50"
            chosen-class="bg-muted/30"
            @end="onReorderEnd('expense', $event)"
        >
            <CategoryRow
                v-for="cat in expenseList"
                :key="cat.id"
                :category="cat"
                :delete-disabled="deleteProcessing"
                @edit="openEditDialog"
                @delete="openDeleteDialog"
            />
        </VueDraggable>

        <VueDraggable
            v-else
            v-model="incomeList"
            tag="ul"
            handle=".drag-handle"
            filter=".no-drag"
            :animation="150"
            ghost-class="opacity-50"
            chosen-class="bg-muted/30"
            @end="onReorderEnd('income', $event)"
        >
            <CategoryRow
                v-for="cat in incomeList"
                :key="cat.id"
                :category="cat"
                :delete-disabled="deleteProcessing"
                @edit="openEditDialog"
                @delete="openDeleteDialog"
            />
        </VueDraggable>
    </div>
</div>
```

Modals block below stays unchanged.

- [ ] **Step 4: Format and typecheck**

Run:

```bash
npx prettier --write resources/js/pages/categories/Index.vue
npm run typecheck
```

Expected: exits 0.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/categories/Index.vue
git commit -m "style(categories): restyle index list to OpenShip single card"
```

---

### Task 4: Smoke tests and checklist

**Files:**
- Modify: `.docs/checklist.md`

- [ ] **Step 1: Run category smoke tests**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Categories/CategoryCrudTest.php tests/Feature/Categories/CategoryReorderTest.php
```

Expected: all tests pass.

- [ ] **Step 2: Update checklist**

Under `.docs/checklist.md` §18, after the modals line, add:

```markdown
- [x] Lista OpenShip: jedna karta, sekcje Przychody/Wydatki, kompaktowe wiersze **[plan 2026-08-27-categories-index-openship]**
```

Place it immediately after:

```markdown
- [x] Dodanie i edycja kategorii z listy `/kategorie` (modal; GET create/edit → redirect na index) **[plan 2026-08-26-categories-modals]**
```

- [ ] **Step 3: Commit**

```bash
git add .docs/checklist.md
git commit -m "docs: record OpenShip categories index list restyle"
```

---

### Task 5: Manual verification

- [ ] **Step 1: Browser check**

With `./vendor/bin/sail npm run dev` running, open `/kategorie` and verify:

| Check | Expected |
|-------|----------|
| Layout | One white card on grey page; income section above expense |
| Section labels | Uppercase muted labels (Przychody / Wydatki) |
| Rows | Compact padding; hover highlight; badge with rounded-lg tile |
| Drag | Reorder within a section persists after refresh |
| Edit | Row click or pencil opens edit modal |
| Delete | Trash on non-system rows only; system rows show hint, no trash |
| Empty section | Muted „Brak kategorii.” inside card when section has no rows |
| Dark mode | Card lifts on black floor; borders readable |
| Mobile | Row wraps gracefully; drag handle and actions reachable |

- [ ] **Step 2: Final commit (only if fixes were needed in Step 1)**

If manual QA required fixes, commit each fix separately with `style(categories): …` messages. If no fixes, skip.

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| One `rounded-2xl bg-card` | Task 3 |
| Income first, expense with `border-t` | Task 3 |
| OpenShip section labels | Task 3 |
| Empty text inside card | Task 3 |
| Compact rows `px-3 py-2.5` | Task 2 |
| `CategoryRow` extract + dedupe | Tasks 2–3 |
| `CategoryBadge` `rounded-lg` | Task 1 |
| Header Plus `stroke-width="1.7"` | Task 3 |
| No PHP / modals change | Global constraints |
| Pest smoke | Task 4 |
| Checklist | Task 4 |
| Manual dark/mobile | Task 5 |

## Out of scope (confirmed)

- Modal interiors, `/kieszenie`, budget sections, floor empty with CTA, PHP, new i18n keys, shared draggable primitive.
