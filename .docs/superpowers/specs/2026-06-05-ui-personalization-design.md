# UI personalization — design spec

**Status:** Approved in brainstorming (2026-06-05)  
**Project name:** Wallet Master  
**User-facing brand:** Ogarniam Portfel  
**Canonical requirements target:** `.docs/prd.md`  
**Next step:** Implementation plan (`.docs/superpowers/plans/`)

## Summary

The first UI personalization wave replaces the remaining Laravel Starter Kit visual identity with a focused Polish brand: **Ogarniam Portfel**. The direction is modern, analytical, and financial, with a navy + emerald palette, a wallet + chart logo, a premium dark auth experience, a branded public welcome page, a refined sidebar, and a lightweight branded dashboard preview.

This wave is visual and copy-focused. It does not add product features, backend endpoints, database fields, or dashboard metrics.

## Problem

The application already contains the core personal finance domains: accounts, transactions, imports, budgets, categories, and goals. The UI still carries starter-kit identity in key places, including the logo, public welcome page, neutral color tokens, and simple auth layout. This weakens the product's first impression and makes the app feel unfinished despite the functional scope.

## Decisions log

| Topic | Decision |
|-------|----------|
| User-facing name | **Ogarniam Portfel** |
| Technical/project name | **Wallet Master** remains valid for repo/project references |
| Brand promise | "Odzyskaj kontrolę nad domowym budżetem" |
| Brand tone | Modern, analytical, financial, clear |
| Logo concept | Wallet + chart symbol, usable as full logo and collapsed icon |
| Palette | Navy + emerald, with light and dark token sets |
| Auth style | Dark premium layout |
| App theme behavior | Keep existing appearance selection; design both light and dark tokens |
| Dashboard scope | Static branded preview, no new backend data |
| Navigation | Preserve PRD sidebar IA |
| Implementation scope | Frontend-only unless implementation discovery proves otherwise |

## Scope

### In scope

- Replace starter-kit logo and app label with Ogarniam Portfel branding.
- Add a wallet + chart logo mark for full and collapsed sidebar states.
- Update global Tailwind CSS variables for branded light and dark themes.
- Refine sidebar visual states while preserving current navigation items and route names.
- Replace the neutral auth shell with a dark navy premium experience.
- Update auth copy around the approved brand promise.
- Replace the Laravel starter welcome page with a concise product entry page.
- Add a lightweight branded dashboard preview or empty-state style.
- Keep current i18n conventions and Polish user-facing copy.

### Out of scope

- New backend KPI endpoints or dashboard aggregations.
- New onboarding wizard.
- New database tables, migrations, or persisted user preferences.
- Full marketing landing page with many sections.
- Native mobile visual design.
- Changing route names, URLs, auth validation behavior, or product IA.

## Brand System

### Naming

The user-facing product name is **Ogarniam Portfel**. It should appear in the sidebar logo, auth screens, welcome page, and page metadata where applicable. **Wallet Master** may remain in technical documentation, repository references, and internal implementation naming.

### Logo

The logo should communicate "personal finances under control" through a wallet + chart motif. It must work in these contexts:

- Full sidebar header: icon + "Ogarniam Portfel".
- Collapsed sidebar: icon alone.
- Auth/welcome: larger icon or lockup.
- Dark and light backgrounds.

The mark should be simple enough to implement as inline SVG in `AppLogoIcon.vue`, avoiding raster assets for the first wave.

### Color Tokens

Use navy as the primary financial base and emerald as the positive/action accent.

The implementation should update CSS variables in `resources/css/app.css`, not scatter hard-coded colors across components. Candidate token intent:

- `primary`: navy in light mode, light/emerald-tinted foreground treatment in dark mode where needed.
- `accent`: subtle emerald or navy-tinted surface.
- `ring`: accessible emerald focus ring.
- `sidebar-*`: branded sidebar background, active state, border, and icon treatments.
- `chart-*`: harmonized financial colors for future chart usage without adding charts in this wave.

Contrast must remain WCAG AA for text, buttons, active navigation, and focus states.

