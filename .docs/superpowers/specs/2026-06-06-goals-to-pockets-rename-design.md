# Goals → Pockets rename — design spec

**Status:** Approved in brainstorming (2026-06-06)  
**Supersedes (naming only):** all user-facing and code references to **Goals** / **Cele**; prior goal specs remain authoritative for **business logic** until PRD is updated:
- `.docs/superpowers/specs/2026-06-03-budget-goals-ux-design.md`
- `.docs/superpowers/specs/2026-06-04-goals-target-model-design.md`
- `.docs/superpowers/specs/2026-06-05-goals-currency-design.md`

**Canonical requirements target:** `.docs/prd.md` (delta: FR-G* → FR-P*, terminology)  
**Next step:** Implementation plan (`.docs/superpowers/plans/2026-06-06-goals-to-pockets-rename.md`)

## Summary

Rename the savings-envelope feature from **Goals / Cele** to **Pockets / Kieszenie** across the full stack: database, PHP domain, Vue/Inertia, i18n, PRD, telemetry, and tests. **No change to business rules** — target amount, planning modes, archiving, currency, transfer assignment (Savings), monthly budget metrics, and flow A remain identical to the shipped goal model.

Pre-production: **no backward compatibility** (no redirects from `/goals`, no dual telemetry, no DB aliases).

## Problem

The product models **savings envelopes** (koperty / kieszenie): money earmarked via transfers to `Savings` accounts. The name **Goals / Cele** suggests life goals or SMART targets and diverges from the PRD dictionary term **„koperta oszczędnościowa”**. After the 2026-06-04 target-model refactor, behaviour is envelope-first; naming lagged behind.

Users should read **„Kieszenie”** (PL) and **„Envelopes”** (EN) — allocation compartments, not abstract goals.

## Decisions log

| Topic | Decision |
|-------|----------|
| Scope | **Full stack rename** (option B): DB, code, routes, UI, PRD, telemetry, tests |
| User-facing PL | **Kieszenie** (sidebar, forms, budget section, empty states) |
| User-facing EN | **Envelopes** / “savings envelope” in descriptions where needed |
| Code / domain (Variant A) | **`Pockets`** — plural domain segment everywhere |
| Model / table | `Pocket` / `pockets` |
| Transaction FK | `pocket_id` (replaces `goal_id`) |
| Routes | `/pockets`, named routes `pockets.*` |
| Backward compatibility | **None** — pre-production; remove all `goal*` routes, keys, events |
| Business logic | **Unchanged** — same validation, metrics, FR semantics |
| PRD identifiers | **FR-G1–G5 → FR-P1–P5**; telemetry `goal_*` → `pocket_*` |
| Implementation shape | **Single atomic PR** on branch `improvement/goals-to-pockets` |
| Completed badge copy | PL **„Ukończona”** (feminine, kieszeń); EN **„Complete”** |

## Naming map

| Layer | Before | After |
|-------|--------|-------|
| Domain folder | `Goals` | `Pockets` |
| Model | `Goal` | `Pocket` |
| Table | `goals` | `pockets` |
| FK on `transactions` | `goal_id` | `pocket_id` |
| Enum | `GoalPlanningMode` | `PocketPlanningMode` |
| Support | `App\Support\Goals\*` | `App\Support\Pockets\*` |
| Routes file | `routes/goals.php` | `routes/pockets.php` |
| HTTP paths | `/goals`, `/goals/create`, … | `/pockets`, … |
| Route names | `goals.index`, … | `pockets.index`, … |
| Inertia pages | `pockets/` ← `goals/` | `resources/js/pages/pockets/*` |
| Components | `components/goals/*` | `components/pockets/*` |
| i18n root key | `goals` | `pockets` |
| Policy | `GoalPolicy` | `PocketPolicy` |
| Concern | `ValidatesGoalId` | `ValidatesPocketId` |
| Budget prop | `goal_rows` | `pocket_rows` |
| Inertia props (lists) | `goals` | `pockets` |
| Factory | `GoalFactory` | `PocketFactory` |
| Tests | `tests/Feature/Goals/*` | `tests/Feature/Pockets/*` |

Class/file renames follow the same pattern (`GoalController` → `PocketController`, `GoalBalance` → `PocketBalance`, etc.).

## Data model

### Migration (single)

1. `Schema::rename('goals', 'pockets')`.
2. On `transactions`: rename column `goal_id` → `pocket_id`; recreate FK to `pockets.id` and index if required by driver.
3. No data transformation — row contents unchanged.

Existing columns on `pockets` (formerly `goals`): `user_id`, `name`, `icon`, `color`, `sort_order`, `currency_id`, `target_amount`, `planning_mode`, `monthly_contribution`, `target_date`, `is_archived`, timestamps — **unchanged**.

### Pocket (entity)

Semantically identical to former `Goal` (see PRD §5 and `2026-06-04-goals-target-model-design.md`). Relation: `User` 1—N `Pockets`; `Pocket` 1—N `Transactions` (optional `pocket_id`); `Pocket` N—1 `Currency`.

### Transaction extension

- `pocket_id` nullable FK → `pockets`.
- **FR-P3** (was FR-G3): required on both legs when transfer involves `Savings`; prohibited on ROR↔ROR.
- **FR-P4** (was FR-G4): optional on P&L transactions.
- Currency match rules unchanged (pocket currency = Savings account / transaction currency).

## Backend (Variant A)

Replace domain **`Goals`** with **`Pockets`**:

