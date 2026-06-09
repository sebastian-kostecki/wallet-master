# Category icon and color — design spec

**Status:** Approved in brainstorming (2026-06-04)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Canonical requirements target:** `.docs/prd.md` (FR-C1, §5 Category)

## Summary

Extend transaction categories with **Lucide icon** (kebab-case) and **hex color** from a fixed palette. Replace the minimal starter seed with a rich Polish catalog (income + expense) for **new users only**. Add dedicated **create/edit** Inertia pages (pattern: accounts/transactions). Reuse a single **`CategoryBadge`** component everywhere categories appear (category list, transaction table, filters, transaction/transfer forms, budget tables).

Existing test users are **not** backfilled (manual DB reset in dev is acceptable).

## Decisions log

| Topic | Decision |
|-------|----------|
| Existing users | **No migration** — test data only; new seed applies on fresh `EnsureUserCategories` |
| Starter catalog | **Replace** `CategoryDefaults` with rich set from reference app + system **Oszczędności** |
| Edit UX | **Dedicated pages** `categories/create`, `categories/{id}/edit` (not inline on index) |
| Color | **Fixed palette** (~20 swatches); value stored as `#RRGGBB` |
| Color on save | **Required** — user must select a swatch; no implicit default |
| Icon | **Curated whitelist** (~50 Lucide names); UI default highlight **`tag`** until user picks another |
| Architecture | **Approach 1:** DB columns + `CategoryFormOptions` + shared Vue components + `CategoryResource` |

## PRD changes (outline)

### §5 Model — Category

Add fields:

| Field | Description |
|-------|-------------|
| `icon` | Lucide icon name (kebab-case), validated against whitelist |
| `color` | Hex color `#RRGGBB`, validated against palette whitelist |

### §1 Słownik — Kategoria

Extend: category has display order, **icon**, and **color** for consistent UI across lists, forms, and budget.

### FR-C1 — CRUD kategorii + zestaw startowy

- Create/edit includes **name**, **type**, **icon**, **color** (dedicated pages).
- Rich starter set on first `EnsureUserCategories` (see Seed below).
- System category **Oszczędności** remains (`is_system: true`); appearance editable; delete still blocked.

**Events:** unchanged (`category_created`, `category_updated`).

---

## Backend

### Migration

Add to `categories`:

| Column | Type | Notes |
|--------|------|-------|
| `icon` | `string(50)` | not null |
| `color` | `char(7)` | not null, `#RRGGBB` |

No data migration for existing rows in test environments.

### Validation

`StoreCategoryRequest` / `UpdateCategoryRequest`:

- `icon`: `required`, `string`, `Rule::in(CategoryIcon::values())` (or Support whitelist class)
- `color`: `required`, `string`, `regex:/^#[0-9A-Fa-f]{6}$/`, `Rule::in(CategoryColor::values())`
- `name`, `type`: existing rules
- `UpdateCategoryRequest`: `type` prohibited when category has transactions (existing behavior)

### `CategoryFormOptions` (`Data/Categories/`)

Static props for create/edit pages:

```php
// icons: list<{ value: string, label_key: string }>  // e.g. categories.icons.shopping-cart
// colors: list<{ value: string }>  // hex only, UI renders swatch
```

Whitelist lives in PHP (`Support/Categories/CategoryIcons.php`, `CategoryColors.php`) — single source for validation and form options.

### Palette (whitelist)

Derived from reference seed (unique hex values):

`#10b981`, `#06b6d4`, `#f59e0b`, `#8b5cf6`, `#6366f1`, `#ef4444`, `#f97316`, `#eab308`, `#ec4899`, `#d946ef`, `#3b82f6`, `#0ea5e9`, `#14b8a6`, `#fd7e14`, `#ff922b`, `#ff6b6b`, `#868e96`

### Icon whitelist (curated)

Includes all icons used in seed plus defaults:

`tag`, `circle`, `briefcase`, `gift`, `laptop`, `trending-up`, `plus-circle`, `minus-circle`, `shopping-cart`, `car`, `zap`, `film`, `utensils`, `shopping-bag`, `heart`, `book-open`, `shield`, `home`, `smartphone`, `wifi`, `activity`, `repeat`, `wrench`, `plane`, `paw-print`, `scissors`, `piggy-bank`, `wallet`, `coins`, `coffee`, `shirt`, `baby`, `graduation-cap`, `fuel`, `bus`, `train`, `credit-card`, `banknote`, `receipt`, `landmark`, `sparkles`

(Exact list finalized in implementation; must be superset of seed icons.)

### Seed — `CategoryDefaults::starterRows()`

Replace current 9 rows with:

1. **Income** (5): Wynagrodzenie, Bonus, Praca freelance, Odsetki, Inne przychody — names/colors/icons per reference config (`sort_order` 1–5).
2. **Expense** (20): Artykuły spożywcze … Inne wydatki — per reference config (`sort_order` 1–20).
3. **System expense**: **Oszczędności** — `is_system: true`, e.g. `piggy-bank` + `#10b981`, `sort_order` after main expense block (e.g. 25) — required for PRD savings flow and import fallback ordering.

