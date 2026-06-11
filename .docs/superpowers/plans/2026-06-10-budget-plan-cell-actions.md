# Budget plan cell — icon alignment & align-to-actual — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align edit icons in the budget Plan column via fixed-width right-aligned amounts; on monthly view add an icon-only “align to actual” action with the same save/cancel flow as plan edit.

**Architecture:** Extend `EditableEstimateCell` with optional align button and mode-aware draft pre-fill. `Monthly.vue` tracks `editingMode` alongside `editingCategoryId`. Align save reuses existing `PATCH categories.estimates.monthly`. Yearly gets layout fix only.

**Tech Stack:** Vue 3, TypeScript, Tailwind CSS 3, Inertia v2, lucide-vue-next, vue-i18n, Pest 4 (existing backend tests), ESLint, Sail.

**Spec:** `.docs/superpowers/specs/2026-06-10-budget-plan-cell-actions-design.md`  
**Suggested branch:** `improvement/budget-plan-cell-actions` (from `develop`)

---

## File map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `resources/js/locales/pl.json` | `budget.estimate.align` label |
| Modify | `resources/js/locales/en.json` | `budget.estimate.align` label |
| Modify | `resources/js/components/budget/EditableEstimateCell.vue` | Fixed-width amount, align button, mode-aware draft |
| Modify | `resources/js/components/budget/BudgetCategorySection.vue` | Wire align props/emits |
| Modify | `resources/js/pages/budget/Monthly.vue` | `editingMode` state, wider plan column |
| Modify | `resources/js/pages/budget/Yearly.vue` | Fixed-width amount via shared cell (no logic change) |
| Unchanged | Backend / routes | Reuse `categories.estimates.monthly` |

**Note:** No JS unit-test runner (no Vitest/Jest). Verify via ESLint + existing `MonthlyBudgetTest` / `YearlyBudgetTest` + manual browser checklist from spec.

---

### Task 1: Add i18n keys for align action

**Files:**
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add `align` key to Polish locale**

In `resources/js/locales/pl.json`, inside `budget.estimate`, add after `"cancel"`:

```json
            "align": "Wyrównaj do wykonania — {name}",
```

Resulting block:

```json
        "estimate": {
            "edit": "Edytuj plan — {name}",
            "save": "Zapisz",
            "cancel": "Anuluj",
            "align": "Wyrównaj do wykonania — {name}"
        }
```

- [ ] **Step 2: Add `align` key to English locale**

In `resources/js/locales/en.json`, inside `budget.estimate`, add after `"cancel"`:

```json
            "align": "Align to actual — {name}",
```

- [ ] **Step 3: Run ESLint on locale files (if included in lint glob)**

```bash
npm run lint -- resources/js/locales/pl.json resources/js/locales/en.json
```

Expected: no errors (or lint skips JSON — that is fine).

- [ ] **Step 4: Commit**

```bash
git add resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "$(cat <<'EOF'
feat(budget): add i18n label for align-to-actual action

EOF
)"
```

---

### Task 2: Extend EditableEstimateCell — layout + align button

**Files:**
- Modify: `resources/js/components/budget/EditableEstimateCell.vue`

- [ ] **Step 1: Update script — new props, emit, watch logic**

Replace the full `<script setup>` block with:

```ts
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { Check, Equal, Pencil, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        plan: string | null;
        currency: CurrencyDisplay;
        inputId: string;
        placeholder: string;
        editLabel: string;
        alignLabel: string;
        saveLabel: string;
        cancelLabel: string;
        isEditing: boolean;
        mode?: 'plan' | 'align' | null;
        alignValue?: string | null;
        showAlignButton?: boolean;
    }>(),
    {
        mode: null,
        alignValue: null,
        showAlignButton: false,
    },
);

const emit = defineEmits<{
    'start-edit': [];
    'start-align': [];
    cancel: [];
    save: [rawValue: string];
}>();

const draft = ref('');
const error = ref<string | null>(null);

watch(
    () => [props.isEditing, props.mode] as const,
    ([editing, mode]) => {
        if (editing) {
            draft.value = mode === 'align' ? (props.alignValue ?? '') : (props.plan ?? '');
            error.value = null;
        }
    },
);

function isValidAmount(raw: string): boolean {
    const trimmed = raw.trim();

    if (trimmed === '') {
        return true;
    }

    return /^\d+([.,]\d{1,2})?$/.test(trimmed);
}

function onSave() {
    if (!isValidAmount(draft.value)) {
        error.value = 'invalid';
        return;
    }

    error.value = null;
    emit('save', draft.value);
}

function onCancel() {
    error.value = null;
    emit('cancel');
}

function onInputKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter') {
        event.preventDefault();
        onSave();
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        onCancel();
    }
}
</script>
```

