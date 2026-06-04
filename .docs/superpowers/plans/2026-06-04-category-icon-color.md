# Category Icon and Color Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `icon` and `color` to categories with rich starter seed, dedicated create/edit pages, and `CategoryBadge` reused across transactions, filters, transfers, and budget views.

**Architecture:** DB columns + PHP whitelists in `Support/Categories/` + `CategoryFormOptions` for Inertia forms; extend `CategoryResource` and budget row builders; shared Vue `CategoryBadge` with palette/icon pickers on create/edit only.

**Tech Stack:** Laravel 13, Inertia v2, Vue 3, Pest 4, Lucide (`Icon.vue`), Tailwind v3, Sail for tests.

**Spec:** `.docs/superpowers/specs/2026-06-04-category-icon-color-design.md`

**Branch:** `improvement/category-icon-color` (from `develop`)

---

## File map

| Action | Path |
|--------|------|
| Create | `database/migrations/2026_06_04_120000_add_icon_and_color_to_categories.php` |
| Create | `app/Support/Categories/CategoryColors.php` |
| Create | `app/Support/Categories/CategoryIcons.php` |
| Create | `app/Data/Categories/CategoryFormOptions.php` |
| Create | `tests/Unit/Support/Categories/CategoryAppearanceTest.php` |
| Create | `tests/Unit/Support/Categories/CategoryDefaultsTest.php` |
| Create | `resources/js/components/categories/CategoryBadge.vue` |
| Create | `resources/js/components/categories/CategoryColorPicker.vue` |
| Create | `resources/js/components/categories/CategoryIconPicker.vue` |
| Create | `resources/js/pages/categories/Create.vue` |
| Create | `resources/js/pages/categories/Edit.vue` |
| Modify | `app/Support/Categories/CategoryDefaults.php` |
| Modify | `app/Models/Category.php` |
| Modify | `database/factories/CategoryFactory.php` |
| Modify | `app/Http/Requests/Categories/StoreCategoryRequest.php` |
| Modify | `app/Http/Requests/Categories/UpdateCategoryRequest.php` |
| Modify | `app/Actions/Categories/StoreCategory.php` |
| Modify | `app/Actions/Categories/UpdateCategory.php` |
| Modify | `app/Http/Resources/Categories/CategoryResource.php` |
| Modify | `app/Http/Controllers/Categories/CategoryController.php` |
| Modify | `routes/categories.php` |
| Modify | `app/Actions/Budgets/ListMonthlyBudget.php` |
| Modify | `app/Actions/Budgets/ListYearlyBudget.php` |
| Modify | `resources/js/pages/categories/Index.vue` |
| Modify | `resources/js/lib/categories.ts` |
| Modify | `resources/js/pages/transactions/Index.vue` |
| Modify | `resources/js/components/transactions/TransactionsIndexHeaderFilters.vue` |
| Modify | `resources/js/pages/transactions/Create.vue` |
| Modify | `resources/js/pages/transactions/Edit.vue` |
| Modify | `resources/js/pages/transfers/Create.vue` |
| Modify | `resources/js/pages/budget/Monthly.vue` |
| Modify | `resources/js/pages/budget/Yearly.vue` |
| Modify | `resources/js/locales/pl.json`, `resources/js/locales/en.json` |
| Modify | `tests/Feature/Categories/CategoryCrudTest.php` |
| Modify | `tests/Feature/Categories/EnsureUserCategoriesTest.php` |
| Modify | `.docs/prd.md` (§5 Category + FR-C1 starter note) |

---

### Task 1: Color and icon whitelists

**Files:**
- Create: `app/Support/Categories/CategoryColors.php`
- Create: `app/Support/Categories/CategoryIcons.php`
- Create: `tests/Unit/Support/Categories/CategoryAppearanceTest.php`

- [ ] **Step 1: Write failing unit test**

```php
<?php

use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;

test('category colors contains seed hex values', function () {
    expect(CategoryColors::values())->toContain('#ef4444', '#10b981', '#868e96');
});

test('category icons contains seed icon names', function () {
    expect(CategoryIcons::values())->toContain('shopping-cart', 'briefcase', 'piggy-bank', 'tag');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Categories/CategoryAppearanceTest.php`  
Expected: FAIL (classes not found)

- [ ] **Step 3: Implement whitelist classes**

