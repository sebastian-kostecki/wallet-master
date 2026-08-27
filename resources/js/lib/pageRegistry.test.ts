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

    it('registers accounts tabs with kind query params', () => {
        const group = pageTabGroups.accounts;

        expect(pageNodes.accounts.tabGroup).toBe('accounts');
        expect(group.defaultKey).toBe('accounts');
        expect(group.tabs.map((tab) => tab.key)).toEqual(['accounts', 'wallets', 'deposits']);
        expect(group.tabs[0]).toMatchObject({
            route: 'accounts.index',
            params: { kind: 'accounts' },
            labelKey: 'accounts.tabs.accounts',
        });
        expect(group.tabs[1]).toMatchObject({
            route: 'accounts.index',
            params: { kind: 'wallets' },
            labelKey: 'accounts.tabs.wallets',
        });
        expect(group.tabs[2]).toMatchObject({
            route: 'accounts.index',
            params: { kind: 'deposits' },
            labelKey: 'accounts.tabs.deposits',
        });
    });

    it('registers settingsCategories tab for categories route', () => {
        const group = pageTabGroups.settingsCategories;

        expect(pageNodes.categories.tabGroup).toBe('settingsCategories');
        expect(pageNodes.pockets.tabGroup).toBeUndefined();
        expect(group.defaultKey).toBe('categories');
        expect(group.tabs.map((tab) => tab.key)).toEqual(['categories']);
        expect(group.tabs[0]).toMatchObject({
            route: 'categories.index',
            labelKey: 'categories.index.title',
        });
        expect(pageTabGroups.catalog).toBeUndefined();
    });

    it('does not register transfer create as a page', () => {
        expect(pageNodes['transfers.create']).toBeUndefined();
    });
});
