# Inertia SSR production — design spec

**Status:** Approved in brainstorming (2026-06-11)  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)  
**Scope:** Production-ready Inertia SSR for the existing Laravel 13 + Vue 3 application.

## Summary

Enable Inertia server-side rendering as a production capability for the whole application, with a staged rollout and a safe fallback to normal client-side Inertia rendering. SSR should improve the first render and make the production Inertia setup complete, but it must not become a hard dependency for application availability.

## Decisions log

| Topic | Decision |
|-------|----------|
| Goal | Use the full production capabilities of Inertia, not just SEO for selected pages |
| Rollout | Target the whole application, staged with an emergency disable switch |
| Process model | Run SSR in the existing `app` container as a Supervisor-managed process |
| Runtime | Use the default Node runtime for `php artisan inertia:start-ssr` |
| Failure policy | Fall back to CSR if SSR is unhealthy; log and expose the failure during deploy |
| Route-level control | Allow SSR to be disabled for specific paths if a page is not SSR-safe |

## Current state

- `config/inertia.php` already exposes `INERTIA_SSR_ENABLED`, `INERTIA_SSR_URL`, and `INERTIA_SSR_ENSURE_BUNDLE_EXISTS`.
- `package.json` has `build:ssr`, but `vite.config.ts` does not yet define an SSR entry point.
- `resources/js/app.ts` uses `createApp`, not `createSSRApp`, so it is not ready for hydration.
- There is no `resources/js/ssr.ts` entry point.
- `docker/8.5/Dockerfile.prod` builds frontend assets in a Node stage, but the final runtime image does not include Node.
- `docker/8.5/supervisord.prod.conf` currently manages PHP, Reverb, Horizon, and scheduler, but not Inertia SSR.
- `scripts/deploy.sh` builds the image, starts the stack, runs migrations, and caches Laravel, but does not build/check SSR explicitly.

## Target architecture

SSR runs inside the production `app` container as a first-class Supervisor program:

- command: `php /var/www/html/artisan inertia:start-ssr`
- user: the same application user used by the other Laravel processes
- restart policy: `autorestart=true`
- logs: either stdout/stderr through Docker or a dedicated `storage/logs/inertia-ssr.log`, consistent with the existing production process logs

The production image must include the SSR bundle and the runtime needed to execute it. Since Inertia's SSR server runs on Node by default, the final runtime image needs Node installed or copied in from a Node-enabled stage. The asset build should produce both client and server bundles with `npm run build:ssr`.

The application keeps `INERTIA_SSR_ENABLED` as the global switch. A small middleware or service-provider hook may disable SSR per request by setting `config(['inertia.ssr.enabled' => false])` for known-problematic routes during rollout.

## Frontend design

Introduce a server entry point:

- `resources/js/ssr.ts` uses `createServer` from `@inertiajs/vue3/server`.
- It renders with `renderToString` from `vue/server-renderer`.
- It uses `createSSRApp` and registers the same SSR-safe plugins as the browser app.
- It resolves pages from `resources/js/pages/**/*.vue`.

Update the browser entry point:

- `resources/js/app.ts` switches from `createApp` to `createSSRApp` for hydration.
- Browser-only work remains in the browser entry point only.
- Shared setup should be extracted only if it reduces duplication without hiding browser-only side effects.

Browser-only concerns that must not run in the SSR process:

- `configureEcho`
- DOM-based Ziggy bootstrapping
- assigning browser globals from DOM-derived state
- `initializeTheme`
- direct use of `window`, `document`, `localStorage`, viewport APIs, timers that affect initial markup, or random values during render

Components should render the same initial DOM on the server and client. If a feature depends on browser state, it should render a stable placeholder before mount or be guarded so it only changes after hydration.

## Deployment flow

The production deploy should:

1. Build the production image.
2. Run the frontend build as `npm run build:ssr` inside the asset stage.
3. Start or restart the Docker stack.
4. Run migrations and Laravel cache commands as today.
5. Check SSR with `php artisan inertia:check-ssr`.
6. Report SSR health clearly in deploy output and logs.

