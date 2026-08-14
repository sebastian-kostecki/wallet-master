---
name: openship-inspired-ui
description: Applies the OpenShip-inspired dashboard look to Wallet Master (Inertia Vue, Tailwind v3, shadcn-vue). Use when creating or editing Vue pages, layouts, components, Tailwind classes, CSS tokens, sidebar, cards, forms, empty states, or any app UI.
---

# OpenShip-inspired UI (Wallet Master)

North star: [oblien/openship](https://github.com/oblien/openship) **dashboard** (not the marketing site). Map that language onto this stack — do not copy their Tailwind v4 `data-theme`, `dim` theme, `.th-*` classes, or Gellix font.

For token values and copy-paste recipes, read [reference.md](reference.md).

## What this look is

Quiet operator UI. The page is a warm grey floor. Cards sit on it as white (or slightly lifted dark) surfaces **without** resting shadows. Chrome (sidebar, icon buttons, labels) stays muted. Color is reserved for status and money.

If a new screen could pass as Linear / Vercel / OpenShip dashboard, it is on-brand. If it looks like a SaaS marketing landing (gradients, aurora, huge radius blobs), it is not.

## Surface ladder

| Layer | Role | How it reads |
|-------|------|----------------|
| Page | Floor | Grey, not `#fff`. Cards need this contrast to exist without a border. |
| Card / sidebar | Lifted surface | White on grey (light) or ~5% white on black (dark). No drop shadow. |
| Dropdown / popover / dialog | Highest | Opaque surface + real shadow + hairline border. |

Never put a white card on a white page. Never put a black card on a pure-black page without a lift.

## Layout

- App shell: `AppSidebarLayout` — content `max-w-[1600px]`, horizontal padding `px-4 sm:px-6`.
- Sidebar: shadcn `variant="inset"` floating card (`rounded-2xl`, `border-0` on the outer Sidebar). Keep collapse + theme toggle in the header row.
- Page header: `h1` `text-xl font-semibold tracking-tight` + optional `text-sm text-muted-foreground` subtitle. **No** gradient heroes, **no** uppercase tracking-[0.2em] brand kicker on new screens.
- Index pages: header row (title + primary action) then filters then list. Primary action is a solid `Button`, not a rainbow CTA.

## Color

Map to existing CSS variables in `resources/css/app.css` (see reference). Do not introduce a third `dim` theme unless asked.

- **Primary button = ink.** Light: near-black. Dark: white fill, black text. Do not use the mint/teal accent as `--primary` or on chrome.
- **Accent/mint** is not a brand fill. Do not add `bg-accent` icon wells, emerald kickers, or `from-primary via-slate-800 to-emerald-900` banners on new UI.
- **Status / money only:** income → success green, expense → danger red, warning amber, info blue. Recipe: `bg` at ~10% opacity, border ~20%, text the solid hue. Never neon on dark.

## Typography

- Font: **Instrument Sans** (already `--font-sans`). Do not load Gellix or another display face.
- Headings: `font-semibold tracking-tight`, color `text-foreground` (near-opaque).
- Body: `text-sm` / `leading-relaxed`, `text-muted-foreground` (~50–66% opacity).
- Labels / section labels: `text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60`.
- Tabular numbers for money: `tabular-nums`.
- Lucide icons in nav/chrome: `size-4` or `size-[18px]`, `stroke-width="1.7"`.

## Radius and density

| Element | Radius |
|---------|--------|
| Cards, sidebar, dialogs | `rounded-2xl` (16px) |
| Buttons, inputs, nav items, icon wells | `rounded-xl` (12px) |
| Small chips / icon buttons | `rounded-lg`–`rounded-xl` |
| Badges, avatars, status pills | `rounded-full` |

When unsure, round **more**. Do not add new `rounded-md` / `rounded-lg` cards.

Padding: page blocks `p-5`–`p-6`; compact rows `px-3 py-2.5`; inputs `h-10` or `h-11`.

## Components (do this)

Reuse `components/ui/*`. Domain-specific UI goes in `components/{domain}/`, never in `ui/`.

- **Button:** `rounded-xl`, no `shadow`. Default = primary ink. Secondary actions = `variant="outline"` / ghost with `border-border` and muted text. Destructive stays red.
- **Input:** `h-11 rounded-xl`, quiet border, focus = slightly stronger border + `ring-2 ring-ring/40` — not a thick colored glow.
- **Card:** `rounded-2xl border-transparent bg-card` (override the default `rounded-lg border shadow-sm` at the call site or when touching the primitive). Hover: `border-border` if the card is clickable.
- **Tabs:** underline strip, `border-b border-border/50`, active = `text-foreground` + `h-0.5 bg-primary` underline inset to the label, not a pill background.
- **Empty state:** centered, short title `text-2xl font-medium`, muted description `max-w-sm`, one primary + optional ghost action. No illustration unless it uses theme tokens only.
- **Tables / lists:** no zebra. Row hover `hover:bg-foreground/[0.03]` (or existing `.list-row-hover`). Dividers `border-border/50`.

## Motion

- `duration-150` / `duration-200`, `transition-colors` or `transition-all`.
- Hover lift for a primary empty-state CTA may be `-translate-y-0.5` + soft shadow. Everywhere else: color only.
- No bounce, no layout spring, no marketing blob animation on app routes.

## Do not

- Copy OpenShip `.th-*` class names or `--th-*` tokens into this repo.
- Use Gellix / SF Arabic / Oblien CDN fonts.
- Add `data-theme="dim"` or Tailwind v4 `@theme` / `@custom-variant`.
- Draw resting drop shadows on cards, sidebar, or inputs.
- Paint chrome with mint, emerald, violet gradients, or aurora.
- Put domain widgets inside `components/ui/`.
- Restyle unrelated lines while touching one component.

Existing screens (e.g. Dashboard gradient hero) may still use older language — **do not drive-by rewrite them**. New work and screens you are asked to restyle follow this skill.
