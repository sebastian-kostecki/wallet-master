# Page header, breadcrumbs and tabs — design spec

**Date:** 2026-08-25
**Status:** Approved in brainstorming
**Scope:** Frontend (Inertia Vue) only — app shell + pages under `AppLayout`
**Supersedes:** `.docs/superpowers/specs/2026-08-20-remove-breadcrumbs-design.md`
**Depends on:** `.docs/superpowers/specs/2026-08-11-openship-page-chrome-design.md` (chrome row is replaced by `PageHeader`)

## Goal

Give every authenticated page one coherent header block — breadcrumbs, `h1`, description, primary actions and an optional tab strip — derived from a single central registry instead of being hand-declared on each page. Unify the two ad-hoc navigation switchers (pockets filter, budget monthly/yearly) behind one navigation-tabs component, and provide a reusable shell for table pages.

## Context

Today the same label is written up to three times per page: once in `computed<BreadcrumbItem[]>`, once in `<Head :title>`, and once in the sidebar nav. Most pages have **no `h1`** at all — the last breadcrumb doubles as the visible title. Three different switchers exist with three different implementations:

| Place | Mechanism | Markup |
|-------|-----------|--------|
| `pockets/Index.vue` | query param `?filter=` on the same route | pill group, `bg-muted p-1` |
| `budget/Monthly.vue` / `Yearly.vue` | two separate routes | `<Button>` + `<Link>` pairs |
| `accounts/Index.vue` | client-side presentation toggle | grid/list buttons |

The previously approved spec `2026-08-20-remove-breadcrumbs-design.md` (never implemented) planned to delete breadcrumbs entirely. It is superseded by this document: breadcrumbs stay and are redesigned. The dead-code cleanup it planned (`AppHeaderLayout.vue`, `AppHeader.vue`) is carried over here.

## Decisions log

| Topic | Decision |
|-------|----------|
| Superseded spec | Breadcrumbs stay; `2026-08-20-remove-breadcrumbs-design.md` marked `Superseded` |
| Tabs scope | Unify existing switchers only: pockets filter + budget monthly/yearly |
| Tab ↔ path | Active tab is the **leaf** of the breadcrumb trail and enters the document title |
| Tab ↔ `h1` | `h1` shows the **node** title (`Kieszenie`, `Budżet`), not the tab label |
| Source of truth | Central frontend registry keyed by logical node id, resolved from the current route name |
| API shape | Layout renders the header automatically; pages declare nothing except dynamic overrides |
| Detail pages | Leaf and `h1` show the record name; page passes `title` override |
| Composition | One block inside page content: crumbs → `h1` + actions → description → tab strip |
| Table integration | Shared page shell (header + toolbar + content slot + pagination); no generic DataTable |
| `accounts` view toggle | Out of scope — presentation toggle, not navigation |
| Budget query state | Tab params may be a resolver over current params, preserving `year`/`month` and the existing month heuristic |
| Testing | Add Vitest; unit-test the resolver |

## Technical constraint that shapes the design

`globalThis.Ziggy` is assigned **once** at bootstrap (`resources/js/app.ts:44`), so `Ziggy.location` goes stale after client-side visits and `route().current()` cannot be trusted. This is why `NavMain.vue` matches paths from `usePage().url` by hand instead of using Ziggy.

However `HandleInertiaRequests::share()` publishes `ziggy` as a closure containing `'location' => $request->url()`, so **`usePage().props.ziggy` is fresh on every visit**. The resolver therefore builds a per-visit Ziggy router:

```ts
const router = route(undefined, undefined, undefined, page.props.ziggy);
const name = router.current();   // e.g. 'pockets.index'
```

No backend change is required.

## Data model

`resources/js/lib/pageRegistry.ts`:

```ts
export type PageNode = {
    /** i18n key for the h1, the node's breadcrumb label and the document title. */
    titleKey: string;
    /** i18n key for the subtitle under the h1. */
    descriptionKey?: string;
    /** Parent node id; omitted for domain roots. */
    parent?: string;
    /**
     * Route name used to build this node's breadcrumb href.
     * Defaults to the first entry of `matchRoutes`, then to the node id.
     */
    route?: string;
    routeParams?: Record<string, string | number>;
    /** Route names resolving to this node. Defaults to `[nodeId]`. */
    matchRoutes?: string[];
    /** Tab group id anchored at this node. */
    tabGroup?: string;
};

export type TabParams = Record<string, string | number>;

export type PageTab = {
    key: string;
    labelKey: string;
    route: string;
    /**
     * Static params identify the tab and are also used to detect the active one.
     * A resolver receives the current params instead, for tabs whose target
     * depends on where the user currently is.
     */
    params?: TabParams | ((current: Record<string, string>) => TabParams);
};

export type PageTabGroup = {
    defaultKey: string;
    tabs: PageTab[];
};
```

