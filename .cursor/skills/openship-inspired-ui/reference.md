# OpenShip → Wallet Master mapping

Source of truth in OpenShip: `apps/dashboard/src/styles/theme.css`.
Source of truth here: `resources/css/app.css` + Tailwind utilities on Vue components.

Do not paste `--th-*` names into this repo. Use the shadcn variables and the classes below.

## Target tokens (`resources/css/app.css`)

When you **are** asked to change CSS variables, move toward these. When you are only building a screen, use the equivalent Tailwind classes.

### Light

| Role | OpenShip | Wallet target | Tailwind |
|------|----------|---------------|----------|
| Page | `#f9f9f9` | `--background: 0 0% 98%` (neutral grey, not blue-tinted) | `bg-background` |
| Card | `#ffffff` | `--card: 0 0% 100%` | `bg-card` |
| Heading | `rgba(0,0,0,1)` | `--foreground: 0 0% 4%` | `text-foreground` |
| Body / muted | `rgba(0,0,0,.52–.66)` | `--muted-foreground: 0 0% 45%` | `text-muted-foreground` |
| Ghost text | `rgba(0,0,0,.22)` | — | `text-foreground/25` |
| Hairline | `#e8e8e8` / `#f0f0f0` | `--border: 0 0% 91%` | `border-border` |
| Primary btn | `rgba(0,0,0,.92)` | `--primary: 0 0% 9%` | `bg-primary text-primary-foreground` |
| Input bg | `#ffffff` | white | `bg-background` |
| Overlay | `rgba(0,0,0,.45)` | — | `bg-black/45` |
| Radius | `0.75rem` base | `--radius: 0.75rem` | cards still `rounded-2xl` |

### Dark

| Role | OpenShip | Wallet target | Tailwind |
|------|----------|---------------|----------|
| Page | `#000000` | `--background: 0 0% 0%` | `bg-background` |
| Card | `rgba(255,255,255,.05)` → ~`#0d0d0d` | `--card: 0 0% 5%` | `bg-card` |
| Heading | `#ffffff` | `--foreground: 0 0% 98%` | `text-foreground` |
| Muted | `rgba(255,255,255,.50)` | `--muted-foreground: 0 0% 64%` | `text-muted-foreground` |
| Primary btn | `#ffffff` / text `#000` | `--primary: 0 0% 100%` / `--primary-foreground: 0 0% 0%` | `bg-primary` |
| Hairline | `rgba(255,255,255,.08)` | `--border: 0 0% 14%` | `border-border` |
| Dropdown | `#161616` + deep shadow | `--popover` opaque | `bg-popover shadow-xl` |

Do **not** keep mint (`158 64% 52%`) as dark `--primary`. Mint/emerald is for income/success only.

### Status (money + badges)

Shared recipe: `bg-{hue}-500/10 border border-{hue}-500/20`.

| Meaning | Light text | Dark text | Solid (dots/bars) |
|---------|------------|-----------|-------------------|
| Success / income | `text-emerald-600` | `text-emerald-400` | `bg-emerald-500` |
| Danger / expense | `text-red-600` | `text-red-400` | `bg-red-500` |
| Warning | `text-amber-600` | `text-amber-400` | `bg-amber-500` |
| Info | `text-blue-600` | `text-blue-400` | `bg-blue-500` |
| Neutral | `text-zinc-600` | `text-white/50` | `bg-zinc-400` |

## Chrome interactions

```
hover icon/button:  hover:bg-foreground/[0.06] hover:text-foreground
active nav item:    bg-foreground/[0.07] text-foreground
row hover:          hover:bg-foreground/[0.03]   (or .list-row-hover)
divider:            bg-border/60  or  border-border/50
focus ring:         focus-visible:ring-2 focus-visible:ring-ring/40
```

Muted chrome icons: `text-muted-foreground` at rest.

## Primitive overrides

Default shadcn pieces still say `rounded-md` / `shadow-sm`. Prefer fixing the primitive when you touch it; otherwise override at the call site.

### Button (`components/ui/button/index.ts`)

Target classes (keep CVA variants):

