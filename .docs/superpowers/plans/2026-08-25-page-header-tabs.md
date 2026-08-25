# Page Header, Breadcrumbs and Tabs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace per-page breadcrumb and title declarations with one central registry from which the app layout derives breadcrumbs, `h1`, description, document title and a shared tab strip.

**Architecture:** A pure data module (`pageRegistry.ts`) describes the page tree and tab groups. A pure resolver (`pageChrome.ts`) turns "current route + overrides" into the finished chrome, with `t()` and URL generation injected so it is unit-testable without Vue, Ziggy or i18n. A thin composable (`useCurrentPage.ts`) wires the resolver to Inertia and Ziggy, and `PageHeader.vue` renders it once inside `AppSidebarLayout`. Pages stop declaring chrome entirely.

**Tech Stack:** Vue 3 (`<script setup>`, TypeScript), Inertia v2, Ziggy v2, vue-i18n v11, Tailwind v3, shadcn-vue primitives, Vitest (added by this plan).

**Spec:** `.docs/superpowers/specs/2026-08-25-page-header-tabs-design.md`

## Global Constraints

- Frontend only. **No backend changes of any kind** — no controller, request, route or middleware edits.
- Follow `.cursor/rules/wallet-ui-design.mdc` and the `openship-inspired-ui` skill: buttons/inputs `rounded-xl`, cards `rounded-2xl`, `transition-colors duration-150`, primary is ink not mint, no resting drop shadows.
- Tab strip styling is fixed by the spec: `border-b border-border/50`, active item `text-foreground` plus an `h-0.5 bg-primary` underline. **Never a pill background.**
- Tabs are navigation, not an ARIA tab widget: `<nav aria-label>` + `<Link aria-current="page">`. **Do not use `role="tablist"` or `role="tab"`.**
- Domain UI lives in `components/{domain}/`, primitives in `components/ui/`. The new chrome components go in `components/page/`.
- All code, comments and commit messages in English.
- Do not edit `resources/js/components/ui/*` (ESLint-ignored shadcn primitives) — reuse them as they are.
- Only one new i18n key is allowed: `a11y.page_tabs`. Every other label already exists in both `en.json` and `pl.json`.
- Run `npx prettier --write` on touched frontend files before committing; the repo uses `prettier-plugin-organize-imports` and `prettier-plugin-tailwindcss`.

---

## File Structure

| File | Responsibility |
|------|----------------|
| `resources/js/lib/pageRegistry.ts` | Data only: node tree, tab groups, their types. No logic beyond the budget param resolvers. |
| `resources/js/lib/pageRegistry.test.ts` | Integrity of the data: parents exist, routes are unique, tab groups resolve. |
| `resources/js/lib/pageChrome.ts` | Pure resolver: `(currentRoute, overrides, deps) → PageChrome`. Knows nothing about Vue. |
| `resources/js/lib/pageChrome.test.ts` | Behaviour of the resolver — the only place with real branching logic. |
| `resources/js/composables/useCurrentPage.ts` | Adapter: Inertia page props + Ziggy + `useI18n` → resolver input. |
| `resources/js/components/page/PageTabs.vue` | Renders the tab strip. Presentational, props in, links out. |
| `resources/js/components/page/PageHeader.vue` | Renders crumbs + `h1` + description + actions + tabs + `<Head>`. |
| `resources/js/components/page/TablePage.vue` | Shell for data pages: toolbar, content, empty state, pagination. |
| `resources/js/components/pagination/types.ts` | Paginator types shared by `PaginationBar` and `TablePage`. |
| `vitest.config.ts` | Test runner config, separate from `vite.config.ts` so the Laravel plugin is not loaded. |

---

### Task 1: Vitest and the page registry

**Files:**
- Create: `vitest.config.ts`
- Create: `resources/js/lib/pageRegistry.ts`
- Test: `resources/js/lib/pageRegistry.test.ts`
- Modify: `package.json` (devDependency + `test` scripts)

**Interfaces:**
- Consumes: nothing.
- Produces: `PageNode`, `PageTab`, `PageTabGroup`, `TabParams`, `TabParamsResolver` types; `pageNodes: Record<string, PageNode>`; `pageTabGroups: Record<string, PageTabGroup>`. Task 2 imports all of these from `@/lib/pageRegistry`.

- [ ] **Step 1: Install Vitest**

```bash
npm install -D vitest
```

Do not pin a version by hand — take whatever npm resolves as latest and let Step 4 prove it runs under Vite 6.

- [ ] **Step 2: Add the test scripts**

In `package.json`, inside `"scripts"`, after the `"lint:check"` line:

```json
"test": "vitest run",
"test:watch": "vitest"
```

- [ ] **Step 3: Create `vitest.config.ts`**

A standalone config. It deliberately does **not** reuse `vite.config.ts`, because the `laravel-vite-plugin` expects a dev-server/build context and has no business running under the test runner. Environment is `node` because every test here is pure TypeScript — no DOM, so no jsdom dependency is needed.

```ts
import path from 'path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
});
```

- [ ] **Step 4: Write the failing registry integrity test**

Create `resources/js/lib/pageRegistry.test.ts`:

```ts
import { pageNodes, pageTabGroups } from '@/lib/pageRegistry';
import { describe, expect, it } from 'vitest';

describe('pageRegistry', () => {
    it('points every parent at an existing node', () => {
        for (const [nodeId, node] of Object.entries(pageNodes)) {
            if (node.parent) {
                expect(Object.keys(pageNodes), `parent of "${nodeId}"`).toContain(node.parent);
            }
        }
    });

    it('never maps one route name to two nodes', () => {
        const owners = new Map<string, string>();

        for (const [nodeId, node] of Object.entries(pageNodes)) {
            for (const routeName of node.matchRoutes ?? [nodeId]) {
                expect(owners.has(routeName), `"${routeName}" is claimed by both "${owners.get(routeName)}" and "${nodeId}"`).toBe(false);
                owners.set(routeName, nodeId);
            }
        }
    });

    it('points every tabGroup at a group whose defaultKey is one of its tabs', () => {
        for (const [nodeId, node] of Object.entries(pageNodes)) {
            if (!node.tabGroup) {
                continue;
            }

            const group = pageTabGroups[node.tabGroup];

            expect(group, `tabGroup "${node.tabGroup}" used by "${nodeId}"`).toBeDefined();
            expect(group.tabs.map((tab) => tab.key)).toContain(group.defaultKey);
        }
    });
});
```

- [ ] **Step 5: Run the test to verify it fails**

Run: `npm test`
Expected: FAIL — `Failed to resolve import "@/lib/pageRegistry"`, because the module does not exist yet.

- [ ] **Step 6: Create the registry**