`matchRoutes` is what lets one node anchor several routes. Without it the budget trail would read `Budżet / Budżet miesięczny`, because `budget.monthly.title` is the full phrase rather than the tab label.

Deriving the crumb href from the first `matchRoutes` entry keeps the tree almost declaration-free: node ids like `accounts` or `budget` are logical names, not route names, so `accounts` links to `accounts.index` and `budget` links to `budget.monthly` — its default tab — without either node spelling `route` out. Only `settings`, which anchors no route of its own, sets `route: 'profile.edit'` explicitly.

## Resolution algorithm

`resources/js/composables/useCurrentPage.ts` exposes `useCurrentPage(overrides)` returning `{ crumbs, title, description, documentTitle, tabs, activeTabKey }`.

1. Read `page.props.ziggy`; derive the current route `name` and its route params. Merge in query params parsed from `page.url` — the tab matcher needs both, since pockets switch on `?filter=` while budget switches on the route name.
2. Find the node whose `matchRoutes ?? [nodeId]` contains `name`. If there is none, return the empty fallback (see below).
3. Walk the `parent` chain upward, collecting ancestors, then reverse. Each ancestor becomes a linked crumb whose href resolves through `route ?? matchRoutes[0] ?? nodeId` plus `routeParams`.
4. Append the node's own crumb.
5. If the node has a `tabGroup`, pick the active tab: the first tab whose `route` equals `name` **and** whose every static `params` entry equals the corresponding current param (compared as strings). Tabs with a resolver instead of static params match on the route name alone, which is exactly how budget distinguishes its two tabs. If none matches, fall back to `defaultKey`. Append the active tab's label as a further crumb.
6. Render the last crumb of the finished trail as a non-clickable `BreadcrumbPage`, every earlier one as a link. Because the leaf never needs an href, detail routes such as `accounts.edit` never have to reconstruct their record params for the trail.
7. `title` = override, else `t(node.titleKey)`.
8. `description` = override, else `node.descriptionKey` translated, else undefined.
9. `documentTitle` = override, else `t(node.titleKey)`, joined with the active tab label as `Kieszenie · Aktywne` when a tab group is present. `app.ts` appends the ` - {appName}` suffix as it does today.

**Fallback:** an unregistered route renders the mobile sidebar trigger and the actions slot only — no crumbs, no `h1` — and logs `console.warn` when `import.meta.env.DEV`. Pages must never break because someone forgot a registry entry.

## Components

New directory `resources/js/components/page/`, matching the existing `components/pagination/` precedent: app chrome, neither a domain component nor a `ui/` primitive.

### `PageHeader.vue`

Rendered once by `AppSidebarLayout` above the content slot. Absorbs `AppSidebarHeader.vue`, taking over its mobile `SidebarTrigger` and its `#actions` slot. Reuses the existing `components/ui/breadcrumb/*` primitives unchanged. Also renders `<Head :title="documentTitle" />`, so pages drop their own `<Head>`.

Layout, top to bottom:

```text
[☰ mobile]  Kieszenie / Aktywne                          ← text-sm text-muted-foreground,
                                                            leaf: text-foreground font-medium
Kieszenie                                [Dodaj kieszeń] ← h1 text-xl font-semibold tracking-tight
Opis strony                                                 actions right, flex items-start justify-between
────────────────────────────────────────────────────────
 Aktywne │ Zarchiwizowane │ Wszystkie                    ← tab strip, border-b border-border/50
```

Block spacing `mb-6`, preserving the current `AppSidebarHeader` rhythm.

Below `sm` the full trail is redundant with the `h1`, so it collapses to a single back-link to the parent with a chevron; with no parent it disappears entirely.

### `PageTabs.vue`

These tabs change the URL and reload data, so they are **navigation, not an ARIA tab widget**: `<nav aria-label>` plus `<Link aria-current="page">` on the active item. `role="tablist"` / `role="tab"` is deliberately not used — those roles promise a screen reader that panels swap without leaving the page, which would be a lie here.

Styling per the `openship-inspired-ui` skill: `border-b border-border/50`, active item `text-foreground` with an `h-0.5 bg-primary` underline inset to the label, never a pill background. Transitions use `<Link preserve-scroll>` so switching a filter does not jump to the top. The strip scrolls horizontally on narrow viewports.

