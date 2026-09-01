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

/**
 * Ziggy shares `location` as a full URL string from the server. The Router expects
 * a Location-like object (host/pathname/search). SSR already converts this; mirror
 * that here so current() matching does not depend on APP_URL host/port matching
 * the browser (e.g. Sail on localhost:8080 vs APP_URL=http://localhost).
 */
function ziggyConfigFromPage(raw: SharedData['ziggy']): ZiggyConfig {
    const location =
        typeof raw.location === 'string' && raw.location !== '' ? new URL(raw.location) : (raw.location as unknown as ZiggyConfig['location']);

    return { ...(raw as unknown as ZiggyConfig), location };
}

export function useCurrentPage(overrides: ComputedRef<ChromeOverrides>): ComputedRef<PageChrome> {
    const page = usePage<SharedData>();
    const { t } = useI18n();

    return computed(() => {
        const ziggy = ziggyConfigFromPage(page.props.ziggy);
        // absolute=false: match on pathname only — host/port from APP_URL often differ from the browser under Sail
        const router = route(undefined, undefined, false, ziggy);

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