Create `resources/js/lib/pageRegistry.ts`:

```ts
export type TabParams = Record<string, string | number>;

export type TabParamsResolver = (current: Record<string, string>) => TabParams;

export type PageTab = {
    key: string;
    labelKey: string;
    route: string;
    /**
     * Static params identify the tab and mark it active. A resolver receives the
     * current params instead, for tabs whose target depends on where the user is.
     */
    params?: TabParams | TabParamsResolver;
};

export type PageTabGroup = {
    defaultKey: string;
    tabs: PageTab[];
};

export type PageNode = {
    titleKey: string;
    descriptionKey?: string;
    parent?: string;
    /** Route used for this node's breadcrumb href. Falls back to matchRoutes[0], then the node id. */
    route?: string;
    routeParams?: TabParams;
    /** Route names resolving to this node. Defaults to [nodeId]. */
    matchRoutes?: string[];
    tabGroup?: string;
};

function budgetYear(current: Record<string, string>): number {
    return Number(current.year) || new Date().getFullYear();
}

export const pageTabGroups: Record<string, PageTabGroup> = {
    pockets: {
        defaultKey: 'active',
        tabs: [
            { key: 'active', labelKey: 'pockets.filters.active', route: 'pockets.index', params: { filter: 'active' } },
            { key: 'archived', labelKey: 'pockets.filters.archived', route: 'pockets.index', params: { filter: 'archived' } },
            { key: 'all', labelKey: 'pockets.filters.all', route: 'pockets.index', params: { filter: 'all' } },
        ],
    },
    budget: {
        defaultKey: 'monthly',
        tabs: [
            {
                key: 'monthly',
                labelKey: 'budget.view.monthly',
                route: 'budget.monthly',
                params: (current) => {
                    const year = budgetYear(current);
                    const today = new Date();

                    return {
                        year,
                        month: Number(current.month) || (year === today.getFullYear() ? today.getMonth() + 1 : 1),
                    };
                },
            },
            {
                key: 'yearly',
                labelKey: 'budget.view.yearly',
                route: 'budget.yearly',
                params: (current) => ({ year: budgetYear(current) }),
            },
        ],
    },
};

export const pageNodes: Record<string, PageNode> = {
    dashboard: { titleKey: 'dashboard.title', descriptionKey: 'dashboard.description' },

    accounts: { titleKey: 'accounts.index.title', matchRoutes: ['accounts.index'] },
    'accounts.create': { titleKey: 'accounts.create.title', parent: 'accounts' },
    'accounts.edit': { titleKey: 'accounts.edit.title', parent: 'accounts' },

    transactions: {
        titleKey: 'transactions.index.title',
        descriptionKey: 'transactions.index.description',
        matchRoutes: ['transactions.index'],
    },
    'transactions.create': { titleKey: 'transactions.create.title', parent: 'transactions' },
    'transactions.edit': { titleKey: 'transactions.edit.title', parent: 'transactions' },
    'transfers.create': { titleKey: 'transfers.create.title', parent: 'transactions' },

    categories: { titleKey: 'categories.index.title', matchRoutes: ['categories.index'] },
    'categories.create': { titleKey: 'categories.create.title', parent: 'categories' },
    'categories.edit': { titleKey: 'categories.edit.title', parent: 'categories' },

    pockets: { titleKey: 'pockets.index.title', matchRoutes: ['pockets.index'], tabGroup: 'pockets' },
    'pockets.create': { titleKey: 'pockets.create.title', parent: 'pockets' },
    'pockets.edit': { titleKey: 'pockets.edit.title', parent: 'pockets' },

    budget: { titleKey: 'budget.nav', matchRoutes: ['budget.monthly', 'budget.yearly'], tabGroup: 'budget' },

    // Groups the settings pages; owns no route, so it links to the first settings screen.
    settings: { titleKey: 'settings.title', route: 'profile.edit', matchRoutes: [] },
    'settings.profile': { titleKey: 'settings.nav.profile', parent: 'settings', matchRoutes: ['profile.edit'] },
    'settings.password': { titleKey: 'settings.nav.password', parent: 'settings', matchRoutes: ['password.edit'] },
    'settings.appearance': { titleKey: 'settings.nav.appearance', parent: 'settings', matchRoutes: ['appearance'] },
};
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `npm test`
Expected: PASS — 3 tests in `pageRegistry.test.ts`.

- [ ] **Step 8: Verify typecheck and lint are clean**

Run: `npm run typecheck && npm run lint:check`
Expected: both exit 0. If `vitest.config.ts` trips ESLint on `__dirname`, that is expected in an ESM `.ts` config compiled by Vite — it resolves because Vite transpiles configs to CJS; leave it.

- [ ] **Step 9: Commit**

```bash
npx prettier --write vitest.config.ts resources/js/lib/pageRegistry.ts resources/js/lib/pageRegistry.test.ts
git add package.json package-lock.json vitest.config.ts resources/js/lib/pageRegistry.ts resources/js/lib/pageRegistry.test.ts
git commit -m "feat(chrome): add page registry describing the app page tree"
```

---

### Task 2: The chrome resolver

**Files:**
- Create: `resources/js/lib/pageChrome.ts`
- Test: `resources/js/lib/pageChrome.test.ts`

**Interfaces:**
- Consumes: `pageNodes`, `pageTabGroups`, `PageNode`, `PageTab`, `TabParams` from `@/lib/pageRegistry`.
- Produces:
  - `type CurrentRoute = { name: string | null; params: Record<string, string> }`
  - `type ChromeCrumb = { title: string; href: string | null }`
  - `type ChromeTab = { key: string; label: string; href: string; isActive: boolean }`
  - `type ChromeOverrides = { title?: string; description?: string }`
  - `type ChromeDeps = { t: (key: string) => string; url: (routeName: string, params?: TabParams) => string }`
  - `type PageChrome = { crumbs: ChromeCrumb[]; title: string | null; description: string | null; documentTitle: string | null; tabs: ChromeTab[] }`
  - `function resolvePageChrome(current: CurrentRoute, overrides: ChromeOverrides, deps: ChromeDeps): PageChrome`

  Task 3 calls `resolvePageChrome`; Task 4 consumes `ChromeTab`; Task 5 consumes `ChromeCrumb` and `PageChrome`.

- [ ] **Step 1: Write the failing resolver test**

Create `resources/js/lib/pageChrome.test.ts`. `t` returns the key so assertions read as keys, and `url` produces a predictable fake path:

```ts
import { resolvePageChrome, type ChromeDeps, type CurrentRoute } from '@/lib/pageChrome';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const deps: ChromeDeps = {
    t: (key) => key,
    url: (routeName, params) => {
        const entries = Object.entries(params ?? {}).map(([key, value]): [string, string] => [key, String(value)]);
        const query = new URLSearchParams(entries).toString();

        return query ? `/${routeName}?${query}` : `/${routeName}`;
    },
};

