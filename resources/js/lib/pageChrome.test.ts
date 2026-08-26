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
        const chrome = resolvePageChrome(at('dashboard'), {}, deps);

        expect(chrome.crumbs).toEqual([{ title: 'dashboard.title', href: null }]);
        expect(chrome.title).toBe('dashboard.title');
        expect(chrome.documentTitle).toBe('dashboard.title');
        expect(chrome.tabs).toEqual([]);
    });

    it('falls back to the Rachunki tab when kind is missing', () => {
        const chrome = resolvePageChrome(at('accounts.index'), {}, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'accounts.index.title', href: '/accounts.index' },
            { title: 'accounts.tabs.accounts', href: null },
        ]);
        expect(chrome.title).toBe('accounts.index.title');
        expect(chrome.documentTitle).toBe('accounts.index.title · accounts.tabs.accounts');
        expect(chrome.tabs).toHaveLength(2);
        expect(chrome.tabs.find((tab) => tab.isActive)?.key).toBe('accounts');
        expect(chrome.tabs.find((tab) => tab.key === 'wallets')?.href).toBe('/accounts.index?kind=wallets');
    });

    it('activates Portfele when kind=wallets', () => {
        const chrome = resolvePageChrome(at('accounts.index', { kind: 'wallets' }), {}, deps);

        expect(chrome.title).toBe('accounts.index.title');
        expect(chrome.documentTitle).toBe('accounts.index.title · accounts.tabs.wallets');
        expect(chrome.tabs.find((tab) => tab.isActive)?.key).toBe('wallets');
    });

    it('links every ancestor and leaves only the leaf unlinked', () => {
        const chrome = resolvePageChrome(at('transfers.create'), {}, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'transactions.index.title', href: '/transactions.index' },
            { title: 'transfers.create.title', href: null },
        ]);
    });

    it('lets a detail page override the leaf, the title and the document title', () => {
        const chrome = resolvePageChrome(at('profile.edit'), { title: 'Jan Kowalski', description: 'settings.profile.heading' }, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'settings.title', href: '/profile.edit' },
            { title: 'Jan Kowalski', href: null },
        ]);
        expect(chrome.title).toBe('Jan Kowalski');
        expect(chrome.description).toBe('settings.profile.heading');
        expect(chrome.documentTitle).toBe('Jan Kowalski');
    });

    it('activates the pnl catalog tab on categories index', () => {
        const chrome = resolvePageChrome(at('categories.index'), {}, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'categories.index.title', href: '/categories.index' },
            { title: 'categories.tabs.pnl', href: null },
        ]);
        expect(chrome.title).toBe('categories.index.title');
        expect(chrome.documentTitle).toBe('categories.index.title · categories.tabs.pnl');
        expect(chrome.tabs).toHaveLength(2);
        expect(chrome.tabs.find((tab) => tab.isActive)?.key).toBe('pnl');
        expect(chrome.tabs.find((tab) => tab.key === 'pockets')?.href).toBe('/pockets.index');
    });

    it('activates the pockets catalog tab on pockets index', () => {
        const chrome = resolvePageChrome(at('pockets.index'), {}, deps);

        expect(chrome.crumbs).toEqual([
            { title: 'pockets.index.title', href: '/pockets.index' },
            { title: 'categories.tabs.pockets', href: null },
        ]);
        expect(chrome.title).toBe('pockets.index.title');
        expect(chrome.documentTitle).toBe('pockets.index.title · categories.tabs.pockets');
        expect(chrome.tabs).toHaveLength(2);
        expect(chrome.tabs.find((tab) => tab.isActive)?.key).toBe('pockets');
        expect(chrome.tabs.find((tab) => tab.key === 'pnl')?.href).toBe('/categories.index');
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