## UI Architecture

This wave personalizes the existing Inertia Vue + Tailwind + shadcn-vue foundation. It should not introduce a parallel design system.

Expected touchpoints:

- `resources/js/components/AppLogo.vue`
- `resources/js/components/AppLogoIcon.vue`
- `resources/js/components/AppSidebar.vue`
- `resources/js/components/NavMain.vue`
- `resources/js/layouts/AuthLayout.vue`
- `resources/js/layouts/auth/*`
- `resources/js/pages/auth/*`
- `resources/js/pages/Welcome.vue`
- `resources/js/pages/Dashboard.vue`
- `resources/css/app.css`
- `resources/js/locales/pl.json`
- `resources/js/locales/en.json` when existing i18n keys require parity

The implementation plan should confirm exact files after a fresh read of the current branch.

## Screen Designs

### Auth

Login, registration, forgot password, reset password, confirm password, and verify email should share the new auth shell when they already use the shared auth layout.

The auth shell should use a dark navy background with emerald CTA accents and a short brand panel. The panel may include:

- Product name and logo.
- Brand promise: "Odzyskaj kontrolę nad domowym budżetem".
- Two or three concise value statements, such as import history, track spending, and plan savings goals.
- Subtle finance visualization styling, such as abstract cards, lines, or progress indicators.

Forms should remain simple and keep existing validation behavior, `aria-*` attributes, focus handling, and Inertia form submission.

### Sidebar

The sidebar keeps the current PRD navigation:

- Dashboard
- Konta
- Transakcje
- Budżet
- Kategorie
- Cele

The changes are visual only:

- Branded logo header.
- Clear active states using the navy + emerald palette.
- Better contrast in light and dark modes.
- Collapsed state remains readable with the logo icon alone.

Settings remain available through the existing user menu/settings flow.

### Welcome

The public welcome page should no longer show Laravel starter content. It should become a compact product entry page:

- Ogarniam Portfel logo and brand promise.
- Short explanation of the product: accounts, transactions, imports, budgets, categories, and goals.
- Primary CTA to register and secondary CTA to log in.
- Authenticated users continue to have a path to dashboard.

This is not a full marketing landing page in the first wave.

### Dashboard

The dashboard becomes a branded start screen or preview. It should avoid implying real metrics unless those metrics already exist and are safely available.

Acceptable first-wave content:

- Branded heading and short intro.
- Static preview cards that guide the user to add accounts, import history, create transactions, or review goals.
- Empty-state style that feels intentional rather than unfinished.

Do not add backend dashboard summary data in this wave.

## Data Flow

No new backend data flow is required.

Existing Inertia props, auth state, Ziggy routes, and i18n infrastructure should be reused. Dashboard preview content should be static or derived from already available props only.

## Accessibility

- Preserve semantic form labels and validation error links.
- Maintain visible focus states using the branded `ring` token.
- Ensure nav active states are not color-only when possible; preserve `aria-current`.
- Keep contrast at WCAG AA for body text, muted text, buttons, and sidebar states.
- SVG logo should include accessible text through surrounding labels or screen-reader-only product name where appropriate.

## Testing And Verification

The implementation plan should include frontend verification appropriate to the current project scripts, likely:

- TypeScript/build verification for Vue changes.
- Lint/format verification for changed frontend files.
- Smoke coverage for welcome, login, register, and dashboard rendering.
- Browser log check after visiting affected screens when running the app locally.

If implementation changes PHP routes/controllers or backend props, also run the relevant Laravel verification from `.cursor/rules/wallet-dev-workflow.mdc`:

1. `vendor/bin/pint --dirty --format agent`
2. `./vendor/bin/sail artisan test --compact` with a scoped file or filter

## Rollout Notes

This work can be implemented as a single visual personalization PR because it is limited to shared brand components and top-level entry screens. Avoid mixing unrelated product features into the same PR.

The `.docs/checklist.md` should only be reconciled if the implementation completes a checklist item or adds a new tracked `[plan]` item.

