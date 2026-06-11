import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import type { Config as ZiggyConfig } from 'ziggy-js';
import { route } from 'ziggy-js';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createAppI18n, resolveSupportedLocale } from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

type SsrZiggyConfig = ZiggyConfig & {
    location?: string | URL;
};

type SsrPageProps = Record<string, unknown> & {
    locale?: string;
    ziggy: SsrZiggyConfig;
};

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
        setup({ App, props, plugin }) {
            const pageProps = page.props as unknown as SsrPageProps;
            const locale = resolveSupportedLocale(pageProps.locale);
            const i18n = createAppI18n(locale);
            const ziggy = {
                ...pageProps.ziggy,
                location: pageProps.ziggy.location ? new URL(pageProps.ziggy.location) : undefined,
            } as ZiggyConfig;

            globalThis.Ziggy = ziggy;
            globalThis.route = route;

            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(i18n)
                .use(ZiggyVue, ziggy);
        },
    }),
);