Each row: `name`, `type`, `color`, `icon`, `sort_order`, `is_system`.

### Actions & controller

- `StoreCategory` / `UpdateCategory`: persist `icon`, `color`.
- `CategoryResource`: expose `icon`, `color`.
- `CategoryController`:
  - `create(CategoryFormOptions)` → `categories/Create`
  - `edit(Category, CategoryFormOptions)` → `categories/Edit`
  - `store` / `update` → redirect to `categories.index` with toast (not `back()` from create form on index)
- Routes: extend resource with `create`, `edit`; keep estimate routes unchanged.

### Budget actions

`ListMonthlyBudget` / `ListYearlyBudget` row shape adds:

```php
'icon' => $category->icon,
'color' => $category->color,
```

---

## Frontend

### New pages

| Page | Route | Notes |
|------|-------|-------|
| `categories/Create.vue` | `categories.create` | `useForm`: name, type, icon (default `tag`), color (null until picked) |
| `categories/Edit.vue` | `categories.edit` | Prefill from category; type disabled if has transactions |

Layout: `AppLayout`, breadcrumbs, Cancel → index, Save.

### New components (`components/categories/`)

| Component | Role |
|-----------|------|
| `CategoryBadge.vue` | Colored tile (background = `color` at ~15% opacity) + `Icon` in full `color`; optional `name`; sizes `sm` / `md` |
| `CategoryColorPicker.vue` | Swatch grid from `colors` prop; `v-model` hex |
| `CategoryIconPicker.vue` | Icon button grid; `v-model` kebab name; initial selection `tag` |

Live preview on create/edit using `CategoryBadge` (md).

### `categories/Index.vue`

- Remove inline create form and inline name edit.
- Header: **Add category** button → `route('categories.create')`.
- Row: `CategoryBadge` + name + system label + sort arrows + delete (unchanged rules).
- Row click or edit affordance → `categories.edit`.

### Shared type — `lib/categories.ts`

```ts
export type CategoryOption = {
  id: number;
  name: string;
  type: string;
  sort_order: number;
  icon: string;
  color: string;
};
```

### Touchpoints

| Location | UI change |
|----------|-----------|
| `transactions/Index.vue` | Table column + mobile card: `CategoryBadge` md |
| `TransactionsIndexHeaderFilters.vue` | Category filter dropdown: `trigger-leading` / `option-leading` slots with badge |
| `transactions/Create.vue`, `Edit.vue` | Category `DropdownSelect` slots with badge |
| `transfers/Create.vue` | Same for expense categories |
| `budget/Monthly.vue`, `Yearly.vue` | First column: badge + name |

`DropdownSelect` unchanged globally; parents pass category metadata via `categoriesById` Map in slot handlers or extended local option type.

### Icon rendering

Reuse `components/Icon.vue` (Lucide dynamic import). Invalid icon names must not reach UI — enforced by API whitelist.

### i18n (`locales/pl.json`, `en.json`)

- Page titles: `categories.create.title`, `categories.edit.title`
- Fields: `categories.fields.color`, `categories.fields.icon`, `categories.fields.preview`
- Validation: `categories.validation.colorRequired`
- Optional per-icon `categories.icons.<name>` for `aria-label` from `label_key`

---

## Data flow

```text
Create/Edit page
  → StoreCategoryRequest (icon, color validated)
  → StoreCategory / UpdateCategory
  → categories table

Index / Transactions / Budget
  → CategoryResource (icon, color)
  → CategoryBadge in Vue
```

---

## Testing

| Layer | Cases |
|-------|--------|
| Feature `Categories` | Create with valid icon/color; reject invalid hex/icon; edit updates appearance |
| Feature `EnsureUserCategories` | New user gets rich seed including Oszczędności with icon/color |
| Feature `Transactions` | Index nested `category` includes `icon`, `color` |
| Unit | `CategoryIcons` / `CategoryColors` contain seed values; `CategoryDefaults` row count |

Run: `./vendor/bin/sail artisan test --compact --filter=Categories` (and affected Transaction tests).

---

## Out of scope

- Backfill / migration for existing category rows in production-like data
- Custom hex outside palette
- Full Lucide catalog picker
- AI-suggested icons/colors
- Changing category appearance from transaction list inline

---

## Implementation notes (for plan)

1. Migration + model fillable/casts  
2. Support whitelist classes + `CategoryFormOptions`  
3. Replace `CategoryDefaults` + update `EnsureUserCategories` tests  
4. Requests, Actions, Resource, Controller routes  
5. Vue components + Create/Edit + Index refactor  
6. Touchpoints (transactions, transfers, budget backend rows)  
7. i18n + Pint + scoped tests  
8. PRD §5 / FR-C1 bullet update (separate doc commit or same PR per team habit)
