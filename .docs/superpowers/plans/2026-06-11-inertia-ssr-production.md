# Inertia SSR Production Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable production-ready Inertia SSR for the whole Laravel/Inertia/Vue application with staged rollout controls and CSR fallback.

**Architecture:** Add a Vue SSR entry point and hydrate the browser app with `createSSRApp`. Run `php artisan inertia:start-ssr` as a Supervisor-managed process in the existing production `app` container. Keep `INERTIA_SSR_ENABLED` as the global kill switch, add optional path exclusions, and make deploy SSR health checks warn without leaving the app unavailable.

**Tech Stack:** Laravel 13, Inertia Laravel v2, Inertia Vue v2, Vue 3, Vite 6, TypeScript, Docker Compose, Supervisor, Node 24.

**Spec:** `.docs/superpowers/specs/2026-06-11-inertia-ssr-production-design.md`  
**Suggested branch:** `improvement/inertia-ssr-production`

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `app/Http/Middleware/HandleInertiaRequests.php` | Share Ziggy routes/location with the SSR process |
| Modify | `resources/js/i18n.ts` | Provide per-app i18n instances so SSR requests do not share mutable locale state |
| Modify | `resources/js/app.ts` | Hydrate browser-rendered Inertia pages with `createSSRApp`; keep browser-only boot code client-side |
| Create | `resources/js/ssr.ts` | Inertia Vue SSR server entry point |
| Modify | `vite.config.ts` | Register `resources/js/ssr.ts` as the Laravel Vite SSR entry |
| Modify | `resources/js/composables/useAppearance.ts` | Remove top-level browser API access |
| Modify | `resources/js/layouts/settings/Layout.vue` | Replace top-level `window.location` with Inertia page URL |
| Modify | `resources/js/components/PlaceholderPattern.vue` | Replace random ID generation with stable Vue ID |
| Modify | `resources/js/components/ui/sidebar/SidebarMenuSkeleton.vue` | Replace random width generation with deterministic prop/default |
| Modify | `resources/js/components/ui/sidebar/SidebarProvider.vue` | Guard cookie write for SSR |
| Create | `app/Http/Middleware/ConfigureInertiaSsr.php` | Disable SSR for configured path patterns during rollout |
| Modify | `bootstrap/app.php` | Register `ConfigureInertiaSsr` in the web middleware stack |
| Modify | `config/inertia.php` | Add `INERTIA_SSR_EXCEPT_PATHS` config |
| Create | `tests/Feature/Infrastructure/ConfigureInertiaSsrTest.php` | Prove path-level SSR disabling |
| Modify | `docker/8.5/Dockerfile.prod` | Build SSR bundle and install Node in runtime image |
| Modify | `docker/8.5/supervisord.prod.conf` | Run Inertia SSR under Supervisor |
| Modify | `scripts/deploy.sh` | Add non-fatal SSR health check |
| Modify | `scripts/update.sh` | Keep update behavior aligned with deploy |
| Modify | `.env.example` | Document SSR production env flags |
| Modify | `.docs/tech-stack.md` | Document production SSR build/check commands |

---

## Task 1: Add SSR entry point and hydration bootstrap

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/i18n.ts`
- Modify: `resources/js/app.ts`
- Create: `resources/js/ssr.ts`
- Modify: `vite.config.ts`

- [ ] **Step 1: Run the current SSR build to capture the baseline failure**

Run:

```bash
npm run build:ssr
```

Expected: FAIL because the project has no SSR entry point registered in `vite.config.ts` and no `resources/js/ssr.ts`.

- [ ] **Step 2: Share Ziggy with Inertia page props**

In `app/Http/Middleware/HandleInertiaRequests.php`, add:

```php
use Tighten\Ziggy\Ziggy;
```

Inside the returned array in `share()`, add this prop after `locale`:

```php
'ziggy' => fn (): array => [
    ...(new Ziggy)->toArray(),
    'location' => $request->url(),
],
```

This makes `route()` available to the Node SSR process, which cannot read `@routes(json: true)` from the browser DOM.

- [ ] **Step 3: Replace `resources/js/i18n.ts` with an i18n factory**

Use a factory so every SSR render receives its own i18n instance.

```ts
import { createI18n } from 'vue-i18n';

import en from '@/locales/en.json';
import pl from '@/locales/pl.json';

export const supportedLocales = ['en', 'pl'] as const;
export type SupportedLocale = (typeof supportedLocales)[number];