- [ ] **Step 2: Update template — fixed-width amount + align button**

Replace the `<template>` block with:

```vue
<template>
    <div v-if="!isEditing" class="flex items-center gap-1">
        <span class="w-28 shrink-0 text-right tabular-nums">{{ formatMoney(plan, currency) }}</span>
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0" :aria-label="editLabel" @click="emit('start-edit')">
            <Pencil class="h-4 w-4" />
        </Button>
        <Button
            v-if="showAlignButton"
            type="button"
            variant="ghost"
            size="icon"
            class="h-8 w-8 shrink-0"
            :aria-label="alignLabel"
            @click="emit('start-align')"
        >
            <Equal class="h-4 w-4" />
        </Button>
    </div>
    <div v-else class="flex items-center gap-1">
        <Input
            :id="inputId"
            v-model="draft"
            type="text"
            inputmode="decimal"
            class="h-8 w-28 tabular-nums"
            :placeholder="placeholder"
            :aria-invalid="error !== null"
            @keydown="onInputKeydown"
        />
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0" :aria-label="saveLabel" @click="onSave">
            <Check class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
        </Button>
        <Button type="button" variant="ghost" size="icon" class="h-8 w-8 shrink-0" :aria-label="cancelLabel" @click="onCancel">
            <X class="h-4 w-4 text-muted-foreground" />
        </Button>
    </div>
</template>
```

- [ ] **Step 3: Run ESLint**

```bash
npm run lint -- resources/js/components/budget/EditableEstimateCell.vue
```

Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/budget/EditableEstimateCell.vue
git commit -m "$(cat <<'EOF'
feat(budget): align plan cell icons and add align-to-actual button

Fixed-width right-aligned amount; mode-aware draft pre-fill for align action.
EOF
)"
```

---

### Task 3: Wire BudgetCategorySection props and emits

**Files:**
- Modify: `resources/js/components/budget/BudgetCategorySection.vue`

- [ ] **Step 1: Add `editingMode` prop and `start-align` emit**

In the props block, add after `editingCategoryId`:

```ts
    editingMode?: 'plan' | 'align' | null;
```

In the emit block, add:

```ts
    'start-align': [categoryId: number];
```

- [ ] **Step 2: Pass new props to EditableEstimateCell**

Replace the `<EditableEstimateCell … />` block inside the plan `<td>` with:

```vue
                            <EditableEstimateCell
                                :plan="planForRow(row)"
                                :currency="currency"
                                :input-id="`${variant}-plan-${row.category_id}`"
                                :placeholder="planPlaceholder"
                                :edit-label="t('budget.estimate.edit', { name: row.name })"
                                :align-label="t('budget.estimate.align', { name: row.name })"
                                :save-label="t('budget.estimate.save')"
                                :cancel-label="t('budget.estimate.cancel')"
                                :is-editing="editingCategoryId === row.category_id"
                                :mode="editingCategoryId === row.category_id ? (editingMode ?? 'plan') : null"
                                :align-value="row.actual"
                                :show-align-button="variant === 'monthly'"
                                @start-edit="emit('start-edit', row.category_id)"
                                @start-align="emit('start-align', row.category_id)"
                                @cancel="emit('cancel')"
                                @save="(raw) => emit('save', row, raw)"
                            />
```

Note: when `editingMode` is undefined (Yearly), fallback `?? 'plan'` ensures draft uses plan value.

- [ ] **Step 3: Run ESLint**

```bash
npm run lint -- resources/js/components/budget/BudgetCategorySection.vue
```

Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/budget/BudgetCategorySection.vue
git commit -m "$(cat <<'EOF'
feat(budget): wire align-to-actual through category section

Pass align props and start-align emit from BudgetCategorySection to EditableEstimateCell.
EOF
)"
```

---

### Task 4: Monthly.vue — editingMode state + wider plan column

**Files:**
- Modify: `resources/js/pages/budget/Monthly.vue`

- [ ] **Step 1: Add `editingMode` ref and handlers**

After `const editingCategoryId = ref<number | null>(null);` add:

```ts
const editingMode = ref<'plan' | 'align' | null>(null);
```

Replace `startEdit`:

```ts
function startEdit(categoryId: number) {
    editingCategoryId.value = categoryId;
    editingMode.value = 'plan';
}
```

Replace `cancelEdit`:

