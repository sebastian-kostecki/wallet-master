import type { Config } from 'ziggy-js';
import { route as ziggyRoute } from 'ziggy-js';

declare global {
    var route: typeof ziggyRoute;
    var Ziggy: Config;
}

declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        route: typeof ziggyRoute;
    }
}

export {};