The param resolver exists because budget carries state in the query string: both views take `year`, and the monthly view also takes `month`. A tab that emitted only its own static params would silently reset the user to the current year on every switch. `Yearly.vue` today also applies a heuristic when moving to the monthly view — the current month if the user is looking at the current year, otherwise January, which the backend does not replicate (`MonthlyBudgetRequest::getMonth()` always falls back to the current month). The resolver keeps that behaviour inside the registry, with no page involvement and no backend change.

### `TablePage.vue`

Shell for data pages, not a table implementation: `#toolbar` slot for filters and search, default slot for the table or list, `#empty` slot for the empty state, and an automatic `PaginationBar` at the bottom when a paginator is passed. Existing markup drops into the slots unchanged.

## Registry content

Nodes marked ✱ have no route of their own — they exist to group children or to anchor a tab strip.

| Node id | Title key | Parent | `matchRoutes` | Tab group |
|---------|-----------|--------|---------------|-----------|
| `dashboard` | `dashboard.title` | — | `dashboard` | — |
| `accounts` | `accounts.index.title` | — | `accounts.index` | — |
| `accounts.create` | `accounts.create.title` | `accounts` | `accounts.create` | — |
| `accounts.edit` | `accounts.edit.title` | `accounts` | `accounts.edit` | — |
| `transactions` | `transactions.index.title` | — | `transactions.index` | — |
| `transactions.create` | `transactions.create.title` | `transactions` | `transactions.create` | — |
| `transactions.edit` | `transactions.edit.title` | `transactions` | `transactions.edit` | — |
| `transfers.create` | `transfers.create.title` | `transactions` | `transfers.create` | — |
| `categories` | `categories.index.title` | — | `categories.index` | — |
| `categories.create` | `categories.create.title` | `categories` | `categories.create` | — |
| `categories.edit` | `categories.edit.title` | `categories` | `categories.edit` | — |
| `pockets` | `pockets.index.title` | — | `pockets.index` | `pockets` |
| `pockets.create` | `pockets.create.title` | `pockets` | `pockets.create` | — |
| `pockets.edit` | `pockets.edit.title` | `pockets` | `pockets.edit` | — |
| `budget` ✱ | `budget.nav` | — | `budget.monthly`, `budget.yearly` | `budget` |
| `settings` ✱ | `settings.title` | — | none — sets `route: 'profile.edit'` | — |
| `settings.profile` | `settings.nav.profile` | `settings` | `profile.edit` | — |
| `settings.password` | `settings.nav.password` | `settings` | `password.edit` | — |
| `settings.appearance` | `settings.nav.appearance` | `settings` | `appearance` | — |

Tab groups:

| Group | Default | Tab | Route | Label key | Params |
|-------|---------|-----|-------|-----------|--------|
| `pockets` | `active` | `active` | `pockets.index` | `pockets.filters.active` | `{ filter: 'active' }` |
| | | `archived` | `pockets.index` | `pockets.filters.archived` | `{ filter: 'archived' }` |
| | | `all` | `pockets.index` | `pockets.filters.all` | `{ filter: 'all' }` |
| `budget` | `monthly` | `monthly` | `budget.monthly` | `budget.view.monthly` | resolver (below) |
| | | `yearly` | `budget.yearly` | `budget.view.yearly` | resolver (below) |

The budget resolvers preserve the viewed period and carry over the month heuristic from `Yearly.vue`:

```ts
const budgetYear = (current: Record<string, string>): number => Number(current.year) || new Date().getFullYear();

// monthly tab
params: (current) => {
    const year = budgetYear(current);
    const now = new Date();

    return {
        year,
        month: Number(current.month) || (year === now.getFullYear() ? now.getMonth() + 1 : 1),
    };
},

// yearly tab
params: (current) => ({ year: budgetYear(current) }),
```

Resulting trails: `Kieszenie / Aktywne`, `Budżet / Roczny`, `Transakcje / Nowy transfer`, `Konta / mBank ROR`, `Ustawienia / Profil`.

Routes deliberately absent: `home` and `auth.*` (no `AppLayout`), `imports.*` (no Inertia pages — imports run through a dialog), and every non-GET endpoint.

## Detail pages

`accounts.edit` and `transactions.edit` pass a title override so the leaf, the `h1` and the document title carry the record name, with the static action name demoted to the description:

```vue
<AppLayout :title="account.name" :description="t('accounts.edit.title')">
```

## i18n

Every label the registry needs already exists in both `en.json` and `pl.json` — verified for all 19 nodes: `dashboard.title`, `{domain}.index|create|edit.title`, `transfers.create.title`, `budget.nav`, `budget.view.*`, `pockets.filters.*`, `settings.title` and `settings.nav.*`.

Exactly one key is added: `a11y.page_tabs` (`"Page views"` / `"Widoki strony"`), the `aria-label` for the tab `<nav>`. The `a11y` block currently holds only `skip_to_content`.