function at(name: string, params: Record<string, string> = {}): CurrentRoute {
    return { name, params };
}

describe('resolvePageChrome', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-25T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders a root page as a single unlinked crumb', () => {
        const chrome = resolvePageChrome(at('accounts.index'), {}, deps);

        expect(chrome.crumbs).toEqual([{ title: 'accounts.index.title', href: null }]);
        expect(chrome.title).toBe('accounts.index.title');
        expect(chrome.documentTitle).toBe('accounts.index.title');
        expect(chrome.tabs).toEqual([]);
    });

    it('links every ancestor and leaves only the leaf unlinked', () => {
        const chrome = resolvePageChrome(at('accounts.create'), {}, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'accounts.index.title', href: '/accounts.index' },
            { title: 'accounts.create.title', href: null },
        ]);
    });

    it('lets a detail page override the leaf, the title and the document title', () => {
        const chrome = resolvePageChrome(at('accounts.edit', { account: '12' }), { title: 'mBank ROR', description: 'accounts.edit.title' }, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'accounts.index.title', href: '/accounts.index' },
            { title: 'mBank ROR', href: null },
        ]);
        expect(chrome.title).toBe('mBank ROR');
        expect(chrome.description).toBe('accounts.edit.title');
        expect(chrome.documentTitle).toBe('mBank ROR');
    });

    it('falls back to the default tab when no filter is present', () => {
        const chrome = resolvePageChrome(at('pockets.index'), {}, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'pockets.index.title', href: '/pockets.index' },
            { title: 'pockets.filters.active', href: null },
        ]);
        expect(chrome.tabs.find((tab) => tab.isActive)?.key).toBe('active');
    });

    it('keeps the h1 stable while the tab moves into the trail and the document title', () => {
        const chrome = resolvePageChrome(at('pockets.index', { filter: 'archived' }), {}, deps);

        expect(chrome.title).toBe('pockets.index.title');
        expect(chrome.documentTitle).toBe('pockets.index.title · pockets.filters.archived');
        expect(chrome.tabs.find((tab) => tab.isActive)?.key).toBe('archived');
    });

    it('anchors both budget routes on one node with different leaves', () => {
        const monthly = resolvePageChrome(at('budget.monthly', { year: '2026', month: '8' }), {}, deps);
        const yearly = resolvePageChrome(at('budget.yearly', { year: '2026' }), {}, deps);

        expect(monthly.crumbs.map((crumb) => crumb.title)).toEqual(['budget.nav', 'budget.view.monthly']);
        expect(yearly.crumbs.map((crumb) => crumb.title)).toEqual(['budget.nav', 'budget.view.yearly']);
        expect(monthly.title).toBe('budget.nav');
    });

    it('carries the viewed year across a budget tab switch', () => {
        const chrome = resolvePageChrome(at('budget.monthly', { year: '2024', month: '3' }), {}, deps);

        expect(chrome.tabs.find((tab) => tab.key === 'yearly')?.href).toBe('/budget.yearly?year=2024');
    });

    it('targets the current month for the current year but January for a past one', () => {
        const currentYear = resolvePageChrome(at('budget.yearly', { year: '2026' }), {}, deps);
        const pastYear = resolvePageChrome(at('budget.yearly', { year: '2024' }), {}, deps);

        expect(currentYear.tabs.find((tab) => tab.key === 'monthly')?.href).toBe('/budget.monthly?year=2026&month=8');
        expect(pastYear.tabs.find((tab) => tab.key === 'monthly')?.href).toBe('/budget.monthly?year=2024&month=1');
    });

    it('degrades to empty chrome and warns when the route is not registered', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const chrome = resolvePageChrome(at('imports.index'), {}, deps);

        expect(chrome).toEqual({ crumbs: [], title: null, description: null, documentTitle: null, tabs: [] });
        expect(warn).toHaveBeenCalledOnce();

        warn.mockRestore();
    });

    it('degrades to empty chrome when there is no route name at all', () => {
        expect(resolvePageChrome({ name: null, params: {} }, {}, deps)).toEqual({
            crumbs: [],
            title: null,
            description: null,
            documentTitle: null,
            tabs: [],
        });
    });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npm test -- pageChrome`
Expected: FAIL — `Failed to resolve import "@/lib/pageChrome"`.

- [ ] **Step 3: Write the resolver**

Create `resources/js/lib/pageChrome.ts`:

```ts
import { pageNodes, pageTabGroups, type PageNode, type PageTab, type PageTabGroup, type TabParams } from '@/lib/pageRegistry';

export type CurrentRoute = {
    name: string | null;
    params: Record<string, string>;
};

export type ChromeCrumb = {
    title: string;
    href: string | null;
};

export type ChromeTab = {
    key: string;
    label: string;
    href: string;
    isActive: boolean;
};

export type ChromeOverrides = {
    title?: string;
    description?: string;
};

export type ChromeDeps = {
    t: (key: string) => string;
    url: (routeName: string, params?: TabParams) => string;
};

export type PageChrome = {
    crumbs: ChromeCrumb[];
    title: string | null;
    description: string | null;
    documentTitle: string | null;
    tabs: ChromeTab[];
};

function emptyChrome(): PageChrome {
    return { crumbs: [], title: null, description: null, documentTitle: null, tabs: [] };
}

function matchRoutesOf(nodeId: string, node: PageNode): string[] {
    return node.matchRoutes ?? [nodeId];
}

function routeNameOf(nodeId: string, node: PageNode): string {
    return node.route ?? node.matchRoutes?.[0] ?? nodeId;
}

function findNodeId(routeName: string): string | null {
    for (const [nodeId, node] of Object.entries(pageNodes)) {
        if (matchRoutesOf(nodeId, node).includes(routeName)) {
            return nodeId;
        }
    }

    return null;
}

function ancestorIdsOf(nodeId: string): string[] {
    const chain: string[] = [];
    let parentId = pageNodes[nodeId]?.parent;

    while (parentId && !chain.includes(parentId)) {
        chain.unshift(parentId);
        parentId = pageNodes[parentId]?.parent;
    }

    return chain;
}

function tabParamsOf(tab: PageTab, current: Record<string, string>): TabParams | undefined {
    return typeof tab.params === 'function' ? tab.params(current) : tab.params;
}

function activeTabOf(group: PageTabGroup, current: CurrentRoute): PageTab {
    const matched = group.tabs.find((tab) => {
        if (tab.route !== current.name) {
            return false;
        }

        // A resolver-driven tab is identified by its route alone.
        if (typeof tab.params !== 'object' || tab.params === null) {
            return true;
        }

        return Object.entries(tab.params).every(([key, value]) => current.params[key] === String(value));
    });

    return matched ?? group.tabs.find((tab) => tab.key === group.defaultKey) ?? group.tabs[0];
}

