# Extractable components

Catalog only — full source lives in `components.md` and `layouts.md`. Superdesign reads this as a menu of `DraftComponent` candidates.

## Layout

## AppSidebar
- Source: `resources/js/components/AppSidebar.vue`
- Category: layout
- Description: Floating inset sidebar card with logo, theme toggle, collapse control, primary nav, add-transaction CTA, and user footer
- Extractable props: activeItem / current route (string; derived from Inertia `page.url` via `NavMain.isNavItemActive`; no explicit prop today — extract as `activeItem` or `currentPath`)
- Hardcoded: nav destinations (dashboard, accounts, transactions, budget, categories, pockets), Lucide icon names (`LayoutGrid`, `Wallet`, `ArrowLeftRight`, `PieChart`, `Tags`, `Target`, `Plus`, `PanelLeftClose`, `PanelLeftOpen`), i18n label keys, `variant="inset"`, `collapsible="icon"`, Add transaction button linking to `transactions.create`, logo via AppLogo, theme toggle, collapse button, all CSS (`rounded-xl` nav, `stroke-width="1.7"`)

## AppSidebarHeader
- Source: `resources/js/components/AppSidebarHeader.vue`
- Category: layout
- Description: Page chrome row: mobile sidebar trigger, breadcrumb trail, and trailing actions
- Extractable props: breadcrumbs (array of `{ title, href }`, default `[]`), actions slot (page-level header actions rendered to the right of crumbs)
- Hardcoded: `SidebarTrigger` (md:hidden), breadcrumb composition (`Breadcrumb`/`List`/`Item`/`Link`/`Page`/`Separator`), last item as `BreadcrumbPage`, separator between items, header CSS (`mb-6 flex items-center justify-between gap-3`)

## AppLayout
- Source: `resources/js/layouts/AppLayout.vue`
- Category: layout
- Description: Authenticated app wrapper: skip-to-content link, then AppSidebarLayout; forwards breadcrumbs and headerActions
- Extractable props: breadcrumbs (array, default `[]`), headerActions slot
- Hardcoded: skip-to-content link + a11y classes, always uses AppSidebarLayout (not header layout), i18n key `a11y.skip_to_content`

## Heading
- Source: `resources/js/components/Heading.vue`
- Category: layout
- Description: Page title block with optional description and a Separator — used by settings layout
- Extractable props: title (string), description (optional string)
- Hardcoded: heading typography (`text-xl font-semibold tracking-tight`), muted description (`text-sm text-muted-foreground`), `mb-8`, Separator `my-6`

## Basic

## Button
- Source: `resources/js/components/ui/button/Button.vue` (+ `button/index.ts` CVA)
- Category: basic
- Description: Polymorphic shadcn-vue button; ink primary, rounded-xl
- Extractable props: variant (`default` | `destructive` | `outline` | `secondary` | `ghost` | `link`, default `default`), size (`default` | `sm` | `lg` | `icon`, default `default`), as / asChild, label via slot
- Hardcoded: CVA class strings (`rounded-xl`, `h-10 px-4`, ink `bg-primary`), focus ring, SVG size rules, all CSS

## Breadcrumb
- Source: `resources/js/components/ui/breadcrumb/*` composed by `resources/js/components/AppSidebarHeader.vue`
- Category: basic
- Description: Breadcrumb pattern — ui primitives plus AppSidebarHeader composition (no standalone Breadcrumbs.vue wrapper)
- Extractable props: breadcrumbs array (`{ title, href }[]`); current page is last item (rendered as BreadcrumbPage, not a link)
- Hardcoded: separator chevron, `font-medium` on current page, list/item CSS from ui primitives, mobile SidebarTrigger adjacency in the header

## Card
- Source: `resources/js/components/ui/card/Card.vue` (plus Header/Title/Description/Content/Footer)
- Category: basic
- Description: Lifted surface used for dashboard cards and settings panels — white on grey page
- Extractable props: class (optional override); title/description/footer via slots (CardTitle/CardDescription/CardFooter), not required props
- Hardcoded: `rounded-2xl border border-transparent bg-card text-card-foreground` (the OpenShip card recipe)