export function resolveSupportedLocale(locale: string | undefined): SupportedLocale {
    return supportedLocales.includes(locale as SupportedLocale) ? (locale as SupportedLocale) : 'pl';
}

export function createAppI18n(locale: string | undefined = 'pl') {
    return createI18n({
        legacy: false,
        globalInjection: true,
        locale: resolveSupportedLocale(locale),
        fallbackLocale: 'en',
        messages: {
            en,
            pl,
        },
    });
}
```

- [ ] **Step 4: Replace `resources/js/app.ts` with the hydration-aware browser entry**

```ts
import 'vue-sonner/style.css';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createSSRApp, h } from 'vue';
import { route } from 'ziggy-js';
import type { Config as ZiggyConfig } from 'ziggy-js';
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
```

- [ ] **Step 5: Create `resources/js/ssr.ts`**

```ts
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import type { Config as ZiggyConfig } from 'ziggy-js';
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
            const pageProps = page.props as SsrPageProps;
            const locale = resolveSupportedLocale(pageProps.locale);
            const i18n = createAppI18n(locale);
            const ziggy = {
                ...pageProps.ziggy,
                location: pageProps.ziggy.location ? new URL(pageProps.ziggy.location) : undefined,
            } as ZiggyConfig;

            globalThis.Ziggy = ziggy;

            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(i18n)
                .use(ZiggyVue, ziggy);
        },
    }),
);
```

- [ ] **Step 6: Add the SSR entry to `vite.config.ts`**

The Laravel plugin block should become:

```ts
laravel({
    input: ['resources/js/app.ts'],
    ssr: 'resources/js/ssr.ts',
    refresh: true,
}),
```

- [ ] **Step 7: Run SSR build**

Run:

```bash
npm run build:ssr
```

Expected: It may still FAIL on browser-only code. The SSR entry and Vite config errors should be gone. Any remaining failure should point to a specific SSR-unsafe module, which Task 2 addresses.

- [ ] **Step 8: Format PHP touched in this task**

Run:

```bash
vendor/bin/pint --dirty --format agent
```

Expected: PASS. Pint should only touch `app/Http/Middleware/HandleInertiaRequests.php` from this task.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/i18n.ts resources/js/app.ts resources/js/ssr.ts vite.config.ts
git commit -m "$(cat <<'EOF'
feat(inertia): add SSR entry point and hydration bootstrap

EOF
)"
```

---

## Task 2: Fix known SSR-unsafe frontend code

**Files:**
- Modify: `resources/js/composables/useAppearance.ts`
- Modify: `resources/js/layouts/settings/Layout.vue`
- Modify: `resources/js/components/PlaceholderPattern.vue`
- Modify: `resources/js/components/ui/sidebar/SidebarMenuSkeleton.vue`
- Modify: `resources/js/components/ui/sidebar/SidebarProvider.vue`

- [ ] **Step 1: Replace `resources/js/composables/useAppearance.ts`**

This removes top-level `window.matchMedia` so importing the composable during SSR does not crash.

```ts
import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

function getStoredAppearance(): Appearance | null {
    if (typeof localStorage === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
}

function getSystemTheme(): 'light' | 'dark' {
    if (typeof window === 'undefined') {
        return 'light';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function updateTheme(value: Appearance): void {
    if (typeof document === 'undefined') {
        return;
    }

    if (value === 'system') {
        document.documentElement.classList.toggle('dark', getSystemTheme() === 'dark');

        return;
    }

    document.documentElement.classList.toggle('dark', value === 'dark');
}

function handleSystemThemeChange(): void {
    updateTheme(getStoredAppearance() || 'system');
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    updateTheme(getStoredAppearance() || 'system');
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    const appearance = ref<Appearance>('system');

    onMounted(() => {
        initializeTheme();

        const savedAppearance = getStoredAppearance();

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    function updateAppearance(value: Appearance): void {
        appearance.value = value;

        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('appearance', value);
        }

        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
    };
}
```

- [ ] **Step 2: Replace `resources/js/layouts/settings/Layout.vue`**

Use the Inertia page URL instead of `window.location.pathname`.

```vue
<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

const { t } = useI18n();
const page = usePage();

const sidebarNavItems = computed<NavItem[]>(() => [
    {
        title: t('settings.nav.profile'),
        href: route('profile.edit'),
    },
    {
        title: t('settings.nav.password'),
        href: route('password.edit'),
    },
    {
        title: t('settings.nav.appearance'),
        href: route('appearance'),
    },
]);

const currentPath = computed(() => page.url.split('?')[0]);
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="t('settings.title')" :description="t('settings.description')" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-x-0 space-y-1">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 3: Replace random SVG ID in `resources/js/components/PlaceholderPattern.vue`**

```vue
<script setup lang="ts">
import { useId } from 'vue';

