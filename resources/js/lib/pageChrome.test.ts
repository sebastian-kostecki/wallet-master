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
