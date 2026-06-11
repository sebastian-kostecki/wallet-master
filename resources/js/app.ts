import 'vue-sonner/style.css';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createSSRApp, h } from 'vue';
import type { Config as ZiggyConfig } from 'ziggy-js';
import { route } from 'ziggy-js';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import { createAppI18n, resolveSupportedLocale } from './i18n';
import { bootstrapZiggyFromDom } from './lib/ziggy';

type WalletPageProps = Record<string, unknown> & {
    locale?: string;
    ziggy?: ZiggyConfig;
};

function resolveZiggyConfig(pageProps: WalletPageProps): ZiggyConfig | undefined {
    return pageProps.ziggy ?? globalThis.Ziggy;
}

bootstrapZiggyFromDom();
globalThis.route = route;

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const pageProps = props.initialPage.props as WalletPageProps;
        const locale = resolveSupportedLocale(pageProps.locale);
        const i18n = createAppI18n(locale);
        const ziggy = resolveZiggyConfig(pageProps);

        if (ziggy) {
            globalThis.Ziggy = ziggy;
        }

        const vueApp = createSSRApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n);

        if (ziggy) {
            vueApp.use(ZiggyVue, ziggy);
        } else {
            vueApp.use(ZiggyVue);
        }

        vueApp.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