`CategoryColors.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Categories;

final class CategoryColors
{
    /** @return list<string> */
    public static function values(): array
    {
        return [
            '#10b981', '#06b6d4', '#f59e0b', '#8b5cf6', '#6366f1',
            '#ef4444', '#f97316', '#eab308', '#ec4899', '#d946ef',
            '#3b82f6', '#0ea5e9', '#14b8a6', '#fd7e14', '#ff922b',
            '#ff6b6b', '#868e96',
        ];
    }
}
```

`CategoryIcons.php` — `values()` returns ordered list including at minimum:

`tag`, `circle`, `briefcase`, `gift`, `laptop`, `trending-up`, `plus-circle`, `minus-circle`, `shopping-cart`, `car`, `zap`, `film`, `utensils`, `shopping-bag`, `heart`, `book-open`, `shield`, `home`, `smartphone`, `wifi`, `activity`, `repeat`, `wrench`, `plane`, `paw-print`, `scissors`, `piggy-bank`, `wallet`, `coins`, `coffee`, `shirt`, `baby`, `graduation-cap`, `fuel`, `bus`, `train`, `credit-card`, `banknote`, `receipt`, `landmark`, `sparkles`

- [ ] **Step 4: Run test — PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Support/Categories/CategoryColors.php app/Support/Categories/CategoryIcons.php tests/Unit/Support/Categories/CategoryAppearanceTest.php
git commit -m "feat(categories): add icon and color whitelists"
```

---

### Task 2: Migration and model

**Files:**
- Create: `database/migrations/2026_06_04_120000_add_icon_and_color_to_categories.php`
- Modify: `app/Models/Category.php`
- Modify: `database/factories/CategoryFactory.php`

- [ ] **Step 1: Add migration** (defaults for any existing rows during `migrate`)

```php
$table->string('icon', 50)->default('tag');
$table->char('color', 7)->default('#868e96');
```

- [ ] **Step 2: Run migration**

Run: `./vendor/bin/sail artisan migrate`

- [ ] **Step 3: Update model** — add to `$fillable`: `icon`, `color`; PHPDoc `@property string $icon` `@property string $color`

- [ ] **Step 4: Update factory**

```php
'icon' => 'tag',
'color' => '#868e96',
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_04_120000_add_icon_and_color_to_categories.php app/Models/Category.php database/factories/CategoryFactory.php
git commit -m "feat(categories): add icon and color columns"
```

---

### Task 3: Rich starter seed

**Files:**
- Modify: `app/Support/Categories/CategoryDefaults.php`
- Create: `tests/Unit/Support/Categories/CategoryDefaultsTest.php`
- Modify: `tests/Feature/Categories/EnsureUserCategoriesTest.php`

- [ ] **Step 1: Write failing unit test**

```php
<?php

use App\Support\Categories\CategoryDefaults;

test('starter rows include groceries with icon and color', function () {
    $rows = CategoryDefaults::starterRows();
    $groceries = collect($rows)->firstWhere('name', 'Artykuły spożywcze');

    expect($groceries)->not->toBeNull()
        ->and($groceries['icon'])->toBe('shopping-cart')
        ->and($groceries['color'])->toBe('#ef4444');
});

test('starter rows include system savings category', function () {
    $rows = CategoryDefaults::starterRows();
    $savings = collect($rows)->first(fn ($r) => ($r['name'] ?? '') === 'Oszczędności' && ($r['is_system'] ?? false));

    expect($savings)->not->toBeNull()
        ->and($savings)->toHaveKeys(['icon', 'color', 'type', 'sort_order']);
});
```

- [ ] **Step 2: Run test — FAIL**

- [ ] **Step 3: Replace `CategoryDefaults::starterRows()`**

Return **26 rows** from spec/reference:

- 5 income (Wynagrodzenie … Inne przychody) with icons/colors from design spec reference
- 20 expense (Artykuły spożywcze … Inne wydatki)
- 1 system expense: Oszczędności — `is_system: true`, `icon: piggy-bank`, `color: #10b981`, `sort_order: 25`

Each row shape:

```php
['name' => '...', 'type' => CategoryType::Expense, 'icon' => '...', 'color' => '#...', 'sort_order' => N, 'is_system' => false],
```

- [ ] **Step 4: Update `EnsureUserCategories`** — pass `icon` and `color` in `Category::create([...])`