```ts
function cancelEdit() {
    editingCategoryId.value = null;
    editingMode.value = null;
}
```

Add after `startEdit`:

```ts
function startAlign(categoryId: number) {
    editingCategoryId.value = categoryId;
    editingMode.value = 'align';
}
```

In `saveMonthlyEstimate`, ensure reset clears mode — update the early-return and `onFinish` blocks:

```ts
    if (normalized === current || (normalized === '' && current === '')) {
        editingCategoryId.value = null;
        editingMode.value = null;
        return;
    }
```

```ts
            onFinish: () => {
                editingCategoryId.value = null;
                editingMode.value = null;
            },
```

- [ ] **Step 2: Pass props/events to both BudgetCategorySection instances**

On both `<BudgetCategorySection>` blocks, add:

```vue
                :editing-mode="editingMode"
                @start-align="startAlign"
```

- [ ] **Step 3: Widen monthly plan column**

In the scoped `<style>`, change:

```css
    --budget-col-plan: 11.5rem;
```

(was `9rem`)

- [ ] **Step 4: Run ESLint**

```bash
npm run lint -- resources/js/pages/budget/Monthly.vue
```

Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/budget/Monthly.vue
git commit -m "$(cat <<'EOF'
feat(budget): add align-to-actual state on monthly budget page

Track editingMode for plan vs align; widen plan column for two action icons.
EOF
)"
```

---

### Task 5: Yearly.vue — confirm layout (no logic changes)

**Files:**
- Modify: `resources/js/pages/budget/Yearly.vue` (verify only)

- [ ] **Step 1: Verify Yearly inherits fixed-width amount from EditableEstimateCell**

No script changes required. `BudgetCategorySection` on Yearly does not pass `editingMode` or `@start-align` — align button hidden via `show-align-button="variant === 'monthly'"`.

Confirm `--budget-col-plan: 9rem` remains in scoped style (one pencil button fits).

- [ ] **Step 2: Run ESLint on Yearly page**

```bash
npm run lint -- resources/js/pages/budget/Yearly.vue
```

Expected: no errors.

- [ ] **Step 3: Commit (only if any incidental fix was needed)**

If no file changes: skip commit for this task.

---

### Task 6: Verification

**Files:**
- Test: `tests/Feature/Budgets/MonthlyBudgetTest.php`
- Test: `tests/Feature/Budgets/YearlyBudgetTest.php`

- [ ] **Step 1: Run ESLint on all touched Vue files**

```bash
npm run lint -- resources/js/components/budget/EditableEstimateCell.vue resources/js/components/budget/BudgetCategorySection.vue resources/js/pages/budget/Monthly.vue resources/js/pages/budget/Yearly.vue
```

Expected: no errors.

- [ ] **Step 2: Run existing budget feature tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Budgets/MonthlyBudgetTest.php tests/Feature/Budgets/YearlyBudgetTest.php
```

Expected: all tests pass (backend unchanged).

- [ ] **Step 3: Manual browser checklist**

With `./vendor/bin/sail npm run dev` running, verify spec checklist:

- [ ] Monthly: pencil icons aligned across income/expense rows
- [ ] Monthly: Equal icon visible on every row, always enabled
- [ ] Monthly: align opens input pre-filled with `actual` value
- [ ] Monthly: save from align updates plan; cancel restores both icons
- [ ] Monthly: plan edit hides align button (and vice versa)
- [ ] Monthly: only one row in edit mode at a time
- [ ] Yearly: pencil icons aligned; no Equal button
- [ ] Enter/Escape work in both monthly modes

- [ ] **Step 4: Final commit (if checklist doc updated)**

Only if `.docs/checklist.md` is updated — not required for this scope.

---

## Spec coverage self-review

| Spec requirement | Task |
|------------------|------|
| Fixed `w-28` right-aligned amount | Task 2 |
| Align icon `Equal` + aria-label | Task 1, Task 2 |
| Align always active | Task 2 (no disabled logic) |
| `editingMode` state | Task 4 |
| Mutual exclusion (hide icons in edit) | Task 2 template (`v-if="!isEditing"`) |
| Reuse PATCH monthly endpoint | Task 4 (saveMonthlyEstimate unchanged) |
| Yearly: icon alignment only | Task 2 + Task 5 |
| Monthly plan column `11.5rem` | Task 4 |
| Yearly plan column `9rem` | Task 5 |
| i18n `budget.estimate.align` | Task 1 |
| Pocket section out of scope | — |
| No backend changes | — |

No placeholders. All code blocks are complete.
