# Pockets Index OpenShip Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle `/kieszenie` page content to the OpenShip language used on `/konta` and `/kategorie`: one lifted card, compact draggable rows, updated progress/badge tokens, without changing PHP, modals, reorder, or delete confirm behavior.

**Architecture:** Extract `PocketRow` (`<li>` per row) for drag handle, badge, progress, balance, status pills, and actions. Single `rounded-2xl bg-card` wrapper in `Index.vue`; remove inner `listTitle`. Minor updates to `PocketBadge` and `PocketProgressBar`. Add `pockets.index.dragHandle` i18n. No PHP.

**Tech Stack:** Inertia Vue 3, Tailwind v3, shadcn-vue Button, vue-draggable-plus, vue-i18n, Lucide, Pest 4 (smoke only), Prettier, `vue-tsc`.

**Spec:** `.docs/superpowers/specs/2026-08-27-pockets-index-openship-design.md`  
**Suggested branch:** `improvement/pockets-index-openship`

## Global Constraints

- Variant A architecture: domain UI in `components/pockets/`, not `components/ui/`.
- No PHP, routes, Inertia props, or reorder API changes.
- Modals (`PocketFormDialog`) and `PageHeader` are **untouched**.
- Delete stays `window.confirm` in `Index.vue` — no delete dialog.
- Card: `overflow-hidden rounded-2xl border border-transparent bg-card` — no resting shadow.
- Row density: `px-3 py-2.5`, hover `hover:bg-foreground/[0.03]`, dividers `border-border/50`.
- No inner `listTitle` heading inside the card.
- Empty: short muted text inside card (`pockets.index.empty`); no floor empty with CTA.
- Code, comments, commits in English. User-facing copy uses existing i18n keys plus new `pockets.index.dragHandle`.
- UI: `.cursor/rules/wallet-ui-design.mdc` and skill `openship-inspired-ui`.
- After Vue: `npx prettier --write` on touched files; `npm run typecheck`. No PHP expected — skip Pint. Smoke: `./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketCrudTest.php tests/Feature/Pockets/PocketReorderTest.php`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `resources/js/components/pockets/PocketBadge.vue` | Icon tile `rounded-md` → `rounded-lg` |
| Modify | `resources/js/components/pockets/PocketProgressBar.vue` | Track `h-1.5`, fill token, `tabular-nums` on caption |
| Modify | `resources/js/locales/pl.json`, `en.json` | Add `pockets.index.dragHandle` |
| Create | `resources/js/components/pockets/PocketRow.vue` | Compact draggable row with badge, progress, balance, status, actions |
| Modify | `resources/js/pages/pockets/Index.vue` | One card, wire `PocketRow`, header icon polish |
| Modify | `.docs/checklist.md` | Record OpenShip list restyle under §19 |

---

### Task 1: `PocketBadge` icon tile radius

**Files:**
- Modify: `resources/js/components/pockets/PocketBadge.vue`

**Interfaces:**
- Consumes: nothing new.
- Produces: icon tile with `rounded-lg` (unchanged props API).

- [ ] **Step 1: Update tile radius**

In `resources/js/components/pockets/PocketBadge.vue`, change the icon tile span class from `rounded-md` to `rounded-lg`:

```vue
<span class="inline-flex shrink-0 items-center justify-center rounded-lg" :class="tileSizeClass" :style="tileStyle" aria-hidden="true">
```

- [ ] **Step 2: Format and typecheck**

Run:

```bash
npx prettier --write resources/js/components/pockets/PocketBadge.vue
npm run typecheck
```

Expected: Prettier exits 0; typecheck exits 0.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/pockets/PocketBadge.vue
git commit -m "style(pockets): round pocket badge icon tile to OpenShip"
```

---

### Task 2: `PocketProgressBar` tokens

**Files:**
- Modify: `resources/js/components/pockets/PocketProgressBar.vue`

**Interfaces:**
- Consumes: existing props (`percent`, `balance`, `targetAmount`, `currency`).
- Produces: thinner track and OpenShip-compatible fill (unchanged props API).

- [ ] **Step 1: Update track, fill, and caption**

Replace the template block in `resources/js/components/pockets/PocketProgressBar.vue`:

```vue
<template>
    <div v-if="targetAmount !== null" class="grid gap-1">
        <div
            class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="Math.round(normalizedPercent)"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full rounded-full bg-emerald-500/90 transition-[width] duration-200 ease-out"
                :style="{ width: `${normalizedPercent}%` }"
            />
        </div>
        <p class="text-xs tabular-nums text-muted-foreground">
            {{ formatMoney(balance, currency) }} / {{ formatMoney(targetAmount, currency) }}
        </p>
    </div>
</template>
```

- [ ] **Step 2: Format and typecheck**

Run:

```bash
npx prettier --write resources/js/components/pockets/PocketProgressBar.vue
npm run typecheck
```

Expected: exits 0.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/pockets/PocketProgressBar.vue
git commit -m "style(pockets): tune progress bar for OpenShip density"
```

---

### Task 3: Add `pockets.index.dragHandle` i18n

