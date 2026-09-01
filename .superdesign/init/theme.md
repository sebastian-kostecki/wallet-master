# Theme tokens

Wallet UI (from `.cursor/rules/wallet-ui-design.mdc`): **Instrument Sans**; **page is grey, cards are white**; cards `rounded-2xl`; buttons `rounded-xl`; **primary is ink not mint**; sidebar is a **floating inset card**.

## Part 1 — Compact token summary

### Fonts

- Family: `Instrument Sans`, then `ui-sans-serif, system-ui, sans-serif` (`--font-sans` in `app.css`; `theme.extend.fontFamily.sans` in Tailwind).
- Headings: `font-semibold tracking-tight`.
- Body: `text-sm text-muted-foreground`.
- No Gellix / display font.

### Radius

- `--radius: 0.75rem` (12px) → Tailwind `rounded-lg`.
- `rounded-md` = `calc(var(--radius) - 2px)`; `rounded-sm` = `calc(var(--radius) - 4px)`.
- Product usage (not the CSS var): buttons/inputs **`rounded-xl` (0.75rem)**; cards **`rounded-2xl` (1rem)**; pills `rounded-full`. Do not add new `rounded-md`.

### Shadows

- No custom `boxShadow` in `tailwind.config.js`.
- Lift comes from grey page vs white card contrast, not `shadow-sm`. Resting cards: `border-transparent` (or `border-border/40` max). Hover may add a hairline, not a drop shadow.

### Spacing

- Tailwind default scale (4px base). App content: `px-4 pb-8 pt-6 sm:px-6`, max width `max-w-[1600px]`.
- Motion: `transition-colors` / `duration-150`.

### Breakpoints (Tailwind v3 defaults)

| Token | Min width |
|---|---|
| sm | 640px |
| md | 768px |
| lg | 1024px |
| xl | 1280px |
| 2xl | 1536px |

### Colors — `:root` (light)

HSL channels without `hsl()` wrapper as stored in CSS; resolved color in parentheses.

| Token | Value | Notes |
|---|---|---|
| `--background` | `0 0% 98%` (near-white grey) | **Page grey** |
| `--foreground` | `0 0% 4%` | Ink text |
| `--card` | `0 0% 100%` | **White cards** |
| `--card-foreground` | `0 0% 4%` | |
| `--popover` | `0 0% 100%` | |
| `--popover-foreground` | `0 0% 4%` | |
| `--primary` | `0 0% 9%` | **Ink fill, not mint** |
| `--primary-foreground` | `0 0% 98%` | |
| `--secondary` | `0 0% 96%` | |
| `--secondary-foreground` | `0 0% 9%` | |
| `--muted` | `0 0% 96%` | |
| `--muted-foreground` | `0 0% 45%` | |
| `--accent` | `158 64% 92%` | Mint wash — status/money only, not chrome |
| `--accent-foreground` | `164 86% 16%` | |
| `--destructive` | `0 84.2% 60.2%` | |
| `--destructive-foreground` | `0 0% 98%` | |
| `--border` | `0 0% 91%` | |
| `--input` | `0 0% 91%` | |
| `--ring` | `0 0% 9%` | Matches ink primary |
| `--chart-1` | `160 84% 39%` | |
| `--chart-2` | `0 0% 24%` | |
| `--chart-3` | `199 89% 48%` | |
| `--chart-4` | `43 96% 56%` | |
| `--chart-5` | `340 82% 52%` | |
| `--sidebar-background` | `0 0% 100%` | White floating card |
| `--sidebar-foreground` | `0 0% 9%` | |
| `--sidebar-primary` | `0 0% 9%` | |
| `--sidebar-primary-foreground` | `0 0% 98%` | |
| `--sidebar-accent` | `0 0% 96%` | Overridden in NavMain to `bg-foreground/[0.07]` |
| `--sidebar-accent-foreground` | `0 0% 4%` | |
| `--sidebar-border` | `0 0% 91%` | |
| `--sidebar-ring` | `0 0% 9%` | |

### Colors — `.dark`

| Token | Value | Notes |
|---|---|---|
| `--background` | `0 0% 0%` | True black page |
| `--foreground` | `0 0% 98%` | |
| `--card` | `0 0% 5%` | Lifted surface |
| `--card-foreground` | `0 0% 98%` | |
| `--popover` | `0 0% 9%` | |
| `--popover-foreground` | `0 0% 98%` | |
| `--primary` | `0 0% 100%` | **White fill / black text** |
| `--primary-foreground` | `0 0% 0%` | |
| `--secondary` | `0 0% 12%` | |
| `--secondary-foreground` | `0 0% 96%` | |
| `--muted` | `0 0% 10%` | |
| `--muted-foreground` | `0 0% 64%` | |
| `--accent` | `166 76% 18%` | Dark mint — money/status only |
| `--accent-foreground` | `158 64% 88%` | |
| `--destructive` | `0 84% 60%` | |
| `--destructive-foreground` | `0 0% 98%` | |
| `--border` | `0 0% 14%` | |
| `--input` | `0 0% 14%` | |
| `--ring` | `0 0% 100%` | |
| `--chart-1` | `158 64% 52%` | |
| `--chart-2` | `199 89% 60%` | |
| `--chart-3` | `43 96% 64%` | |
| `--chart-4` | `280 65% 64%` | |
| `--chart-5` | `340 82% 62%` | |
| `--sidebar-background` | `0 0% 5%` | |
| `--sidebar-foreground` | `0 0% 98%` | |
| `--sidebar-primary` | `0 0% 100%` | |
| `--sidebar-primary-foreground` | `0 0% 0%` | |
| `--sidebar-accent` | `0 0% 12%` | |
| `--sidebar-accent-foreground` | `0 0% 98%` | |
| `--sidebar-border` | `0 0% 14%` | |
| `--sidebar-ring` | `0 0% 100%` | |

