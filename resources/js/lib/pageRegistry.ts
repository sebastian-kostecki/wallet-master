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
    settingsCategories: {
        defaultKey: 'categories',
        tabs: [{ key: 'categories', labelKey: 'categories.index.title', route: 'categories.index' }],
    },
    accounts: {
        defaultKey: 'accounts',
        tabs: [
            { key: 'accounts', labelKey: 'accounts.tabs.accounts', route: 'accounts.index', params: { kind: 'accounts' } },
            { key: 'wallets', labelKey: 'accounts.tabs.wallets', route: 'accounts.index', params: { kind: 'wallets' } },
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

    accounts: { titleKey: 'accounts.index.title', matchRoutes: ['accounts.index'], tabGroup: 'accounts' },

    transactions: {
        titleKey: 'transactions.index.title',
        descriptionKey: 'transactions.index.description',
        matchRoutes: ['transactions.index'],
    },

    categories: {
        titleKey: 'settings.title',
        descriptionKey: 'categories.index.description',
        matchRoutes: ['categories.index'],
        tabGroup: 'settingsCategories',
    },

    pockets: { titleKey: 'pockets.index.title', matchRoutes: ['pockets.index'] },

    budget: { titleKey: 'budget.nav', matchRoutes: ['budget.monthly', 'budget.yearly'], tabGroup: 'budget' },

    // Groups the settings pages; owns no route, so it links to the first settings screen.
    settings: { titleKey: 'settings.title', route: 'profile.edit', matchRoutes: [] },
    'settings.profile': { titleKey: 'settings.nav.profile', parent: 'settings', matchRoutes: ['profile.edit'] },
    'settings.password': { titleKey: 'settings.nav.password', parent: 'settings', matchRoutes: ['password.edit'] },
    'settings.appearance': { titleKey: 'settings.nav.appearance', parent: 'settings', matchRoutes: ['appearance'] },
};