**Files:**
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

**Interfaces:**
- Consumes: nothing.
- Produces: `t('pockets.index.dragHandle')` string in both locales.

- [ ] **Step 1: Add PL key**

In `resources/js/locales/pl.json`, inside `pockets.index`, add after `"add"`:

```json
"dragHandle": "Przeciągnij, aby zmienić kolejność",
```

- [ ] **Step 2: Add EN key**

In `resources/js/locales/en.json`, inside `pockets.index`, add after `"add"`:

```json
"dragHandle": "Drag to reorder",
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "i18n(pockets): add drag handle label for index list"
```

---

### Task 4: Extract `PocketRow`

**Files:**
- Create: `resources/js/components/pockets/PocketRow.vue`

**Interfaces:**
- Consumes: pocket object with fields used in the row (see type below).
- Produces:

```ts
export type PocketRowCurrency = {
    code: string;
    symbol: string;
    precision: number;
};

export type PocketRowPocket = {
    id: number;
    name: string;
    icon: string;
    color: string;
    currency: PocketRowCurrency;
    target_amount: string | null;
    progress_percent: number | null;
    balance: string;
    is_completed: boolean;
    is_overdue: boolean;
};

defineProps<{
    pocket: PocketRowPocket;
}>();

defineEmits<{
    edit: [pocket: PocketRowPocket];
    delete: [pocket: PocketRowPocket];
}>();
```

- [ ] **Step 1: Create `PocketRow.vue`**

Create `resources/js/components/pockets/PocketRow.vue`:

```vue
<script setup lang="ts">
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import PocketProgressBar from '@/components/pockets/PocketProgressBar.vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/formatMoney';
import { GripVertical, Pencil, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

export type PocketRowCurrency = {
    code: string;
    symbol: string;
    precision: number;
};

export type PocketRowPocket = {
    id: number;
    name: string;
    icon: string;
    color: string;
    currency: PocketRowCurrency;
    target_amount: string | null;
    progress_percent: number | null;
    balance: string;
    is_completed: boolean;
    is_overdue: boolean;
};

defineProps<{
    pocket: PocketRowPocket;
}>();

defineEmits<{
    edit: [pocket: PocketRowPocket];
    delete: [pocket: PocketRowPocket];
}>();

const { t } = useI18n();
</script>

<template>
    <li
        class="list-row-hover flex flex-col gap-2 border-b border-border/50 px-3 py-2.5 last:border-b-0 hover:bg-foreground/[0.03] sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex min-w-0 flex-1 items-start gap-2">
            <button
                type="button"
                class="drag-handle shrink-0 cursor-grab touch-none rounded-lg p-1 text-muted-foreground hover:bg-foreground/[0.06] active:cursor-grabbing"
                :aria-label="t('pockets.index.dragHandle')"
            >
                <GripVertical class="size-4" :stroke-width="1.7" aria-hidden="true" />
            </button>

            <div class="min-w-0 flex-1 space-y-2">
                <PocketBadge :name="pocket.name" :icon="pocket.icon" :color="pocket.color" />
                <PocketProgressBar
                    :percent="pocket.progress_percent"
                    :balance="pocket.balance"
                    :target-amount="pocket.target_amount"
                    :currency="pocket.currency"
                />
            </div>
        </div>

        <div class="no-drag flex flex-wrap items-center gap-2 sm:justify-end">
            <span class="text-sm font-medium tabular-nums">{{ formatMoney(pocket.balance, pocket.currency) }}</span>

            <span
                v-if="pocket.is_completed"
                class="inline-flex items-center rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400"
            >
                {{ t('pockets.status.completed') }}
            </span>
            <span
                v-if="pocket.is_overdue"
                class="inline-flex items-center rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"
            >
                {{ t('pockets.status.overdue') }}
            </span>

            <Button variant="ghost" size="icon" type="button" :aria-label="t('actions.edit')" @click="$emit('edit', pocket)">
                <Pencil class="size-4 text-muted-foreground" :stroke-width="1.7" aria-hidden="true" />
            </Button>

            <Button
                variant="ghost"
                size="icon"
                type="button"
                :aria-label="t('pockets.index.delete')"
                @click="$emit('delete', pocket)"
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
npx prettier --write resources/js/components/pockets/PocketRow.vue
npm run typecheck
```

