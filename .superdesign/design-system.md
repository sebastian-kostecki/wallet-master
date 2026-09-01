# Ogarniam Portfel — design system

Household budget app (Wallet Master). Quiet OpenShip-style operator UI: grey page, white lifted cards, ink primary, Instrument Sans. Color only for money and status.

## Product context

Authenticated app with inset floating sidebar. Canonical Polish routes: `/panel`, `/konta`, `/transakcje`, `/budzet/miesieczny`, `/kategorie`, `/kieszenie`, `/ustawienia/profil`.

**Jobs:** see accounts and balances; scan/filter a transaction table; add/edit a transaction or transfer; import a statement; plan monthly budget and envelopes.

## Page names & breadcrumbs (current ground truth)

Three inconsistent patterns exist today. Design work must treat this as a product problem, not invent a fourth visual language.

### Pattern A — Index / table pages (Transactions, Accounts, Categories, Pockets, Budget)

- Browser tab (`<Head title>`): same string as the single breadcrumb (`transactions.index.title` = „Transakcje”).
- Header chrome (`AppSidebarHeader`): **one breadcrumb item, styled as the current page** (`BreadcrumbPage`, `font-medium`). No parent crumb. Sidebar already labels the same destination.
- **No in-page `h1`.** `Heading.vue` is unused here.
- Locale already has unused copy: `transactions.index.description` („Przychody, wydatki i transfery na Twoich kontach.”) — never rendered.
- Primary actions live in `#headerActions` **on the same row as the breadcrumb**. On Transactions this is filters + a „Dodaj” dropdown (transaction / transfer / import), so the crumb is squeezed left.
- Table sits below summary cards. Row click/edit goes to `/transakcje/{id}/edit`.

### Pattern B — Nested create/edit (Transactions, Accounts, Categories, Pockets, Transfers)

- Two crumbs: parent list title (link back, preserves table query on transactions) → action title („Dodaj transakcję” / „Edytuj transakcję”).
- Last crumb is a **verb + noun**, not the entity (never „Lidl · 24.08.2026”).
- Still **no in-page `h1`**. Create/edit descriptions exist in i18n and are unused.
- Create/edit have no `#headerActions` (edit delete lives in a danger zone in the form).

### Pattern C — Dashboard & Settings (the exception)

- Dashboard: breadcrumb = `dashboard.headTitle` („Dashboard”) **and** a real `h1` (`dashboard.title` = „Dzień dobry w Ogarniam Portfel”) + muted description. Duplicate naming: tab/crumb vs greeting.
- Settings: breadcrumb + `Heading.vue` (`h2` `text-xl font-semibold tracking-tight` + optional description + Separator).

### Navigation labels

Sidebar uses the same index titles (`Transakcje`, `Konta`, …) except Dashboard (`nav.dashboard` = „Panel”) vs crumb/tab „Dashboard”. Budget nav is `budget.nav`, pages are „Budżet — miesięczny” / „Budżet — roczny”.

## Target UX for table → detail (design goal)

Keep Instrument Sans, grey floor, white cards, ink buttons. Improve **information hierarchy**:

1. **Index (table):** page name should be a real heading, not only a one-item breadcrumb. Optional subtitle from existing `*.index.description`. Actions stay in the header; filters should not replace the title.
2. **Create/Edit:** breadcrumb must connect back to the table (`Transakcje` → current). Last crumb can stay the action, or become the entity; the visible `h1` should match what the user is doing. Parent crumb must remain a working link to the filtered table.
3. Do not duplicate the same string three times (sidebar + breadcrumb + h1) without a role for each.

## Branding & type

- Font: Instrument Sans (`--font-sans`). Headings: `font-semibold tracking-tight`. Body: `text-sm text-muted-foreground`.
- Brand name in sidebar: „Ogarniam Portfel”. Logo: wallet glyph in an ink `rounded-xl` 32px well (see `AppLogo` / `AppLogoIcon`) — never replace with initials, emoji, or a generic mark.
- Primary = ink (`--primary: 0 0% 9%` light; inverted in dark). Accent mint is **not** a chrome fill.

## Color (HSL channels)

Light page `--background: 0 0% 98%`; cards `--card: 0 0% 100%`; text `--foreground: 0 0% 4%`; muted `--muted-foreground: 0 0% 45%`; border `--border: 0 0% 91%`. Income green / expense rose on amounts only.

## Radius & density

Cards/sidebar/dialogs `rounded-2xl`. Buttons, inputs, nav items `rounded-xl`. Chips `rounded-full`. Content `max-w-[1600px] px-4 sm:px-6 pt-6 pb-8`. Header row `mb-6`. Tables: no zebra; row hover `hover:bg-foreground/[0.03]`; dividers `border-border/50`.

## Motion

`transition-colors duration-150`. No bounce, no gradient heroes, no aurora.

## Components

shadcn-vue. Page chrome: `AppSidebar` (inset floating card) + `AppSidebarHeader` (mobile trigger + breadcrumbs + actions slot). Breadcrumb last item is current page (`aria-current="page"`), not a link. Separator: ChevronRight.

## Constraints

Polish UI copy in drafts. Do not introduce new fonts, mint headers, or marketing layouts. Use only tokens above.
