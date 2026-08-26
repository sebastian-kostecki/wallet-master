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