The SSR health check should not make the application unavailable. If the check fails, the deploy should make the failure obvious and leave the app serving CSR-compatible Inertia responses.

Because the production scripts use `set -euo pipefail`, the SSR check must be wrapped deliberately: capture the non-zero exit code, print a clear warning, and continue after the Laravel application itself is running. A failed SSR health check is an operational warning, not a reason to leave the stack stopped.

For deploys that replace an already-running container, stopping the old SSR process is handled by container restart. If a future deploy flow updates code in-place without replacing the container, it should run `php artisan inertia:stop-ssr` and let Supervisor start the new SSR process.

## Rollout policy

Initial production rollout targets the whole app but keeps fast rollback controls:

- `INERTIA_SSR_ENABLED=false` disables SSR globally without a code rollback.
- Route-level disabling can be used temporarily for pages with hydration or browser-only runtime issues.
- Page-level exceptions should be treated as temporary compatibility fixes, not the steady-state architecture.

## Error handling and observability

SSR failures should be visible but non-fatal:

- Supervisor restarts the SSR process if it exits.
- Deploy output includes the result of `inertia:check-ssr`.
- SSR process logs are available from Docker logs or `storage/logs`.
- Laravel falls back to normal Inertia rendering when SSR cannot dispatch a response.

No user-facing error page should be introduced solely because SSR is unavailable.

## Files

| File | Change |
|------|--------|
| `resources/js/ssr.ts` | Add Inertia Vue SSR server entry point |
| `resources/js/app.ts` | Use `createSSRApp` for hydration and keep browser-only setup client-side |
| `vite.config.ts` | Add `ssr: 'resources/js/ssr.ts'` to the Laravel Vite plugin config |
| `docker/8.5/Dockerfile.prod` | Build with `npm run build:ssr`; ensure Node is available in runtime for SSR |
| `docker/8.5/supervisord.prod.conf` | Add an `inertia-ssr` Supervisor program |
| `scripts/deploy.sh` | Add SSR health check after startup/cache |
| `scripts/update.sh` | Keep behavior consistent with deploy if this script remains part of production ops |
| `.env.example` | Document `INERTIA_SSR_ENABLED`, `INERTIA_SSR_URL`, and SSR fallback expectations |
| Optional middleware/provider | Disable SSR for selected paths during staged rollout |

## Out of scope

- Moving SSR into a separate Docker Compose service.
- Switching to Bun runtime.
- SEO-specific metadata work.
- Inertia v3 upgrade.
- Replacing Laravel's built-in development server in the current production container.
- Large unrelated frontend refactors.

## Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Browser-only code crashes SSR | Audit entry points and components; guard client-only logic |
| Hydration mismatch | Keep initial server/client markup deterministic; defer browser state until mounted |
| SSR process dies | Supervisor `autorestart`; deploy health check; CSR fallback |
| Runtime image grows | Prefer minimal Node installation/copy; keep the single-container model for now |
| A page is not SSR-safe | Temporarily disable SSR per route and fix the page |

## Verification

| Check | Method |
|-------|--------|
| Client bundle builds | `npm run build` |
| SSR bundle builds | `npm run build:ssr` |
| TypeScript still passes | `npm run typecheck` |
| Lint still passes for frontend changes | `npm run lint:check` or scoped lint during implementation |
| SSR process responds | `php artisan inertia:check-ssr` inside the production container |
| App still works without SSR | Set `INERTIA_SSR_ENABLED=false` and smoke a normal Inertia page |
| Hydration is stable | Browser smoke test key pages and check console logs |

## Implementation notes for plan

1. Add the SSR entry point and Vite config first.
2. Refactor browser-only startup code out of SSR execution.
3. Build locally with `npm run build:ssr` and fix SSR runtime errors.
4. Update the production Dockerfile and Supervisor config.
5. Add deploy/update health checks.
6. Add env documentation and any route-level disable mechanism needed for staged rollout.
7. Run frontend build/typecheck/lint and production-container SSR smoke checks.
