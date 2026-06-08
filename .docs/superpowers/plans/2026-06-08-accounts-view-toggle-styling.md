# Accounts View Toggle Styling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align the accounts grid/list view toggle with the app's segmented-control design pattern while keeping icon + text labels.

**Architecture:** Replace raw `<button>` elements and `neutral-*` Tailwind classes in `AccountsViewToggle.vue` with the shared `Button` component (`secondary` / `ghost` variants) and design-system container tokens (`border-input`, `bg-muted/30`). No backend, routing, or i18n changes.

**Tech Stack:** Vue 3, TypeScript, Tailwind CSS 3, shadcn-vue `Button`, lucide-vue-next, vue-i18n.

**Spec:** `.docs/superpowers/specs/2026-06-08-accounts-view-toggle-styling-design.md`  
**Reference implementation:** `resources/js/pages/transactions/Create.vue` (income/expense toggle, lines 157–178)  
**Suggested branch:** `improvement/accounts-view-toggle-styling`

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `resources/js/components/accounts/AccountsViewToggle.vue` | Segmented toggle markup and styling |
| Unchanged | `resources/js/pages/accounts/Index.vue` | View mode state + localStorage |
| Unchanged | `resources/js/components/forms/SegmentedControl.vue` | Shared pattern reference only |
| Unchanged | `resources/js/locales/*.json` | Existing i18n keys |

---

## Task 1: Update AccountsViewToggle styling

**Files:**
- Modify: `resources/js/components/accounts/AccountsViewToggle.vue`

- [ ] **Step 1: Replace the component with design-system markup**

Replace the entire contents of `resources/js/components/accounts/AccountsViewToggle.vue` with:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { LayoutGrid, List } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

type AccountsViewMode = 'grid' | 'list';

defineProps<{
    modelValue: AccountsViewMode;
}>();

defineEmits<{
    'update:modelValue': [mode: AccountsViewMode];
}>();

const { t } = useI18n();
</script>

<template>
    <div
        role="group"
        class="inline-flex gap-1 rounded-lg border border-input bg-muted/30 p-1"
        :aria-label="t('accounts.index.view.toggleAria')"
    >
        <Button
            type="button"
            :variant="modelValue === 'grid' ? 'secondary' : 'ghost'"
            class="h-9 px-3.5"
            :aria-pressed="modelValue === 'grid'"
            @click="$emit('update:modelValue', 'grid')"
        >
            <LayoutGrid aria-hidden="true" />
            {{ t('accounts.index.view.grid') }}
        </Button>
        <Button
            type="button"
            :variant="modelValue === 'list' ? 'secondary' : 'ghost'"
            class="h-9 px-3.5"
            :aria-pressed="modelValue === 'list'"
            @click="$emit('update:modelValue', 'list')"
        >
            <List aria-hidden="true" />
            {{ t('accounts.index.view.list') }}
        </Button>
    </div>
</template>
```

Notes:
- `Button` already applies `gap-2` and `[&_svg]:size-4` via `buttonVariants` — no manual icon sizing needed.
- Container uses `inline-flex` (not `grid grid-cols-2`) because labels vary in width; matches current layout behavior.
- `role="group"` + `aria-label` on container; `aria-pressed` on each option.

- [ ] **Step 2: Run ESLint on the changed file (if dev server not running)**

Run:

```bash
./vendor/bin/sail npm run lint -- resources/js/components/accounts/AccountsViewToggle.vue
```

Expected: no errors (warnings acceptable if pre-existing elsewhere).

If Sail is not running, use host:

```bash
npm run lint -- resources/js/components/accounts/AccountsViewToggle.vue
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/accounts/AccountsViewToggle.vue
git commit -m "$(cat <<'EOF'
style(accounts): align view toggle with segmented control pattern

Replace neutral-* raw buttons with Button secondary/ghost variants and design-system container tokens.
EOF
)"
```

---

## Task 2: Manual verification

**Files:** none (browser smoke test)

- [ ] **Step 1: Start frontend dev server (if not already running)**

Run:

```bash
./vendor/bin/sail npm run dev
```

Or use existing `composer run dev` session.

- [ ] **Step 2: Visual comparison in light mode**

1. Open `/accounts` (must have at least one account).
2. Confirm toggle container has subtle border and muted background (not flat gray `neutral-100`).
3. Confirm active option has `secondary` fill; inactive is transparent/ghost.
4. Open `/transactions/create` and compare the income/expense toggle — colors and border treatment should match.

- [ ] **Step 3: Visual comparison in dark mode**

1. Switch app to dark mode.
2. Revisit `/accounts` — toggle should use dark theme tokens automatically (no explicit `dark:*` classes).
3. Active/inactive states remain distinguishable.

- [ ] **Step 4: Functional smoke test**

1. Click **Lista** — layout switches to rows.
2. Click **Siatka** — layout switches to cards.
3. Refresh page — last selection persists via `localStorage` key `accounts.viewMode`.
4. Tab to toggle buttons — focus ring visible; Enter/Space activates option.

Expected: all four checks pass; no console errors.

- [ ] **Step 5: No PHP test run required**

Per spec: no backend changes. Skip `./vendor/bin/sail artisan test` unless other work is in the same branch.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Container `border border-input bg-muted/30 p-1` | Task 1 Step 1 |
| `Button` with `secondary` / `ghost` | Task 1 Step 1 |
| Icon + text labels preserved | Task 1 Step 1 |
| `aria-pressed` on options | Task 1 Step 1 |
| `aria-label` on container | Task 1 Step 1 |
| No `Index.vue` changes | File Map |
| No `SegmentedControl` extension | Out of scope |
| Light + dark verification | Task 2 Steps 2–3 |
| localStorage persistence unchanged | Task 2 Step 4 |

---

## Out of scope (do not implement)

- Extending `SegmentedControl` with icon support
- Icon-only compact variant
- Repositioning toggle on accounts index
- New Pest/browser tests