### Sidebar product rules

- `variant="inset"`, `rounded-2xl`, not a full-bleed rail.
- Nav items `rounded-xl`, icons `stroke-width="1.7"`, hover `bg-foreground/[0.06]` (NavMain uses `/[0.04]` hover, `/[0.07]` active).

## Part 2 — Raw dumps

### `tailwind.config.js`

```js
import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts,jsx,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Instrument Sans', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
            colors: {
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                chart: {
                    1: 'hsl(var(--chart-1))',
                    2: 'hsl(var(--chart-2))',
                    3: 'hsl(var(--chart-3))',
                    4: 'hsl(var(--chart-4))',
                    5: 'hsl(var(--chart-5))',
                },
                sidebar: {
                    DEFAULT: 'hsl(var(--sidebar-background))',
                    foreground: 'hsl(var(--sidebar-foreground))',
                    primary: 'hsl(var(--sidebar-primary))',
                    'primary-foreground': 'hsl(var(--sidebar-primary-foreground))',
                    accent: 'hsl(var(--sidebar-accent))',
                    'accent-foreground': 'hsl(var(--sidebar-accent-foreground))',
                    border: 'hsl(var(--sidebar-border))',
                    ring: 'hsl(var(--sidebar-ring))',
                },
            },
        },
    },
    plugins: [require('tailwindcss-animate')],
};
```

### `resources/css/app.css`

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

:focus-visible {
    outline: 2px solid hsl(var(--ring));
    outline-offset: 2px;
}

body,
html {
    --font-sans:
        'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
}

@layer base {
    :root {
        --background: 0 0% 98%;
        --foreground: 0 0% 4%;
        --card: 0 0% 100%;
        --card-foreground: 0 0% 4%;
        --popover: 0 0% 100%;
        --popover-foreground: 0 0% 4%;
        --primary: 0 0% 9%;
        --primary-foreground: 0 0% 98%;
        --secondary: 0 0% 96%;
        --secondary-foreground: 0 0% 9%;
        --muted: 0 0% 96%;
        --muted-foreground: 0 0% 45%;
        --accent: 158 64% 92%;
        --accent-foreground: 164 86% 16%;
        --destructive: 0 84.2% 60.2%;
        --destructive-foreground: 0 0% 98%;
        --border: 0 0% 91%;
        --input: 0 0% 91%;
        --ring: 0 0% 9%;
        --chart-1: 160 84% 39%;
        --chart-2: 0 0% 24%;
        --chart-3: 199 89% 48%;
        --chart-4: 43 96% 56%;
        --chart-5: 340 82% 52%;
        --radius: 0.75rem;
        --sidebar-background: 0 0% 100%;
        --sidebar-foreground: 0 0% 9%;
        --sidebar-primary: 0 0% 9%;
        --sidebar-primary-foreground: 0 0% 98%;
        --sidebar-accent: 0 0% 96%;
        --sidebar-accent-foreground: 0 0% 4%;
        --sidebar-border: 0 0% 91%;
        --sidebar-ring: 0 0% 9%;
    }

    .dark {
        --background: 0 0% 0%;
        --foreground: 0 0% 98%;
        --card: 0 0% 5%;
        --card-foreground: 0 0% 98%;
        --popover: 0 0% 9%;
        --popover-foreground: 0 0% 98%;
        --primary: 0 0% 100%;
        --primary-foreground: 0 0% 0%;
        --secondary: 0 0% 12%;
        --secondary-foreground: 0 0% 96%;
        --muted: 0 0% 10%;
        --muted-foreground: 0 0% 64%;
        --accent: 166 76% 18%;
        --accent-foreground: 158 64% 88%;
        --destructive: 0 84% 60%;
        --destructive-foreground: 0 0% 98%;
        --border: 0 0% 14%;
        --input: 0 0% 14%;
        --ring: 0 0% 100%;
        --chart-1: 158 64% 52%;
        --chart-2: 199 89% 60%;
        --chart-3: 43 96% 64%;
        --chart-4: 280 65% 64%;
        --chart-5: 340 82% 62%;
        --sidebar-background: 0 0% 5%;
        --sidebar-foreground: 0 0% 98%;
        --sidebar-primary: 0 0% 100%;
        --sidebar-primary-foreground: 0 0% 0%;
        --sidebar-accent: 0 0% 12%;
        --sidebar-accent-foreground: 0 0% 98%;
        --sidebar-border: 0 0% 14%;
        --sidebar-ring: 0 0% 100%;
    }
}

@layer base {
    * {
        @apply border-border;
    }

    body {
        @apply bg-background text-foreground;
    }
}

@layer components {
    .list-row-hover {
        @apply transition-colors hover:bg-foreground/[0.03];
    }
}
```