- [ ] **Step 5: Update feature test count**

`EnsureUserCategoriesTest.php`:

```php
expect(Category::where('user_id', $user->id)->count())->toBe(26);
```

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/sail artisan test --compact tests/Unit/Support/Categories/CategoryDefaultsTest.php tests/Feature/Categories/EnsureUserCategoriesTest.php`

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(categories): replace starter seed with rich icon/color catalog"
```

---

### Task 4: Validation, actions, resource (TDD)

**Files:**
- Modify: `app/Http/Requests/Categories/StoreCategoryRequest.php`
- Modify: `app/Http/Requests/Categories/UpdateCategoryRequest.php`
- Modify: `app/Actions/Categories/StoreCategory.php`
- Modify: `app/Actions/Categories/UpdateCategory.php`
- Modify: `app/Http/Resources/Categories/CategoryResource.php`
- Modify: `tests/Feature/Categories/CategoryCrudTest.php`

- [ ] **Step 1: Update failing feature tests**

Replace create payload:

```php
$response = $this->actingAs($user)->post('/categories', [
    'name' => 'Hobby',
    'type' => CategoryType::Expense->value,
    'icon' => 'tag',
    'color' => '#6366f1',
]);
```

Add tests:

```php
test('cannot create category with invalid color', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $this->actingAs($user)->post('/categories', [
        'name' => 'Bad',
        'type' => 'expense',
        'icon' => 'tag',
        'color' => '#000001',
    ])->assertSessionHasErrors('color');
});

test('category resource includes icon and color on index', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $this->actingAs($user)->get('/categories')->assertOk()->assertInertia(fn ($page) => $page
        ->has('categories', 26)
        ->where('categories.0.icon', fn ($v) => is_string($v) && $v !== '')
        ->where('categories.0.color', fn ($v) => is_string($v) && str_starts_with($v, '#'))
    );
});
```

- [ ] **Step 2: Run — FAIL**

- [ ] **Step 3: Implement requests**

`StoreCategoryRequest` add:

```php
use App\Support\Categories\CategoryColors;
use App\Support\Categories\CategoryIcons;
use Illuminate\Validation\Rule;

'icon' => ['required', 'string', Rule::in(CategoryIcons::values())],
'color' => ['required', 'string', Rule::in(CategoryColors::values())],
```

`UpdateCategoryRequest` add (all optional for sort-only PATCH from index):

```php
'icon' => ['sometimes', 'required', 'string', Rule::in(CategoryIcons::values())],
'color' => ['sometimes', 'required', 'string', Rule::in(CategoryColors::values())],
```

- [ ] **Step 4: Update actions**

`StoreCategory` — persist `icon`, `color` from validated.

`UpdateCategory` — when `icon` / `color` keys present, assign them.

- [ ] **Step 5: Update `CategoryResource`**

```php
'icon' => $this->icon,
'color' => $this->color,
```

- [ ] **Step 6: Run Category tests — PASS**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Categories/`

- [ ] **Step 7: Commit**

---

### Task 5: Create/edit controller routes and form options

**Files:**
- Create: `app/Data/Categories/CategoryFormOptions.php`
- Modify: `app/Http/Controllers/Categories/CategoryController.php`
- Modify: `routes/categories.php`
- Modify: `tests/Feature/Categories/CategoryCrudTest.php`

- [ ] **Step 1: Add feature tests for pages**

```php
test('user can view category create page', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);

    $this->actingAs($user)->get(route('categories.create'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('categories/Create')
        ->has('icons')
        ->has('colors')
    );
});