```
base: rounded-xl text-sm font-medium transition-all duration-150
      focus-visible:ring-2 focus-visible:ring-ring/40
default: bg-primary text-primary-foreground hover:bg-primary/80   (no shadow)
outline: border border-input bg-transparent text-foreground/60 hover:bg-foreground/[0.04]
ghost:   text-foreground/60 hover:bg-foreground/[0.06] hover:text-foreground
sm: h-8 rounded-lg px-3 text-xs
default size: h-10 px-4
lg: h-12 rounded-xl px-8
icon: h-9 w-9 rounded-xl
```

### Card (`components/ui/card/Card.vue`)

```
rounded-2xl border border-transparent bg-card text-card-foreground
```

Clickable cards: `transition-colors hover:border-border`. No `shadow-sm`.

### Input (`components/ui/input/Input.vue`)

```
h-11 rounded-xl border border-input bg-background px-3.5 text-sm
placeholder:text-muted-foreground/70
focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40
```

## Recipes

### Page header

```vue
<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-foreground">
            {{ t('accounts.index.title') }}
        </h1>
        <p class="mt-1.5 text-sm text-muted-foreground">
            {{ t('accounts.index.description') }}
        </p>
    </div>
    <Button as-child>
        <Link :href="route('accounts.create')">{{ t('accounts.index.create') }}</Link>
    </Button>
</div>
```

### Sidebar nav item (already in `NavMain.vue`)

Keep this language:

```
h-auto rounded-xl px-3 py-2.5 text-[15px] font-medium
text-muted-foreground
hover:bg-foreground/[0.04] hover:text-foreground
data-[active=true]:bg-foreground/[0.07] data-[active=true]:text-foreground
[&>svg]:!size-[18px]  +  stroke-width="1.7"
```

Section labels: `text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60`.

### Quiet card (summary / settings)

```vue
<section class="rounded-2xl border border-transparent bg-card p-6">
    <h2 class="text-sm font-semibold tracking-tight text-foreground">{{ title }}</h2>
    <p class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
    <div class="mt-4"><slot /></div>
</section>
```

### Status badge

```vue
<span class="inline-flex items-center rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
    {{ label }}
</span>
```

### Empty state

```vue
<div class="py-16 text-center">
    <h3 class="text-2xl font-medium tracking-tight text-foreground/80">{{ title }}</h3>
    <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground/70">
        {{ description }}
    </p>
    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
        <Button>{{ primary }}</Button>
        <Button variant="ghost">{{ secondary }}</Button>
    </div>
</div>
```

### Dialog / dropdown (highest surface)

```
rounded-2xl border border-border/50 bg-popover shadow-xl shadow-black/10
```

Overlay: `bg-black/45`. Close / icon buttons: `rounded-xl text-muted-foreground hover:bg-foreground/[0.06]`.

### Tabs (underline)

```
flex gap-1 border-b border-border/50
tab: px-4 py-2.5 text-sm font-medium text-muted-foreground hover:text-foreground/70
active: text-foreground
indicator: absolute bottom-0 inset-x-4 h-0.5 rounded-full bg-primary
```

Do not use filled pill tabs for page-level navigation.

## Anti-patterns

```vue
<!-- ❌ white page + bordered+shadowed card -->
<div class="bg-white">
    <div class="rounded-lg border shadow-sm">

<!-- ❌ mint chrome -->
<button class="bg-emerald-600 text-white">Save</button>
<div class="rounded-xl bg-accent text-accent-foreground"><Wallet /></div>

<!-- ❌ marketing hero on an app page -->
<section class="rounded-3xl bg-gradient-to-br from-primary via-slate-800 to-emerald-900">

<!-- ❌ new display font -->
<body class="font-[Gellix]">

<!-- ❌ OpenShip tokens copied verbatim -->
<div class="th-card" style="color: var(--th-text-body)">
```

```vue
<!-- ✅ grey page, borderless card, ink button, lucide at 1.7 -->
<div class="bg-background">
    <div class="rounded-2xl border border-transparent bg-card p-6">
        <Button>Save</Button>
    </div>
</div>
```