const patternId = `pattern-${useId()}`;
</script>

<template>
    <svg class="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" fill="none">
        <defs>
            <pattern :id="patternId" x="0" y="0" width="8" height="8" patternUnits="userSpaceOnUse">
                <path d="M-1 5L5 -1M3 9L8.5 3.5" stroke-width="0.5"></path>
            </pattern>
        </defs>
        <rect stroke="none" :fill="`url(#${patternId})`" width="100%" height="100%"></rect>
    </svg>
</template>
```

- [ ] **Step 4: Replace random skeleton width in `resources/js/components/ui/sidebar/SidebarMenuSkeleton.vue`**

```vue
<script setup lang="ts">
import Skeleton from '@/components/ui/skeleton/Skeleton.vue';
import { cn } from '@/lib/utils';
import { computed, type HTMLAttributes } from 'vue';

const props = withDefaults(
    defineProps<{
        showIcon?: boolean;
        class?: HTMLAttributes['class'];
        width?: string;
    }>(),
    {
        width: '66%',
    },
);

const skeletonWidth = computed(() => props.width);
</script>

<template>
    <div data-sidebar="menu-skeleton" :class="cn('flex h-8 items-center gap-2 rounded-md px-2', props.class)">
        <Skeleton v-if="showIcon" class="size-4 rounded-md" data-sidebar="menu-skeleton-icon" />

        <Skeleton class="h-4 max-w-[--skeleton-width] flex-1" data-sidebar="menu-skeleton-text" :style="{ '--skeleton-width': skeletonWidth }" />
    </div>
</template>
```

- [ ] **Step 5: Guard the cookie write in `resources/js/components/ui/sidebar/SidebarProvider.vue`**

Change only `setOpen`:

```ts
function setOpen(value: boolean) {
    open.value = value; // emits('update:open', value)

    if (typeof document !== 'undefined') {
        document.cookie = `${SIDEBAR_COOKIE_NAME}=${open.value}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`;
    }
}
```

- [ ] **Step 6: Run SSR build**

Run:

```bash
npm run build:ssr
```

Expected: PASS. If it fails, fix only the named browser-only import or hydration-risk module reported by the build, then rerun the same command.

- [ ] **Step 7: Run frontend typecheck**

Run:

```bash
npm run typecheck
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/composables/useAppearance.ts resources/js/layouts/settings/Layout.vue resources/js/components/PlaceholderPattern.vue resources/js/components/ui/sidebar/SidebarMenuSkeleton.vue resources/js/components/ui/sidebar/SidebarProvider.vue
git commit -m "$(cat <<'EOF'
fix(frontend): make shared Vue code safe for Inertia SSR

EOF
)"
```

---

## Task 3: Add route-level SSR rollout control

**Files:**
- Modify: `config/inertia.php`
- Create: `app/Http/Middleware/ConfigureInertiaSsr.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Feature/Infrastructure/ConfigureInertiaSsrTest.php`

- [ ] **Step 1: Create failing middleware tests**

Create `tests/Feature/Infrastructure/ConfigureInertiaSsrTest.php`:

```php
<?php

