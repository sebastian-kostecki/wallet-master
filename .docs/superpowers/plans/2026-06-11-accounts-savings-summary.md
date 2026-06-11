# Accounts Savings Summary Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show total balance of PLN savings accounts as a second line in the accounts index summary card.

**Architecture:** Extend `AccountsSummaryCard.vue` with a client-side computed that filters `type === 'savings'` and `currency.code === 'PLN'`, reusing the existing `parseAmount` and `Intl.NumberFormat` logic. Add i18n label only — no backend or API changes.

**Tech Stack:** Vue 3, TypeScript, vue-i18n, Tailwind CSS 3, shadcn-vue `Card`.

**Spec:** `.docs/superpowers/specs/2026-06-11-accounts-savings-summary-design.md`  
**Reference component:** `resources/js/components/accounts/AccountsSummaryCard.vue`  
**Suggested branch:** `improvement/accounts-savings-summary`

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `resources/js/components/accounts/AccountsSummaryCard.vue` | Savings PLN total computed + template block |
| Modify | `resources/js/locales/pl.json` | `accounts.summary.savingsPln` label |
| Modify | `resources/js/locales/en.json` | `accounts.summary.savingsPln` label |
| Unchanged | `resources/js/pages/accounts/Index.vue` | Already passes accounts with `type` |
| Unchanged | Backend (`AccountController`, `AccountResource`) | `type` already exposed |

---

## Task 1: Add i18n keys

**Files:**
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add Polish label**

In `resources/js/locales/pl.json`, inside `accounts.summary`, add `savingsPln` after `totalPln`:

```json
        "summary": {
            "title": "Podsumowanie",
            "description": "Suma sald wszystkich kont w PLN.",
            "totalPln": "Suma sald (PLN)",
            "savingsPln": "Oszczędności (PLN)",
            "countPln": "Liczba kont (PLN): {count}",
```

- [ ] **Step 2: Add English label**

In `resources/js/locales/en.json`, inside `accounts.summary`, add `savingsPln` after `totalPln`:

```json
        "summary": {
            "title": "Summary",
            "description": "Sum of balances for all PLN accounts.",
            "totalPln": "Total balance (PLN)",
            "savingsPln": "Savings (PLN)",
            "countPln": "Number of accounts (PLN): {count}",
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "$(cat <<'EOF'
feat(accounts): add savings summary i18n keys

EOF
)"
```

---

## Task 2: Extend AccountsSummaryCard

**Files:**
- Modify: `resources/js/components/accounts/AccountsSummaryCard.vue`

- [ ] **Step 1: Replace component with savings total logic**

Replace the entire contents of `resources/js/components/accounts/AccountsSummaryCard.vue` with:

```vue
<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Currency = {
    code: string;
};

type Account = {
    type: string;
    current_balance: string;
    currency: Currency;
};

const props = defineProps<{
    accounts: Account[];
}>();

const { t } = useI18n();

const money = new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function parseAmount(input: string): number | null {
    const normalized = input.trim().replace(/\s/g, '').replace(',', '.');
    const parsed = Number(normalized);

    if (Number.isNaN(parsed)) {
        return null;
    }

    return parsed;
}

const accountsInPln = computed(() => props.accounts.filter((a) => a.currency.code === 'PLN'));

const savingsAccountsInPln = computed(() => accountsInPln.value.filter((a) => a.type === 'savings'));

const parsedBalances = computed(() =>
    accountsInPln.value.map((a) => ({
        value: parseAmount(a.current_balance),
    })),
);

const parsedSavingsBalances = computed(() =>
    savingsAccountsInPln.value.map((a) => ({
        value: parseAmount(a.current_balance),
    })),
);

const invalidBalancesCount = computed(() => parsedBalances.value.filter((b) => b.value === null).length);

const totalBalance = computed(() => parsedBalances.value.reduce((sum, b) => sum + (b.value ?? 0), 0));

const savingsTotalBalance = computed(() => parsedSavingsBalances.value.reduce((sum, b) => sum + (b.value ?? 0), 0));

const formattedTotal = computed(() => `${money.format(totalBalance.value)} ${t('currency.defaultSymbol')}`);

const formattedSavingsTotal = computed(() => `${money.format(savingsTotalBalance.value)} ${t('currency.defaultSymbol')}`);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('accounts.summary.title') }}</CardTitle>
            <CardDescription>{{ t('accounts.summary.description') }}</CardDescription>
        </CardHeader>

        <CardContent class="grid gap-3">
            <div>
                <p class="text-xs text-muted-foreground">{{ t('accounts.summary.totalPln') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ formattedTotal }}</p>
            </div>

            <div>
                <p class="text-xs text-muted-foreground">{{ t('accounts.summary.savingsPln') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ formattedSavingsTotal }}</p>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
                <p>{{ t('accounts.summary.countPln', { count: accountsInPln.length }) }}</p>
                <p v-if="invalidBalancesCount > 0">{{ t('accounts.summary.invalidSkipped') }}</p>
                <p v-else-if="accountsInPln.length === 0">{{ t('accounts.summary.addToSeeSummary') }}</p>
            </div>
        </CardContent>
    </Card>
</template>
```