test('user can view category edit page', function () {
    $user = User::factory()->create();
    ensureUserCategories($user);
    $category = Category::where('user_id', $user->id)->first();

    $this->actingAs($user)->get(route('categories.edit', $category))->assertOk()->assertInertia(fn ($page) => $page
        ->component('categories/Edit')
        ->has('category')
        ->where('category.id', $category->id)
    );
});
```

- [ ] **Step 2: Implement `CategoryFormOptions`**

```php
final class CategoryFormOptions
{
    /** @return array{icons: list<array{value: string, label_key: string}>, colors: list<array{value: string}>} */
    public function toArray(): array
    {
        return [
            'icons' => array_map(fn (string $icon) => [
                'value' => $icon,
                'label_key' => 'categories.icons.'.$icon,
            ], CategoryIcons::values()),
            'colors' => array_map(fn (string $hex) => ['value' => $hex], CategoryColors::values()),
        ];
    }
}
```

- [ ] **Step 3: Extend controller**

- `create(CategoryFormOptions $options)` → `Inertia::render('categories/Create', $options->toArray())`
- `edit(Category $category, CategoryFormOptions $options)` → render Edit with `category` resolved from `CategoryResource` + options
- Change `store` redirect: `to_route('categories.index')` with toast (not `back()`)
- Change `update` redirect: `to_route('categories.index')` when full edit; keep `back()` only if you split sort-only endpoint — **prefer**: index sort still uses `patch` with only `sort_order` → `back()` is fine

- [ ] **Step 4: Update routes**

```php
Route::resource('categories', CategoryController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
```

- [ ] **Step 5: Run tests — PASS**

- [ ] **Step 6: Commit**

---

### Task 6: Vue — CategoryBadge and pickers

**Files:**
- Create: `resources/js/components/categories/CategoryBadge.vue`
- Create: `resources/js/components/categories/CategoryColorPicker.vue`
- Create: `resources/js/components/categories/CategoryIconPicker.vue`

- [ ] **Step 1: Implement `CategoryBadge.vue`**

Props: `name?: string`, `icon: string`, `color: string`, `size?: 'sm' | 'md'`, `showName?: boolean` (default true for md).

Tile: `rounded-md` square; background `color` as inline style with alpha via Tailwind arbitrary or `style="backgroundColor: color + '26'"`; icon via `<Icon :name="icon" :style="{ color }" />`.

- [ ] **Step 2: Implement `CategoryColorPicker.vue`**

Props: `colors: { value: string }[]`, `modelValue: string | null`. Emit `update:modelValue`. Grid of buttons; selected = ring-2 ring-primary. No auto-select.

- [ ] **Step 3: Implement `CategoryIconPicker.vue`**

Props: `icons: { value: string; label_key: string }[]`, `modelValue: string`. Grid; default model `tag`.

- [ ] **Step 4: Commit** (no PHP test; verified in Task 7)

```bash
git commit -m "feat(ui): add category badge and appearance pickers"
```

---

### Task 7: Create and Edit pages

**Files:**
- Create: `resources/js/pages/categories/Create.vue`
- Create: `resources/js/pages/categories/Edit.vue`
- Modify: `resources/js/locales/pl.json`, `resources/js/locales/en.json`

- [ ] **Step 1: Add i18n keys** (PL + EN)

`categories.create.title`, `categories.edit.title`, `categories.fields.icon`, `categories.fields.color`, `categories.fields.preview`, `categories.validation.colorRequired`, plus `categories.icons.<name>` for each whitelist icon (can use humanized fallback in picker via `t(label_key)`).

- [ ] **Step 2: `Create.vue`** (mirror `accounts/Create.vue` structure)

Props: `icons`, `colors`. Form: `name`, `type`, `icon: 'tag'`, `color: null`. Client guard: block submit if `color === null` with local error. POST `route('categories.store')`. Live preview `CategoryBadge` with `name || t('categories.fields.preview')`.

- [ ] **Step 3: `Edit.vue`**

Props: `category`, `icons`, `colors`. Prefill form. Disable `type` when `category.has_transactions` if prop provided — **or** omit type field on edit (type change already blocked server-side). PATCH `route('categories.update', category.id)`.

Optional: pass `has_transactions` bool from controller:

```php
'has_transactions' => $category->transactions()->exists(),
```

- [ ] **Step 4: Manual smoke** — visit `/categories/create` (Sail + `npm run dev`)

- [ ] **Step 5: Commit**

---

### Task 8: Refactor categories Index

**Files:**
- Modify: `resources/js/pages/categories/Index.vue`

- [ ] **Step 1: Remove** inline create form, `createForm`, inline name edit (`editingId` / `editingName` / `saveName` / `startEdit`).

- [ ] **Step 2: Add** header button `<Link :href="route('categories.create')">`.

- [ ] **Step 3: Row UI** — `CategoryBadge` + name (link to edit via `<Link :href="route('categories.edit', cat.id)">`) + system label + sort buttons + delete.

- [ ] **Step 4: Extend `Category` type** with `icon`, `color`.

- [ ] **Step 5: Commit**

---

### Task 9: Transaction and filter touchpoints

**Files:**
- Modify: `resources/js/lib/categories.ts`
- Modify: `resources/js/pages/transactions/Index.vue`
- Modify: `resources/js/components/transactions/TransactionsIndexHeaderFilters.vue`
- Modify: `resources/js/pages/transactions/Create.vue`
- Modify: `resources/js/pages/transactions/Edit.vue`
- Modify: `resources/js/pages/transfers/Create.vue`

- [ ] **Step 1: Extend `CategoryOption`** with `icon`, `color`.

- [ ] **Step 2: `transactions/Index.vue`**

Update `TransactionCategory` type. Desktop cell:

```vue
<CategoryBadge
  v-if="tx.category"
  :name="tx.category.name"
  :icon="tx.category.icon"
  :color="tx.category.color"
  size="md"
/>
```

Mobile card: same badge on category line.

- [ ] **Step 3: `TransactionsIndexHeaderFilters.vue`**

Extend `Category` type. Build `categoriesById` Map. Use slots:

```vue
<template #trigger-leading="{ selected }">
  <CategoryBadge v-if="selected?.value && categoriesById.get(selected.value)" ... size="sm" :show-name="false" />
</template>
```

- [ ] **Step 4: Create/Edit/Transfer** — category `DropdownSelect` with `#option-leading` and `#trigger-leading` using `categoriesById`.

- [ ] **Step 5: Run transaction feature tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Transactions/TransactionStoreTest.php`

- [ ] **Step 6: Commit**

---

### Task 10: Budget rows

**Files:**
- Modify: `app/Actions/Budgets/ListMonthlyBudget.php`
- Modify: `app/Actions/Budgets/ListYearlyBudget.php`
- Modify: `resources/js/pages/budget/Monthly.vue`
- Modify: `resources/js/pages/budget/Yearly.vue`
- Modify: `tests/Feature/Budgets/MonthlyBudgetTest.php` (assert icon/color on first row if needed)

- [ ] **Step 1: Add to row arrays** in both List*Budget actions:

```php
'icon' => $category->icon,
'color' => $category->color,
```

- [ ] **Step 2: Update Vue `BudgetRow` type** and first `<td>`:

```vue
<CategoryBadge :name="row.name" :icon="row.icon" :color="row.color" size="md" />
```

- [ ] **Step 3: Run budget tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Budgets/`

- [ ] **Step 4: Commit**

---

### Task 11: PRD sync and final verification

**Files:**
- Modify: `.docs/prd.md`

- [ ] **Step 1: Update §5 Category table** — add `icon`, `color` rows.

- [ ] **Step 2: Update FR-C1** — mention rich starter set and appearance on create/edit.

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 4: Run domain tests**

Run: `./vendor/bin/sail artisan test --compact tests/Feature/Categories/ tests/Feature/Transactions/TransactionStoreTest.php tests/Feature/Budgets/`

- [ ] **Step 5: Optional PHPStan** (if types touched heavily)

Run: `./vendor/bin/phpstan analyse`

- [ ] **Step 6: Commit**

```bash
git commit -m "docs: extend PRD for category icon and color"
```

---

## Plan self-review (spec coverage)

| Spec requirement | Task |
|------------------|------|
| DB `icon`, `color` | Task 2 |
| Whitelists | Task 1 |
| Rich seed + Oszczędności | Task 3 |
| No user backfill | Task 2 migration defaults only |
| CategoryFormOptions | Task 5 |
| create/edit pages | Tasks 5, 7 |
| CategoryResource fields | Task 4 |
| Index refactor | Task 8 |
| Transaction table + filter | Task 9 |
| Transfer/transaction forms | Task 9 |
| Budget rows | Task 10 |
| Color required | Task 7 client + Task 4 server |
| Icon default `tag` | Task 7 |
| PRD update | Task 11 |

No placeholders remain in task steps.

---

## Reference: seed row (income example)

```php
['name' => 'Wynagrodzenie', 'type' => CategoryType::Income, 'icon' => 'briefcase', 'color' => '#10b981', 'sort_order' => 1, 'is_system' => false],
```

Full list: copy from user reference config in brainstorming + system **Oszczędności** row documented in spec.