use App\Http\Middleware\ConfigureInertiaSsr;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('disables inertia ssr for configured path patterns', function () {
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.except_paths' => ['settings/*', 'admin/*'],
    ]);

    $request = Request::create('/settings/profile');

    (new ConfigureInertiaSsr)->handle($request, fn () => new Response('ok'));

    expect(config('inertia.ssr.enabled'))->toBeFalse();
});

it('keeps inertia ssr enabled for non-excluded paths', function () {
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.except_paths' => ['settings/*'],
    ]);

    $request = Request::create('/accounts');

    (new ConfigureInertiaSsr)->handle($request, fn () => new Response('ok'));

    expect(config('inertia.ssr.enabled'))->toBeTrue();
});
```

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Infrastructure/ConfigureInertiaSsrTest.php
```

Expected: FAIL because `App\Http\Middleware\ConfigureInertiaSsr` does not exist.

- [ ] **Step 2: Add `except_paths` to `config/inertia.php`**

Inside the existing `ssr` array, after `ensure_bundle_exists`, add:

```php
'except_paths' => array_values(array_filter(array_map(
    static fn (string $path): string => trim($path),
    explode(',', (string) env('INERTIA_SSR_EXCEPT_PATHS', '')),
))),
```

- [ ] **Step 3: Create `app/Http/Middleware/ConfigureInertiaSsr.php`**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureInertiaSsr
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach (config('inertia.ssr.except_paths', []) as $path) {
            if ($request->is($path)) {
                config(['inertia.ssr.enabled' => false]);

                break;
            }
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register middleware in `bootstrap/app.php`**

Add the import:

```php
use App\Http\Middleware\ConfigureInertiaSsr;
```

Update the web append block to run it before `HandleInertiaRequests`:

```php
$middleware->web(append: [
    ConfigureInertiaSsr::class,
    HandleInertiaRequests::class,
    AddLinkHeadersForPreloadedAssets::class,
]);
```

- [ ] **Step 5: Run middleware tests**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Infrastructure/ConfigureInertiaSsrTest.php
```

Expected: PASS.

- [ ] **Step 6: Format PHP and commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/inertia.php app/Http/Middleware/ConfigureInertiaSsr.php bootstrap/app.php tests/Feature/Infrastructure/ConfigureInertiaSsrTest.php
git commit -m "$(cat <<'EOF'
feat(inertia): add SSR route rollout control

EOF
)"
```

---

## Task 4: Run SSR in the production container

**Files:**
- Modify: `docker/8.5/Dockerfile.prod`
- Modify: `docker/8.5/supervisord.prod.conf`

- [ ] **Step 1: Update the asset build in `docker/8.5/Dockerfile.prod`**

Change the asset stage build command from:

```dockerfile
RUN npm run build
```

to:

```dockerfile
RUN npm run build:ssr
```

- [ ] **Step 2: Install Node in the runtime stage**

In the runtime stage, add the NodeSource key and repository before the runtime `apt-get install -y` list, then include `nodejs` in the package list. The runtime install block should include these Node-specific lines:

```dockerfile
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
```

The package list should include:

```dockerfile
        nodejs \
```

Keep `ARG NODE_VERSION=24` in the runtime stage.

- [ ] **Step 3: Add the Supervisor program**

Append this block to `docker/8.5/supervisord.prod.conf`:

```ini
[program:inertia-ssr]
command=php /var/www/html/artisan inertia:start-ssr
autostart=true
autorestart=true
user=%(ENV_SUPERVISOR_PHP_USER)s
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/inertia-ssr.log
stdout_logfile_maxbytes=0
```

- [ ] **Step 4: Build the production image**

Run:

```bash
docker compose -f docker-compose.prod.yml build app
```

Expected: PASS. The output should include `npm run build:ssr` and finish with `wallet-master-app:prod` built.

- [ ] **Step 5: Commit**

```bash
git add docker/8.5/Dockerfile.prod docker/8.5/supervisord.prod.conf
git commit -m "$(cat <<'EOF'
feat(deploy): run Inertia SSR in production container

EOF
)"
```

---

## Task 5: Add deploy checks and env documentation

**Files:**
- Modify: `scripts/deploy.sh`
- Modify: `scripts/update.sh`
- Modify: `.env.example`
- Modify: `.docs/tech-stack.md`

- [ ] **Step 1: Add non-fatal SSR health check to `scripts/deploy.sh`**

After the Laravel cache commands, add:

```bash
echo "==> Checking Inertia SSR..."
if $COMPOSE exec -T app php artisan inertia:check-ssr; then
    echo "==> Inertia SSR is healthy."
else
    echo "WARNING: Inertia SSR health check failed; the app will continue with CSR fallback." >&2
fi
```

- [ ] **Step 2: Add the same health check to `scripts/update.sh`**

After the Laravel cache commands, add the same block:

```bash
echo "==> Checking Inertia SSR..."
if $COMPOSE exec -T app php artisan inertia:check-ssr; then
    echo "==> Inertia SSR is healthy."
else
    echo "WARNING: Inertia SSR health check failed; the app will continue with CSR fallback." >&2
fi
```

- [ ] **Step 3: Document SSR env in `.env.example`**

In the production env comment block, add:

```dotenv
# INERTIA_SSR_ENABLED=true
# INERTIA_SSR_URL=http://127.0.0.1:13714
# INERTIA_SSR_ENSURE_BUNDLE_EXISTS=true
# INERTIA_SSR_EXCEPT_PATHS=          # comma-separated path patterns, e.g. settings/*,admin/*
```

After the default Vite variables, add local defaults:

```dotenv
INERTIA_SSR_ENABLED=false
INERTIA_SSR_URL=http://127.0.0.1:13714
INERTIA_SSR_ENSURE_BUNDLE_EXISTS=true
INERTIA_SSR_EXCEPT_PATHS=
```

- [ ] **Step 4: Update `.docs/tech-stack.md` commands**

Add a production SSR line under `## Commands`:

```md
- **Production SSR:** `npm run build:ssr` builds client + SSR bundles · `php artisan inertia:start-ssr` starts the SSR server · `php artisan inertia:check-ssr` verifies it
```

- [ ] **Step 5: Commit**

```bash
git add scripts/deploy.sh scripts/update.sh .env.example .docs/tech-stack.md
git commit -m "$(cat <<'EOF'
chore(deploy): document and check Inertia SSR health

EOF
)"
```

---

## Task 6: Full verification and production smoke

**Files:**
- No planned source edits unless a verification command reveals a specific issue.

- [ ] **Step 1: Run PHP formatting**

Run:

```bash
vendor/bin/pint --dirty --format agent
```

Expected: PASS with no uncommitted formatting changes, or Pint modifies only files touched by this plan.

- [ ] **Step 2: Run middleware test**

Run:

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Infrastructure/ConfigureInertiaSsrTest.php
```

Expected: PASS.

- [ ] **Step 3: Run frontend verification**

Run:

```bash
npm run build:ssr
npm run typecheck
npm run lint:check
```

Expected: PASS for all three commands.

- [ ] **Step 4: Build the production image**

Run:

```bash
docker compose -f docker-compose.prod.yml build app
```

Expected: PASS.

- [ ] **Step 5: Start production stack locally or on the target host**

Run:

```bash
docker compose -f docker-compose.prod.yml up -d
```

Expected: `wallet-app`, `wallet-mysql`, `wallet-redis`, and `wallet-typesense` are running or healthy.

- [ ] **Step 6: Check SSR process**

Run:

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan inertia:check-ssr
```

Expected: PASS.

- [ ] **Step 7: Check fallback mode**

Temporarily set `INERTIA_SSR_ENABLED=false` in the production `.env`, restart the app container, and run:

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml restart app
```

Expected: `/up` returns healthy and a normal Inertia page still loads through CSR. Restore `INERTIA_SSR_ENABLED=true` after the check.

- [ ] **Step 8: Browser smoke key pages**

Open the production URL and visit:

- `/login`
- `/accounts`
- `/transactions`
- `/settings/profile`

Expected: pages render, navigation remains interactive, and the browser console has no hydration mismatch warnings or SSR-related runtime errors.

- [ ] **Step 9: Commit final verification fixes if any**

If verification required changes, inspect the exact paths first:

```bash
git status --short
```

If `git status --short` shows only files already listed in this plan, add those printed paths explicitly and commit:

```bash
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/i18n.ts resources/js/app.ts resources/js/ssr.ts vite.config.ts resources/js/composables/useAppearance.ts resources/js/layouts/settings/Layout.vue resources/js/components/PlaceholderPattern.vue resources/js/components/ui/sidebar/SidebarMenuSkeleton.vue resources/js/components/ui/sidebar/SidebarProvider.vue config/inertia.php app/Http/Middleware/ConfigureInertiaSsr.php bootstrap/app.php tests/Feature/Infrastructure/ConfigureInertiaSsrTest.php docker/8.5/Dockerfile.prod docker/8.5/supervisord.prod.conf scripts/deploy.sh scripts/update.sh .env.example .docs/tech-stack.md
git commit -m "$(cat <<'EOF'
fix(inertia): resolve SSR verification issues

EOF
)"
```

If there were no verification fixes, do not create an empty commit. If `git status --short` shows unrelated paths, stop and ask the user before staging them.

---

## Self-Review Notes

- Spec coverage: Task 1 covers SSR entry, hydration, per-request i18n, and Ziggy route config for Node. Task 2 covers browser-only frontend issues. Task 3 covers route-level rollout. Task 4 covers Docker runtime and Supervisor. Task 5 covers deploy health checks and env/docs. Task 6 covers verification and CSR fallback.
- Placeholder scan: no task uses symbolic file placeholders or undefined "handle later" steps.
- Type consistency: `createAppI18n`, `resolveSupportedLocale`, and `ConfigureInertiaSsr` are introduced before later tasks reference them.