Expected: exits 0.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/pockets/PocketRow.vue
git commit -m "feat(pockets): add OpenShip PocketRow component"
```

---

### Task 5: Restyle `pockets/Index.vue`

**Files:**
- Modify: `resources/js/pages/pockets/Index.vue`

**Interfaces:**
- Consumes: `PocketRow` with props/emits from Task 4.
- Produces: single-card index page; `onReorderEnd`, `deletePocket`, modal handlers unchanged in behavior.

- [ ] **Step 1: Update script imports**

Replace imports at the top of `resources/js/pages/pockets/Index.vue`:

```ts
import PocketFormDialog from '@/components/pockets/modals/PocketFormDialog.vue';
import PocketRow, { type PocketRowPocket } from '@/components/pockets/PocketRow.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';
import { ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
```

Remove unused imports: `PocketBadge`, `PocketProgressBar`, `formatMoney`, `GripVertical`, `Pencil`, `Trash2`.

Update handler signatures to use `PocketRowPocket` where appropriate:

```ts
function openEditDialog(pocket: PocketRowPocket): void {
    formMode.value = 'edit';
    editingPocket.value = pocket as Pocket;
    formOpen.value = true;
}

function deletePocket(pocket: PocketRowPocket): void {
    if (!window.confirm(t('pockets.index.deleteConfirm', { name: pocket.name }))) {
        return;
    }

    router.delete(route('pockets.destroy', pocket.id), { preserveScroll: true });
}
```

(`Pocket` type stays on the page for props and `editingPocket`; cast is safe because `PocketRowPocket` is a subset.)

- [ ] **Step 2: Update header Add button icon**

In the `#headerActions` template slot:

```vue
<Plus class="size-4" :stroke-width="1.7" aria-hidden="true" />
```

- [ ] **Step 3: Replace list template with one OpenShip card**

Replace the entire `<div class="flex flex-col gap-6">…</div>` block with:

```vue
<div class="overflow-hidden rounded-2xl border border-transparent bg-card">
    <p v-if="list.length === 0" class="px-3 py-4 text-sm text-muted-foreground">
        {{ t('pockets.index.empty') }}
    </p>

    <VueDraggable
        v-else
        v-model="list"
        tag="ul"
        handle=".drag-handle"
        filter=".no-drag"
        :animation="150"
        ghost-class="opacity-50"
        chosen-class="bg-muted/30"
        @end="onReorderEnd"
    >
        <PocketRow v-for="pocket in list" :key="pocket.id" :pocket="pocket" @edit="openEditDialog" @delete="deletePocket" />
    </VueDraggable>
</div>
```

`PocketFormDialog` block below stays unchanged.

- [ ] **Step 4: Format and typecheck**

Run:

```bash
npx prettier --write resources/js/pages/pockets/Index.vue
npm run typecheck
```

Expected: exits 0.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/pockets/Index.vue
git commit -m "style(pockets): restyle index list to OpenShip single card"
```

---

### Task 6: Smoke tests and checklist

**Files:**
- Modify: `.docs/checklist.md`

- [ ] **Step 1: Run pocket smoke tests**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Pockets/PocketCrudTest.php tests/Feature/Pockets/PocketReorderTest.php
```

Expected: all tests pass.

- [ ] **Step 2: Update checklist**

Under `.docs/checklist.md` §19, after the modals line, add:

```markdown
- [x] Lista OpenShip: jedna karta, kompaktowe wiersze, `PocketRow` **[plan 2026-08-27-pockets-index-openship]**
```

Place it immediately after:

```markdown
- [x] Dodanie i edycja kieszeni z listy `/kieszenie` (modal; GET create/edit → redirect na index) **[plan 2026-08-26-pockets-modals]**
```

- [ ] **Step 3: Commit**

```bash
git add .docs/checklist.md
git commit -m "docs: record OpenShip pockets index list restyle"
```

---

### Task 7: Manual verification

- [ ] **Step 1: Browser check**

With `./vendor/bin/sail npm run dev` running, open `/kieszenie` and verify:

| Check | Expected |
|-------|----------|
| Layout | One white card on grey page; no inner „Twoje kieszenie” heading |
| Rows | Compact padding; hover highlight; badge with rounded-lg tile |
| Progress | Thinner bar when target set; balance/target caption with tabular nums |
| Balance | Shown on the right with tabular-nums |
| Status | Completed = emerald pill; overdue = amber pill (OpenShip opacity recipe) |
| Drag | Reorder persists after refresh |
| Edit | Pencil opens edit modal with correct pocket |
| Delete | Confirm dialog then row removed |
| Empty | Muted „Brak kieszeni.” inside card when no pockets |
| Dark mode | Card lifts on black floor; status pills readable |
| Mobile | Row stacks (badge/progress above balance/actions); drag handle reachable |

- [ ] **Step 2: Final commit (only if fixes were needed in Step 1)**

If manual QA required fixes, commit each fix separately with `style(pockets): …` messages. If no fixes, skip.

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| One `rounded-2xl bg-card` | Task 5 |
| Remove `listTitle` | Task 5 |
| Empty text inside card | Task 5 |
| Compact rows `px-3 py-2.5` | Task 4 |
| `PocketRow` extract | Tasks 4–5 |
| `PocketBadge` `rounded-lg` | Task 1 |
| `PocketProgressBar` tokens | Task 2 |
| OpenShip status pills | Task 4 |
| `pockets.index.dragHandle` | Task 3 |
| Header Plus `stroke-width="1.7"` | Task 5 |
| No PHP / modals change | Global constraints |
| Pest smoke | Task 6 |
| Checklist | Task 6 |
| Manual dark/mobile | Task 7 |

## Out of scope (confirmed)

- `PocketFormDialog` interior, `BudgetPocketSection`, floor empty with CTA, delete dialog, PHP, shared draggable primitive, grid layout.