| Layer | Paths |
|-------|--------|
| Actions | `App\Actions\Pockets\` — `ListPockets`, `StorePocket`, `UpdatePocket`, `DeletePocket`, `ReorderPockets` |
| Controller | `App\Http\Controllers\Pockets\PocketController` |
| Requests | `App\Http\Requests\Pockets\*` |
| Resources | `App\Http\Resources\Pockets\PocketResource` |
| Data | `App\Data\Pockets\PocketFormOptions` |
| Support | `App\Support\Pockets\PocketBalance`, `PocketPlanningProjection`, `PocketTransactionMetrics` |
| Policy | `App\Policies\PocketPolicy` |

**Consumers to update:**

- `CreateTransfer`, `UnlinkTransfer` — `pocket_id` on both legs.
- `StoreTransaction`, `UpdateTransaction`, form requests — `ValidatesPocketId`.
- `ListMonthlyBudget` — `buildPocketRows()`, getter `getPocketRows()`.
- `TransferController`, `TransactionController` — pass `pockets` prop where `goals` was used.
- `TransactionResource`, `TransactionEditResource` — nested `pocket` (or `pocket_id` + relation).

Register routes in `routes/pockets.php`; include from `routes/web.php`; **delete** `routes/goals.php`.

**Architecture rules:** Action getters, no Resources inside Actions, index pattern unchanged.

## Frontend

| Area | Change |
|------|--------|
| Pages | `resources/js/pages/pockets/Index.vue`, `Create.vue`, `Edit.vue` |
| Components | `PocketBadge`, `PocketProgressBar`, `PocketArchiveDialog` |
| Sidebar | Label PL **Kieszenie**, EN **Envelopes** → `pockets.index` |
| Transfer / transaction forms | Field `pocket_id`; labels from `pockets.*` i18n |
| Monthly budget | Section title + table keyed on `pocket_rows`; link „Zarządzaj kieszeniami” / „Manage envelopes” |
| Dashboard, Welcome, marketing copy | Replace goals wording with pockets/envelopes |
| Ziggy | `route('pockets.*')` only |

Remove all `goals` i18n keys; add `pockets.*` with approved PL/EN copy.

## PRD and documentation delta

Update `.docs/prd.md`:

- Dictionary: **Kieszenie (pocket)** — koperta oszczędnościowa; drop „Cel (goal)” as primary term.
- All FR-G* sections → FR-P* (same acceptance criteria, updated field names `pocket_id`).
- §3.4 telemetry table: `pocket_created`, `pocket_updated`, …, `pocket_assigned_transfer`, `pocket_assigned_transaction`.
- §7 sidebar: **Kieszenie**; screen list `/pockets`.
- §3.2 out-of-scope bullets referencing `goal_id` → `pocket_id`.

Update `.docs/checklist.md` §19 title and file references to Pockets.

Add **Superseded by 2026-06-06-goals-to-pockets-rename-design.md** note at top of three prior goal specs (logic references remain valid with `Pocket`/`pocket_id` substituted mentally).

## Telemetry

| Before | After |
|--------|-------|
| `goal_created` | `pocket_created` |
| `goal_updated` | `pocket_updated` |
| `goal_deleted` | `pocket_deleted` |
| `goal_archived` | `pocket_archived` |
| `goal_unarchived` | `pocket_unarchived` |
| `goal_assigned_transfer` | `pocket_assigned_transfer` |
| `goal_assigned_transaction` | `pocket_assigned_transaction` |

Update emit sites in Actions/Controllers; no dual logging.

## Testing

| Suite | Action |
|-------|--------|
| `tests/Feature/Pockets/*` | Rename from Goals; routes `pockets.*`; model `Pocket` |
| `tests/Feature/Transfers/TransferPocketTest.php` | Rename from `TransferGoalTest` |
| `tests/Feature/Transactions/TransactionPocketTest.php` | Rename from `TransactionGoalTest` |
| `tests/Feature/Budgets/MonthlyBudgetTest.php` | Assert `pocket_rows` |
| `tests/Unit/Support/Pockets/*` | Rename balance/projection tests |
| Migration tests | Rename; assert `pockets` table and `pocket_id` column |

**Verification:**

1. `vendor/bin/pint --dirty --format agent`
2. `./vendor/bin/sail artisan test --compact --filter=Pocket`
3. `./vendor/bin/sail artisan test --compact tests/Feature/Transfers/TransferPocketTest.php tests/Feature/Transactions/TransactionPocketTest.php tests/Feature/Budgets/MonthlyBudgetTest.php`
4. `rg -i '\bgoal' app/ resources/js routes/ tests/ database/ --glob '!*.md'` → **zero** matches (excluding historical migrations if kept as-is; prefer renaming migration files only if not yet deployed — pre-prod may rename migration stubs in place or add rename migration only)

**Note:** Already-run migrations in dev DB: new migration renames table/column; do not edit old migration filenames on shared environments without team agreement — pre-prod may use fresh migrate.

## Out of scope

- New pocket features (yearly rollup, multi-pocket transfer, auto-assign on import).
- Logic changes to planning, balance, or archive rules.
- Redirects from `/goals` or alias route names.
- English UI label **“Pockets”** (use **Envelopes** per decision).
- Polish **„Koperty”** as primary nav label (use **Kieszenie**).

## Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Missed `goal` reference | Final grep gate; CI grep in plan optional |
| Broken Inertia props | Feature tests for pockets index, transfer create, monthly budget |
| Feminine/plural Polish copy | Review `pl.json` for adjective agreement (Ukończona, zarchiwizowana) |
| Large PR review fatigue | Implementation plan with ordered file checklist |

## Implementation checklist (high level)

1. Migration: `goals` → `pockets`, `goal_id` → `pocket_id`.
2. PHP: move/rename all `Goals` namespace to `Pockets`.
3. Routes + controller registration.
4. Vue: pages, components, sidebar, forms.
5. i18n PL/EN.
6. PRD + checklist + supersede notes on old specs.
7. Tests rename + green suite.
8. Pint.