Notes:
- `savingsAccountsInPln` is a subset of `accountsInPln` — savings in non-PLN currencies are excluded (shows `0,00 zł`).
- Invalid savings balances contribute `0` to the sum; the existing `invalidSkipped` footer still covers all PLN accounts.
- No savings account count line (per spec).

- [ ] **Step 2: Run ESLint on the changed file**

Run:

```bash
./vendor/bin/sail npm run lint -- resources/js/components/accounts/AccountsSummaryCard.vue
```

If Sail is not running, use host:

```bash
npm run lint -- resources/js/components/accounts/AccountsSummaryCard.vue
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/accounts/AccountsSummaryCard.vue
git commit -m "$(cat <<'EOF'
feat(accounts): show PLN savings total in summary card

Add client-side aggregation for savings accounts below the overall PLN total.
EOF
)"
```

---

## Task 3: Manual verification

**Files:** none (browser smoke test)

- [ ] **Step 1: Start frontend dev server (if not already running)**

Run:

```bash
./vendor/bin/sail npm run dev
```

Or use existing `composer run dev` session.

- [ ] **Step 2: Savings PLN sum**

1. Ensure user has at least two PLN savings accounts (e.g. 1000.00 and 2500.50).
2. Open `/accounts`.
3. Confirm **Oszczędności (PLN)** shows `3 500,50 zł` (or matching manual sum).
4. Confirm **Suma sald (PLN)** still includes checking + savings.

Expected: savings line equals sum of savings-only PLN balances.

- [ ] **Step 3: Checking excluded from savings line**

1. Note a checking account PLN balance (e.g. 500.00).
2. Confirm it appears in overall total but **not** in savings line.

Expected: savings total unchanged when only checking balance differs.

- [ ] **Step 4: No PLN savings edge cases**

1. User with only checking PLN accounts → savings line shows `0,00 zł`.
2. User with savings in EUR only → savings line shows `0,00 zł`.
3. Footer `Liczba kont (PLN)` still counts all PLN accounts (checking + savings).

Expected: savings line always visible; zero when no qualifying accounts.

- [ ] **Step 5: No PHP test run required**

Per spec: no backend changes. Skip `./vendor/bin/sail artisan test` unless other work is in the same branch.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| PLN-only filter for savings | Task 2 Step 1 (`savingsAccountsInPln`) |
| `type === 'savings'` filter | Task 2 Step 1 |
| Second line below overall total | Task 2 Step 1 (template) |
| No savings account count | Task 2 Step 1 (no count in template) |
| Same parse/format logic | Task 2 Step 1 (`parseAmount`, `money.format`) |
| No savings → `0,00 zł` | Task 2 Step 1 (`reduce` on empty array) |
| i18n `savingsPln` pl + en | Task 1 |
| No backend changes | File Map |
| Manual verification scenarios | Task 3 |

---

## Out of scope (do not implement)

- Per-currency savings breakdown
- Checking-only subtotal
- Backend `summary` prop or new Action
- Savings account count
- New Pest/browser tests
