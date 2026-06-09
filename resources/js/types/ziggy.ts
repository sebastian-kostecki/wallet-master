import { Config, RouteParams } from 'ziggy-js';

declare global {
    function route(): Config;
    function route(
        name: string,
        params?: RouteParams<typeof name> | number | string | Record<string, unknown> | undefined,
        absolute?: boolean,
    ): string;
}

declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        route: typeof route;
    }
}
