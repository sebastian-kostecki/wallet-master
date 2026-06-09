import 'vue-sonner/style.css';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { route } from 'ziggy-js';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import type { SupportedLocale } from './i18n';
import { i18n, supportedLocales } from './i18n';
import { bootstrapZiggyFromDom } from './lib/ziggy';

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
        const initialLocale = (props.initialPage.props.locale as string | undefined) ?? 'pl';
        const resolvedLocale: SupportedLocale = supportedLocales.includes(initialLocale as SupportedLocale)
            ? (initialLocale as SupportedLocale)
            : 'pl';
        i18n.global.locale.value = resolvedLocale;

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