export function resolvePageChrome(current: CurrentRoute, overrides: ChromeOverrides, deps: ChromeDeps): PageChrome {
    if (!current.name) {
        return emptyChrome();
    }

    const nodeId = findNodeId(current.name);

    if (!nodeId) {
        if (import.meta.env.DEV) {
            console.warn(`[pageChrome] No registry entry for route "${current.name}".`);
        }

        return emptyChrome();
    }

    const node = pageNodes[nodeId];
    const crumbs: ChromeCrumb[] = ancestorIdsOf(nodeId).map((ancestorId) => {
        const ancestor = pageNodes[ancestorId];

        return {
            title: deps.t(ancestor.titleKey),
            href: deps.url(routeNameOf(ancestorId, ancestor), ancestor.routeParams),
        };
    });

    const nodeTitle = overrides.title ?? deps.t(node.titleKey);

    crumbs.push({ title: nodeTitle, href: deps.url(routeNameOf(nodeId, node), node.routeParams) });

    const group = node.tabGroup ? pageTabGroups[node.tabGroup] : undefined;
    let tabs: ChromeTab[] = [];
    let activeTabLabel: string | null = null;

    if (group) {
        const active = activeTabOf(group, current);

        activeTabLabel = deps.t(active.labelKey);
        tabs = group.tabs.map((tab) => ({
            key: tab.key,
            label: deps.t(tab.labelKey),
            href: deps.url(tab.route, tabParamsOf(tab, current.params)),
            isActive: tab.key === active.key,
        }));

        crumbs.push({ title: activeTabLabel, href: null });
    }

    crumbs[crumbs.length - 1].href = null;

    return {
        crumbs,
        title: nodeTitle,
        description: overrides.description ?? (node.descriptionKey ? deps.t(node.descriptionKey) : null),
        documentTitle: overrides.title ?? (activeTabLabel ? `${deps.t(node.titleKey)} · ${activeTabLabel}` : deps.t(node.titleKey)),
        tabs,
    };
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `npm test`
Expected: PASS — 13 tests across both files.

If the "past year" assertion fails with `month=8` instead of `month=1`, the fake timer is not reaching the registry: confirm `budgetYear` and the resolver both call `new Date()` at call time rather than at module load.

- [ ] **Step 5: Verify typecheck and lint**

Run: `npm run typecheck && npm run lint:check`
Expected: both exit 0.

- [ ] **Step 6: Commit**

```bash
npx prettier --write resources/js/lib/pageChrome.ts resources/js/lib/pageChrome.test.ts
git add resources/js/lib/pageChrome.ts resources/js/lib/pageChrome.test.ts
git commit -m "feat(chrome): resolve breadcrumbs, title and tabs from the registry"
```

---

### Task 3: The `useCurrentPage` composable

**Files:**
- Create: `resources/js/composables/useCurrentPage.ts`

**Interfaces:**
- Consumes: `resolvePageChrome`, `ChromeOverrides`, `PageChrome` from `@/lib/pageChrome`; `TabParams` from `@/lib/pageRegistry`.
- Produces: `function useCurrentPage(overrides: ComputedRef<ChromeOverrides>): ComputedRef<PageChrome>`. Task 5 calls it from `PageHeader.vue`.

- [ ] **Step 1: Write the composable**

Two details matter here and are easy to get wrong.

First, **do not use `globalThis.Ziggy`**. It is assigned once at bootstrap (`resources/js/app.ts:44`) so its `location` goes stale after the first client-side visit, which is why `NavMain.vue` compares paths by hand. `usePage().props.ziggy` is republished on every request by `HandleInertiaRequests::share()` and is the only trustworthy source.

Second, query params are read from `page.url` rather than trusted to Ziggy's `params` getter, because the tab matcher depends on `filter`, `year` and `month`, which live in the query string.

Create `resources/js/composables/useCurrentPage.ts`:

```ts
import { resolvePageChrome, type ChromeOverrides, type PageChrome } from '@/lib/pageChrome';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Config as ZiggyConfig } from 'ziggy-js';
import { route } from 'ziggy-js';

function currentParams(router: { params: Record<string, unknown> }, url: string): Record<string, string> {
    const params: Record<string, string> = {};

    for (const [key, value] of Object.entries(router.params)) {
        if (value !== null && value !== undefined) {
            params[key] = String(value);
        }
    }

    for (const [key, value] of new URLSearchParams(url.split('?')[1] ?? '')) {
        params[key] = value;
    }

    return params;
}

export function useCurrentPage(overrides: ComputedRef<ChromeOverrides>): ComputedRef<PageChrome> {
    const page = usePage<SharedData>();
    const { t } = useI18n();

    return computed(() => {
        const ziggy = page.props.ziggy as unknown as ZiggyConfig;
        const router = route(undefined, undefined, undefined, ziggy);

        return resolvePageChrome(
            {
                name: router.current() || null,
                params: currentParams(router, page.url),
            },
            overrides.value,
            {
                t: (key) => t(key),
                url: (routeName, params) => route(routeName, params, false, ziggy),
            },
        );
    });
}
```

Hrefs are generated relative (`absolute = false`), matching `settings/Layout.vue`, so they stay correct under SSR and behind a proxy.

- [ ] **Step 2: Verify typecheck**

Run: `npm run typecheck`
Expected: exit 0.

If `route(undefined, undefined, undefined, ziggy)` is rejected by the overloads shipped in `vendor/tightenco/ziggy`, read that package's `index.d.ts` and adapt the call to whatever returns a `Router` for a given config — the requirement is a router built from the **per-request** config, not the shape of the call.

- [ ] **Step 3: Verify lint**

Run: `npm run lint:check`
Expected: exit 0.

- [ ] **Step 4: Commit**

```bash
npx prettier --write resources/js/composables/useCurrentPage.ts
git add resources/js/composables/useCurrentPage.ts
git commit -m "feat(chrome): bind the chrome resolver to Inertia and Ziggy"
```

---

### Task 4: The tab strip

**Files:**
- Create: `resources/js/components/page/PageTabs.vue`
- Modify: `resources/js/locales/en.json`, `resources/js/locales/pl.json`

**Interfaces:**
- Consumes: `ChromeTab` from `@/lib/pageChrome`.
- Produces: `PageTabs.vue` with a single prop `tabs: ChromeTab[]`. Task 5 renders it.

- [ ] **Step 1: Add the one new i18n key**

In `resources/js/locales/en.json`, the `a11y` block currently holds only `skip_to_content`. Add a sibling:

```json
"a11y": {
    "skip_to_content": "Skip to main content",
    "page_tabs": "Page views"
},
```

The same in `resources/js/locales/pl.json`:

```json
"a11y": {
    "skip_to_content": "Przejdź do treści",
    "page_tabs": "Widoki strony"
},
```

- [ ] **Step 2: Create the component**

The active marker is an absolutely positioned underline inset to the label (`inset-x-3` matches the `px-3` padding), which is what the design skill means by "underline inset to the label, not a pill background".

Create `resources/js/components/page/PageTabs.vue`:

```vue
<script setup lang="ts">
import type { ChromeTab } from '@/lib/pageChrome';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
    tabs: ChromeTab[];
}>();

const { t } = useI18n();
</script>

<template>
    <nav :aria-label="t('a11y.page_tabs')" class="-mb-px flex gap-1 overflow-x-auto border-b border-border/50">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            preserve-scroll
            :aria-current="tab.isActive ? 'page' : undefined"
            class="relative whitespace-nowrap px-3 py-2 text-sm transition-colors duration-150"
            :class="tab.isActive ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
        >
            {{ tab.label }}
            <span v-if="tab.isActive" class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary" aria-hidden="true" />
        </Link>
    </nav>
</template>
```

- [ ] **Step 3: Verify typecheck and lint**

Run: `npm run typecheck && npm run lint:check`
Expected: both exit 0.

- [ ] **Step 4: Commit**

```bash
npx prettier --write resources/js/components/page/PageTabs.vue resources/js/locales/en.json resources/js/locales/pl.json
git add resources/js/components/page/PageTabs.vue resources/js/locales/en.json resources/js/locales/pl.json
git commit -m "feat(chrome): add underline navigation tabs"
```

---

### Task 5: The page header and layout wiring

**Files:**
- Create: `resources/js/components/page/PageHeader.vue`
- Modify: `resources/js/layouts/app/AppSidebarLayout.vue` (whole file)
- Modify: `resources/js/layouts/AppLayout.vue` (whole file)
- Delete: `resources/js/components/AppSidebarHeader.vue`
- Delete: `resources/js/layouts/app/AppHeaderLayout.vue`
- Delete: `resources/js/components/AppHeader.vue`

**Interfaces:**
- Consumes: `useCurrentPage` (Task 3), `PageTabs` (Task 4), `components/ui/breadcrumb`, `components/ui/sidebar`.
- Produces: `AppLayout` accepting `title?: string` and `description?: string` plus the existing `#headerActions` slot. Tasks 6–9 use exactly this surface.

After this task the app already renders chrome for every page while the pages still declare their own breadcrumbs and `<Head>`, so screens show a duplicated title until Tasks 6–9 clean them up. That intermediate state is deliberate — it keeps this task reviewable on its own.

To keep it *compiling* on its own, both layouts keep accepting a `breadcrumbs` prop that they simply ignore. Twenty pages still pass `:breadcrumbs`, and `vue-tsc` rejects props a component does not declare, so removing it here would break `npm run typecheck`. Task 9 deletes the prop once the last page has stopped passing it.

- [ ] **Step 1: Create `PageHeader.vue`**

The actions row is rendered unconditionally so the fallback path (unregistered route, no title) still shows page actions.

```vue
<script setup lang="ts">
import PageTabs from '@/components/page/PageTabs.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCurrentPage } from '@/composables/useCurrentPage';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    title?: string;
    description?: string;
}>();

const chrome = useCurrentPage(computed(() => ({ title: props.title, description: props.description })));

const parentCrumb = computed(() => {
    const { crumbs } = chrome.value;

    return crumbs.length > 1 ? crumbs[crumbs.length - 2] : null;
});
</script>

<template>
    <Head v-if="chrome.documentTitle" :title="chrome.documentTitle" />

    <header class="mb-6">
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="md:hidden" />

            <Link
                v-if="parentCrumb?.href"
                :href="parentCrumb.href"
                class="flex items-center gap-1 text-sm text-muted-foreground transition-colors duration-150 hover:text-foreground sm:hidden"
            >
                <ChevronLeft class="size-4" :stroke-width="1.7" aria-hidden="true" />
                {{ parentCrumb.title }}
            </Link>

            <Breadcrumb v-if="chrome.crumbs.length > 0" class="hidden sm:block">
                <BreadcrumbList>
                    <template v-for="(crumb, index) in chrome.crumbs" :key="index">
                        <BreadcrumbItem>
                            <BreadcrumbLink v-if="crumb.href" :href="crumb.href">{{ crumb.title }}</BreadcrumbLink>
                            <BreadcrumbPage v-else class="font-medium">{{ crumb.title }}</BreadcrumbPage>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator v-if="index !== chrome.crumbs.length - 1" />
                    </template>
                </BreadcrumbList>
            </Breadcrumb>
        </div>

        <div class="mt-2 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 v-if="chrome.title" class="text-xl font-semibold tracking-tight">{{ chrome.title }}</h1>
                <p v-if="chrome.description" class="mt-1 text-sm text-muted-foreground">{{ chrome.description }}</p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <PageTabs v-if="chrome.tabs.length > 0" :tabs="chrome.tabs" class="mt-4" />
    </header>
</template>
```

- [ ] **Step 2: Rewrite `AppSidebarLayout.vue`**

Replace the whole file:

```vue
<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppToaster from '@/components/notifications/AppToaster.vue';
import PageHeader from '@/components/page/PageHeader.vue';
import type { BreadcrumbItemType } from '@/types';

defineProps<{
    title?: string;
    description?: string;
    /** Ignored. Kept only so pages still passing it compile; removed in the page cleanup task. */
    breadcrumbs?: BreadcrumbItemType[];
}>();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <div class="mx-auto w-full max-w-[1600px] px-4 pb-8 pt-6 sm:px-6">
                <PageHeader :title="title" :description="description">
                    <template #actions>
                        <slot name="headerActions" />
                    </template>
                </PageHeader>
                <slot />
            </div>
        </AppContent>
    </AppShell>
    <AppToaster />
</template>
```

- [ ] **Step 3: Rewrite `AppLayout.vue`**

Keep the skip link exactly as it is; only the props change:

```vue
<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { useI18n } from 'vue-i18n';

defineProps<{
    title?: string;
    description?: string;
    /** Ignored. Kept only so pages still passing it compile; removed in the page cleanup task. */
    breadcrumbs?: BreadcrumbItemType[];
}>();

const { t } = useI18n();
</script>

<template>
    <a
        href="#main-content"
        class="sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:m-0 focus:flex focus:h-auto focus:w-auto focus:items-center focus:overflow-visible focus:rounded-md focus:border focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-foreground focus:shadow-md focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
    >
        {{ t('a11y.skip_to_content') }}
    </a>
    <AppLayout :title="title" :description="description">
        <template #headerActions>
            <slot name="headerActions" />
        </template>
        <slot />
    </AppLayout>
</template>
```

- [ ] **Step 4: Delete the superseded shell files**

```bash
git rm resources/js/components/AppSidebarHeader.vue resources/js/layouts/app/AppHeaderLayout.vue resources/js/components/AppHeader.vue
```

`AppSidebarHeader` is absorbed by `PageHeader`. `AppHeaderLayout` and `AppHeader` are referenced by no page and `AppHeader` renders a `<Breadcrumbs>` component that is never imported.

- [ ] **Step 5: Confirm nothing still references the deleted files**

Run: `rg -n "AppSidebarHeader|AppHeaderLayout|AppHeader" resources/js`
Expected: no matches.

- [ ] **Step 6: Verify typecheck, lint and tests**

Run: `npm run typecheck && npm run lint:check && npm test`
Expected: all exit 0, because both layouts still declare the ignored `breadcrumbs` prop. Do not start editing pages here to chase a type error — that is Task 6.

- [ ] **Step 7: Commit**

```bash
npx prettier --write resources/js/components/page/PageHeader.vue resources/js/layouts/app/AppSidebarLayout.vue resources/js/layouts/AppLayout.vue
git add resources/js/components/page/PageHeader.vue resources/js/layouts/app/AppSidebarLayout.vue resources/js/layouts/AppLayout.vue
git commit -m "feat(chrome): render the page header from the layout"
```

---

### Task 6: Strip chrome from the straightforward pages

**Files (modify):**
- `resources/js/pages/Dashboard.vue`
- `resources/js/pages/accounts/Index.vue`, `Create.vue`, `Edit.vue`
- `resources/js/pages/categories/Index.vue`, `Create.vue`, `Edit.vue`
- `resources/js/pages/transactions/Create.vue`, `Edit.vue`
- `resources/js/pages/transfers/Create.vue`
- `resources/js/pages/settings/Profile.vue`, `Password.vue`, `Appearance.vue`
- `resources/js/layouts/settings/Layout.vue`

**Interfaces:**
- Consumes: `AppLayout` with `title` / `description` props and `#headerActions` (Task 5).
- Produces: nothing new. Pockets, budget and the transactions index are handled in Tasks 7–9.

- [ ] **Step 1: Apply the removal pattern to every listed page**

In each file delete exactly four things:

1. `BreadcrumbItem` (or `type BreadcrumbItem`) from the `@/types` import — drop the whole import line if nothing else is used from it.
2. The `const breadcrumbs = computed<BreadcrumbItem[]>(...)` block.
3. `:breadcrumbs="breadcrumbs"` on `<AppLayout>`.
4. The `<Head :title="..." />` element, and `Head` from the `@inertiajs/vue3` import if unused afterwards.

Then remove any import left unused — commonly `computed` from `vue`, and `route` where it was only used to build crumbs.

`accounts/Index.vue` before:

```vue
<script setup lang="ts">
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('accounts.index.title'),
        href: route('accounts.index'),
    },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('accounts.index.title')" />
```

after:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout>
```

- [ ] **Step 2: Pass overrides on the two detail pages**

`accounts/Edit.vue` — the account name becomes the leaf, the `h1` and the document title:

```vue
<AppLayout :title="account.name" :description="t('accounts.edit.title')">
```

`transactions/Edit.vue` — a transaction has no name, so use its description, falling back to the static label when it is empty:

```vue
<AppLayout :title="transaction.description || t('transactions.edit.title')" :description="t('transactions.edit.title')">
```

Check the actual prop names in each file first; use whatever the page already receives rather than inventing props.

- [ ] **Step 3: Remove the duplicate settings heading**

In `resources/js/layouts/settings/Layout.vue` delete line 34 and the now-unused `Heading` import on line 2:

```vue
<Heading :title="t('settings.title')" :description="t('settings.description')" />
```

Without this the settings screens would show `h1` "Profil" from the header and `h2` "Ustawienia" immediately below it.

- [ ] **Step 4: Remove the Dashboard heading**

In `resources/js/pages/Dashboard.vue` delete its own `<h1>` and the paragraph rendering `dashboard.description`; the registry now supplies both. Leave the `<h2>` section headings alone.

- [ ] **Step 5: Confirm the pages in this task declare no chrome**

The search is scoped to the pages this task touches, because pockets, budget and the transactions index still declare theirs until Tasks 7–9.

Run: `rg -n "breadcrumbs|BreadcrumbItem|<Head" resources/js/pages/Dashboard.vue resources/js/pages/accounts resources/js/pages/categories resources/js/pages/settings resources/js/pages/transfers resources/js/pages/transactions/Create.vue resources/js/pages/transactions/Edit.vue resources/js/layouts/settings`
Expected: no matches.

The `breadcrumbs` prop stays on `AppLayout` and `AppSidebarLayout` for now — Task 9 removes it once the last page stops passing it.

- [ ] **Step 6: Verify typecheck, lint and tests**

Run: `npm run typecheck && npm run lint:check && npm test`
Expected: all exit 0.

- [ ] **Step 7: Commit**

```bash
npx prettier --write resources/js/pages resources/js/layouts
git add resources/js/pages resources/js/layouts
git commit -m "refactor(chrome): drop per-page breadcrumbs and head titles"
```

---

### Task 7: Pockets tabs

**Files:**
- Modify: `resources/js/pages/pockets/Index.vue`
- Modify: `resources/js/pages/pockets/Create.vue`, `Edit.vue`

**Interfaces:**
- Consumes: the `pockets` tab group registered in Task 1 and rendered by Task 5.
- Produces: nothing new.

- [ ] **Step 1: Remove the local tab definition from `pockets/Index.vue`**

Delete the `tabs` computed (currently lines 62–66):

```ts
const tabs = computed<{ key: PocketFilter; label: string }[]>(() => [
    { key: 'active', label: t('pockets.filters.active') },
    { key: 'archived', label: t('pockets.filters.archived') },
    { key: 'all', label: t('pockets.filters.all') },
]);
```

- [ ] **Step 2: Remove the pill markup**

Delete the whole `<div class="inline-flex rounded-lg bg-muted p-1">` block (currently lines 110–120) together with the three `<Link>` elements inside it. The wrapping `<div class="flex flex-col gap-6">` stays.

- [ ] **Step 3: Remove the chrome declarations from all three pocket pages**

In `Index.vue`, `Create.vue` and `Edit.vue` delete exactly four things:

1. `BreadcrumbItem` (or `type BreadcrumbItem`) from the `@/types` import — drop the whole import line if nothing else is used from it.
2. The `const breadcrumbs = computed<BreadcrumbItem[]>(...)` block.
3. `:breadcrumbs="breadcrumbs"` on `<AppLayout>`, leaving `<AppLayout>`.
4. The `<Head :title="..." />` element, and `Head` from the `@inertiajs/vue3` import if unused afterwards.

Then drop any import left unused — typically `computed` from `vue`, and `cn` if the deleted pills were its only consumer.

Keep the `#headerActions` slot with the "add envelope" button; it now lands next to the `h1`.

The `filter` prop stays: the page still receives it and `PocketFilter` is still used by the props type. Drop the `cn` import if the pills were its only consumer.

- [ ] **Step 4: Verify typecheck, lint and tests**

Run: `npm run typecheck && npm run lint:check && npm test`
Expected: all exit 0.

- [ ] **Step 5: Manual smoke**

Start the app (`composer run dev` or `./vendor/bin/sail npm run dev`), then visit `/kieszenie`.

Expected:
- Trail reads `Kieszenie / Aktywne`, `h1` reads `Kieszenie`.
- Three underline tabs; the active one is `text-foreground` with a `bg-primary` underline and no pill background.
- Clicking `Zarchiwizowane` navigates to `/kieszenie?filter=archived`, the trail leaf and browser title follow, the `h1` does not change, and the scroll position is preserved.

- [ ] **Step 6: Commit**

```bash
npx prettier --write resources/js/pages/pockets
git add resources/js/pages/pockets
git commit -m "refactor(pockets): move the filter switcher onto shared page tabs"
```

---

### Task 8: Budget tabs

**Files:**
- Modify: `resources/js/pages/budget/Monthly.vue`
- Modify: `resources/js/pages/budget/Yearly.vue`

**Interfaces:**
- Consumes: the `budget` tab group and its param resolvers from Task 1.
- Produces: nothing new.

- [ ] **Step 1: Remove the switcher from `Monthly.vue`**

Delete the inner `<div class="flex gap-2">` holding the two monthly/yearly buttons (currently lines 127–134). Keep the outer `<div class="flex flex-wrap items-center justify-between gap-4">` and the period arrows — those are period navigation, not view navigation.

- [ ] **Step 2: Remove the switcher from `Yearly.vue`**

Delete the equivalent block (currently lines 113–120) and the now-unused `monthlyViewMonth` computed (lines 47–55). Its January-for-past-years heuristic now lives in the registry's monthly tab resolver, so deleting it here loses nothing.

- [ ] **Step 3: Remove the chrome declarations from both budget pages**

In `Monthly.vue` and `Yearly.vue` delete exactly four things:

1. `BreadcrumbItem` (or `type BreadcrumbItem`) from the `@/types` import — drop the whole import line if nothing else is used from it.
2. The `const breadcrumbs = computed<BreadcrumbItem[]>(...)` block (`Yearly.vue:42`, and its counterpart in `Monthly.vue`).
3. `:breadcrumbs="breadcrumbs"` on `<AppLayout>`, leaving `<AppLayout>`.
4. The `<Head :title="..." />` element, and `Head` from the `@inertiajs/vue3` import if unused afterwards.

The `year` / `month` props stay — the period arrows and the budget tables still use them. Keep `computed`, which both pages use for their row splits.

- [ ] **Step 4: Verify typecheck, lint and tests**

Run: `npm run typecheck && npm run lint:check && npm test`
Expected: all exit 0.

- [ ] **Step 5: Manual smoke of the param resolvers**

This is the behaviour the resolvers exist for; check all four transitions.

Visit `/budzet/miesieczny`. Expected: trail `Budżet / Miesięczny`, `h1` `Budżet`.

Then:
- Click `Roczny` → lands on the yearly view for the **same** year.
- Use the arrows to reach a **past** year on the yearly view, then click `Miesięczny` → lands on **January** of that year, not the current month.
- On the **current** year's yearly view, click `Miesięczny` → lands on the current month.
- From a monthly view of any month, click `Roczny` → keeps that year.

- [ ] **Step 6: Commit**

```bash
npx prettier --write resources/js/pages/budget
git add resources/js/pages/budget
git commit -m "refactor(budget): move the monthly/yearly switcher onto shared page tabs"
```

---

### Task 9: The table page shell

**Files:**
- Create: `resources/js/components/pagination/types.ts`
- Create: `resources/js/components/page/TablePage.vue`
- Modify: `resources/js/components/pagination/PaginationBar.vue:10-40` (move the types out)
- Modify: `resources/js/pages/transactions/Index.vue`
- Modify: `resources/js/layouts/AppLayout.vue`, `resources/js/layouts/app/AppSidebarLayout.vue`

**Interfaces:**
- Consumes: `PaginationBar` from `@/components/pagination/PaginationBar.vue`.
- Produces:
  - `resources/js/components/pagination/types.ts` exporting `PaginatorLink`, `ResourceLinksObject`, `SimplePaginator`, `ResourcePaginator` and `Paginator`.
  - `TablePage.vue` with props `paginator?: Paginator`, `query?: Record<string, unknown>`, `isEmpty?: boolean` and slots `#toolbar`, default, `#empty`.

Note for later adopters: `PaginationBar` hardcodes `route('transactions.index')` in its `pageUrl()` and `changePerPage()` helpers, so it only works on the transactions index today. `TablePage` merely forwards to it. Do not put a paginator on another page's `TablePage` without first making that route configurable — that is out of scope here.

- [ ] **Step 1: Extract the paginator types**

Create `resources/js/components/pagination/types.ts` with the types currently declared inside `PaginationBar.vue` (lines 10–40), moved verbatim:

```ts
export type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type ResourceLinksObject = {
    first?: string | null;
    last?: string | null;
    prev?: string | null;
    next?: string | null;
};

export type SimplePaginator = {
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    per_page: number;
};

export type ResourcePaginator = {
    links?: ResourceLinksObject;
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        links: PaginatorLink[];
    };
};

export type Paginator = SimplePaginator | ResourcePaginator;
```

- [ ] **Step 2: Point `PaginationBar` at the shared types**

In `resources/js/components/pagination/PaginationBar.vue`, delete the five local type declarations (lines 10–40) and import them instead:

```ts
import type { Paginator, PaginatorLink } from '@/components/pagination/types';
```

`PaginatorLink` is still referenced by the `paginatorLinks` computed, `Paginator` by the props. The other three are only used through `Paginator`, so they do not need importing. Change nothing else in the file.

- [ ] **Step 3: Create `TablePage.vue`**

`PaginationBar` requires both `paginator` and `query`, so the wrapper supplies `{}` when a page passes no query.

```vue
<script setup lang="ts">
import PaginationBar from '@/components/pagination/PaginationBar.vue';
import type { Paginator } from '@/components/pagination/types';

defineProps<{
    paginator?: Paginator;
    query?: Record<string, unknown>;
    isEmpty?: boolean;
}>();
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="$slots.toolbar" class="flex flex-wrap items-center gap-2">
            <slot name="toolbar" />
        </div>

        <slot v-if="isEmpty" name="empty" />
        <slot v-else />

        <PaginationBar v-if="paginator && !isEmpty" :paginator="paginator" :query="query ?? {}" />
    </div>
</template>
```

- [ ] **Step 4: Wrap the transactions index**

In `resources/js/pages/transactions/Index.vue`:

- Delete the `BreadcrumbItem` import, the `const breadcrumbs = computed<BreadcrumbItem[]>(...)` block, `:breadcrumbs="breadcrumbs"` on `<AppLayout>`, and the `<Head :title="..." />` element (plus `Head` from the `@inertiajs/vue3` import if unused afterwards).
- Wrap the existing content in `<TablePage>`, moving the existing `PaginationBar` call into the component's `paginator` / `query` props and deleting the standalone `<PaginationBar>` element.
- Move the two empty states into `#empty` and drive `:is-empty` from the same condition they use today.
- Leave `TransactionsIndexHeaderFilters` in `#headerActions`, and leave the `<table>`, its sortable headers, the summary cards, the skeleton rows and the mobile card list byte-for-byte unchanged.

- [ ] **Step 5: Delete the temporary `breadcrumbs` prop**

This was the last page still passing it. Confirm first:

Run: `rg -n "breadcrumbs|BreadcrumbItem" resources/js/pages`
Expected: no matches. If anything still matches, migrate that page before continuing.

Then remove the `breadcrumbs?: BreadcrumbItemType[]` prop, its comment and the now-unused `import type { BreadcrumbItemType } from '@/types';` from both `resources/js/layouts/AppLayout.vue` and `resources/js/layouts/app/AppSidebarLayout.vue`.

`BreadcrumbItem` / `BreadcrumbItemType` stay in `resources/js/types/index.ts` — `PageHeader` still renders the `ui/breadcrumb` primitives.

- [ ] **Step 6: Verify typecheck, lint and tests**

Run: `npm run typecheck && npm run lint:check && npm test`
Expected: all exit 0.

- [ ] **Step 7: Manual smoke**

Visit `/transakcje` and confirm against the pre-change behaviour:
- `h1` `Transakcje` with the description below it, filters on the right of the header row.
- Sorting by date and by amount still works and `aria-sort` still flips.
- Pagination and the per-page dropdown preserve the active filters.
- The filtered-empty and first-use empty states each still appear in their own case.
- The mobile card list still replaces the table below `md`.

- [ ] **Step 8: Commit**

```bash
npx prettier --write resources/js/components/page resources/js/components/pagination resources/js/pages/transactions/Index.vue resources/js/layouts
git add resources/js/components/page resources/js/components/pagination resources/js/pages/transactions/Index.vue resources/js/layouts
git commit -m "feat(chrome): add the table page shell and adopt it on transactions"
```

---

### Task 10: Cleanup, docs and full verification

**Files:**
- Modify: `resources/js/locales/en.json`, `resources/js/locales/pl.json`
- Modify: `.docs/superpowers/specs/2026-08-20-remove-breadcrumbs-design.md`
- Modify: `.docs/checklist.md`

**Interfaces:**
- Consumes: everything above.
- Produces: nothing.

- [ ] **Step 1: Confirm the three keys really are dead**

Run: `rg -n "budget.monthly.title|budget.yearly.title|settings.description" resources/js`
Expected: matches only inside `resources/js/locales/*.json`. If anything else still references them, stop and fix that usage instead of deleting the key.

- [ ] **Step 2: Delete them from both locale files**

Remove `budget.monthly.title`, `budget.yearly.title` and `settings.description` from `en.json` and `pl.json`. Keep every sibling under `budget.monthly` / `budget.yearly` — only the `title` entries go.

- [ ] **Step 3: Mark the superseded spec**

At the top of `.docs/superpowers/specs/2026-08-20-remove-breadcrumbs-design.md`, replace `**Status:** Approved` with:

```markdown
**Status:** Superseded by `.docs/superpowers/specs/2026-08-25-page-header-tabs-design.md`
```

- [ ] **Step 4: Reconcile the checklist**

Append a section to `.docs/checklist.md`, matching the existing bold-header plus `- [x]` style used by the sections above it (the file is written in Polish):

```markdown
**Nagłówek strony, breadcrumbs i taby (`2026-08-25-page-header-tabs-design.md`):**

- [x] Centralny rejestr stron (`lib/pageRegistry.ts`) — tytuł, rodzic, grupa tabów
- [x] `PageHeader` w layoucie: breadcrumbs + `h1` + opis + akcje + `<Head>`; strony nie deklarują chrome
- [x] `PageTabs` (nawigacja, nie ARIA tabs) — kieszenie (filtr) i budżet (miesięczny/roczny)
- [x] `TablePage` — szkielet stron z danymi; wdrożony na indeksie transakcji
- [x] Testy: Vitest, `pageRegistry.test.ts` + `pageChrome.test.ts`
```

- [ ] **Step 5: Run the full verification suite**

```bash
npm test
npm run typecheck
npm run lint:check
npm run format:check
npm run build
./vendor/bin/sail artisan test --compact
```

Expected: all exit 0. `npm run build` matters here because SSR builds the same components; a `usePage()` call reached outside a component context would surface at this point.

- [ ] **Step 6: Final smoke across the app**

Walk every registered route and confirm each shows exactly one `h1` and a sensible trail: `/panel`, `/konta`, `/konta/create`, a `/konta/{id}/edit`, `/transakcje`, `/transakcje/create`, a `/transakcje/{id}/edit`, `/transfery/utworz`, `/kategorie`, `/kieszenie`, `/budzet/miesieczny`, `/budzet/roczny`, `/ustawienia/profil`.

Expected trails: `Konta / mBank ROR`, `Transakcje / Transfer`, `Kieszenie / Aktywne`, `Budżet / Miesięczny`, `Ustawienia / Profil`.

Then narrow the viewport below `sm` and confirm the trail collapses to a single back-link with a chevron, and that on root pages it disappears entirely.

- [ ] **Step 7: Commit**

```bash
npx prettier --write resources/js/locales/en.json resources/js/locales/pl.json
git add resources/js/locales .docs/superpowers/specs/2026-08-20-remove-breadcrumbs-design.md .docs/checklist.md
git commit -m "chore(chrome): drop obsolete i18n keys and reconcile docs"
```