Three keys fall out of use and are removed with their last usage: `budget.monthly.title` and `budget.yearly.title`, obsolete once the trail reads `Budżet / Miesięczny`, and `settings.description`, which only fed the `Heading` block deleted from `settings/Layout.vue`.

`descriptionKey` is set on exactly two nodes, both from keys that already exist: `dashboard` → `dashboard.description` and `transactions` → `transactions.index.description`. No new description keys are invented, and settings pages get no subtitle.

## Page migration

Header plumbing is removed from all 19 app views: the `BreadcrumbItem` import, the `breadcrumbs` computed, `:breadcrumbs` on `<AppLayout>` and `<Head :title>`. On top of that:

- `pockets/Index.vue` — delete the local `tabs` array and the pill markup (currently lines 62–66 and 110–120).
- `budget/Monthly.vue`, `budget/Yearly.vue` — delete the monthly/yearly `<Button>` + `<Link>` switcher.
- `Dashboard.vue` — delete its own `<h1>`; the header supplies it.
- `settings/Layout.vue` — delete the `Heading` block, which would otherwise render a second title under the new `h1`.
- `transactions/Index.vue` — wrap the existing table in `TablePage`, moving filters into `#toolbar` and both empty states into `#empty`. The table markup itself is untouched.

`accounts/Index.vue`, `categories/Index.vue` and the budget components keep their current bodies; they move onto `TablePage` in a later step.

## Files

| File | Change |
|------|--------|
| `resources/js/lib/pageRegistry.ts` | New — nodes, tab groups, types |
| `resources/js/composables/useCurrentPage.ts` | New — resolver |
| `resources/js/components/page/PageHeader.vue` | New |
| `resources/js/components/page/PageTabs.vue` | New |
| `resources/js/components/page/TablePage.vue` | New |
| `resources/js/layouts/app/AppSidebarLayout.vue` | Render `PageHeader`; drop `breadcrumbs` prop |
| `resources/js/layouts/AppLayout.vue` | Drop `breadcrumbs`; add `title`, `description` |
| `resources/js/components/AppSidebarHeader.vue` | Delete — absorbed by `PageHeader` |
| `resources/js/layouts/app/AppHeaderLayout.vue` | Delete — unused |
| `resources/js/components/AppHeader.vue` | Delete — unused, holds a dead `<Breadcrumbs>` reference |
| `resources/js/pages/**` | Remove header plumbing; pockets/budget lose local switchers |
| `resources/js/types/index.ts` | `BreadcrumbItem` stays; consumer moves to `PageHeader` |
| `resources/js/locales/{en,pl}.json` | Add `a11y.page_tabs`; drop `budget.monthly.title`, `budget.yearly.title`, `settings.description` |
| `.docs/superpowers/specs/2026-08-20-remove-breadcrumbs-design.md` | Mark `Status: Superseded` |
| `.docs/checklist.md` | Record the new page chrome |

## Testing

Vitest is added as a dev dependency (the project has no frontend test runner today, and `AGENTS.md` requires every change to be covered programmatically). Unit tests target `useCurrentPage` / the resolver, which is pure logic with real branching:

- trail for a root, a child and a detail page with a title override
- tab-group anchoring: `budget.monthly` and `budget.yearly` resolve to the same node with different leaves
- active tab by query param, including the `defaultKey` fallback on `/kieszenie` with no `?filter=`
- budget param resolvers: `year` survives a switch in both directions, and the monthly target month is the current month for the current year but January for a past one
- `h1` shows the node title while the document title carries the tab label
- unregistered route returns the empty fallback and does not throw

Alongside: `npm run typecheck`, `npm run lint:check`, `npm run format:check`, and `./vendor/bin/sail artisan test --compact` as a backend safety net.

## Out of scope

- Generic `DataTable` with a column API; existing tables keep their markup
- Migrating budget, accounts, categories onto `TablePage`
- The `accounts/Index.vue` grid/list presentation toggle
- Converting the settings side nav into tabs
- Auth and guest layouts, `Welcome.vue`
- Backend changes of any kind

## Success criteria

- Every page under `AppLayout` renders one header block with crumbs, an `h1` and its actions, sourced from the registry; no page declares breadcrumbs or `<Head :title>` any more.
- Pockets and budget switch views through one shared `PageTabs`, with the active tab visible in the trail and the document title while the `h1` stays stable.
- `transactions/Index.vue` renders inside `TablePage` with its filters, sorting, pagination and both empty states behaving as before.
- An unregistered route degrades to a header-less page instead of an error.
- Vitest suite, typecheck, lint and format all pass.
