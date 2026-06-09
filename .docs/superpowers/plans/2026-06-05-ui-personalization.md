# UI Personalization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the remaining Laravel Starter Kit identity with the Ogarniam Portfel brand across logo, theme tokens, sidebar, auth, welcome, and dashboard entry screens.

**Architecture:** Keep the work frontend-only and reuse the existing Inertia Vue + Tailwind + shadcn-vue token system. Centralize brand primitives in `AppLogoIcon.vue`, `AppLogo.vue`, `resources/css/app.css`, and locale keys, then apply them to shared shells and entry pages. Do not add backend data, routes, migrations, or a parallel design system.

**Tech Stack:** Laravel 13, Inertia v2, Vue 3, TypeScript, Tailwind CSS 3, shadcn-vue style CSS variables, vue-i18n, Vite.

**Spec:** `.docs/superpowers/specs/2026-06-05-ui-personalization-design.md`  
**PRD:** `.docs/prd.md` §7 UX, IA i copy  
**Suggested branch:** `feature/ui-app`

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Modify | `resources/js/components/AppLogoIcon.vue` | Inline SVG wallet + chart mark |
| Modify | `resources/js/components/AppLogo.vue` | Full sidebar logo lockup and product name |
| Modify | `resources/css/app.css` | Navy + emerald light/dark CSS tokens |
| Modify | `resources/js/components/AppSidebar.vue` | Branded sidebar container/header treatment |
| Modify | `resources/js/components/NavMain.vue` | Active state and label treatment |
| Modify | `resources/js/layouts/auth/AuthSimpleLayout.vue` | Shared premium dark auth shell |
| Modify | `resources/js/pages/auth/ConfirmPassword.vue` | Move hard-coded auth copy to i18n keys |
| Modify | `resources/js/pages/auth/VerifyEmail.vue` | Move hard-coded auth copy to i18n keys |
| Modify | `resources/js/pages/Welcome.vue` | Replace Laravel starter content with product entry page |
| Modify | `resources/js/pages/Dashboard.vue` | Replace starter panels with static branded preview |
| Modify | `resources/js/locales/pl.json` | Polish brand, auth, welcome, dashboard copy |
| Modify | `resources/js/locales/en.json` | English parity keys for fallback/localized UI |

---

## Task 1: Brand Primitives

**Files:**
- Modify: `resources/js/components/AppLogoIcon.vue`
- Modify: `resources/js/components/AppLogo.vue`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Replace the starter logo icon with a wallet + chart SVG**

Update `resources/js/components/AppLogoIcon.vue` to:

```vue
<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        fill="none"
        :class="className"
        role="img"
        aria-hidden="true"
        v-bind="$attrs"
    >
        <path
            d="M10 15.5C10 12.4624 12.4624 10 15.5 10H34C36.2091 10 38 11.7909 38 14V36C38 38.2091 36.2091 40 34 40H14C11.7909 40 10 38.2091 10 36V15.5Z"
            class="fill-current opacity-95"
        />
        <path
            d="M15.5 10H34C36.2091 10 38 11.7909 38 14V17H15.5C12.4624 17 10 14.5376 10 11.5C10 10.6716 10.6716 10 11.5 10H15.5Z"
            class="fill-current opacity-60"
        />
        <path
            d="M31 24.5H39C40.1046 24.5 41 25.3954 41 26.5V31.5C41 32.6046 40.1046 33.5 39 33.5H31C28.5147 33.5 26.5 31.4853 26.5 29C26.5 26.5147 28.5147 24.5 31 24.5Z"
            class="fill-current opacity-80"
        />
        <path d="M31 29H31.04" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="text-white/90 dark:text-slate-950/90" />
        <path
            d="M17 32L21.25 27.75L24.25 30.75L31.5 23.5"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="text-emerald-300 dark:text-emerald-400"
        />
        <path d="M28.5 23.5H31.5V26.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-300 dark:text-emerald-400" />
    </svg>
</template>
```

- [ ] **Step 2: Replace the sidebar lockup text**

Update `resources/js/components/AppLogo.vue` to:

```vue
<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useI18n } from 'vue-i18n';

interface Props {
    class?: string;
}

defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground shadow-sm">
        <AppLogoIcon class="size-5 fill-current text-white dark:text-slate-950" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">{{ t('brand.name') }}</span>
        <span class="truncate text-xs leading-none text-muted-foreground group-data-[collapsible=icon]:hidden">{{ t('brand.shortPromise') }}</span>
    </div>
</template>
```

- [ ] **Step 3: Add brand locale keys**

In `resources/js/locales/pl.json`, add this top-level object near the existing top-level keys:

```json
"brand": {
    "name": "Ogarniam Portfel",
    "promise": "Odzyskaj kontrolę nad domowym budżetem",
    "shortPromise": "Budżet pod kontrolą",
    "description": "Konta, transakcje, importy, budżet i cele w jednym uporządkowanym miejscu."
}
```

In `resources/js/locales/en.json`, add the matching object:

```json
"brand": {
    "name": "Ogarniam Portfel",
    "promise": "Take back control of your household budget",
    "shortPromise": "Budget under control",
    "description": "Accounts, transactions, imports, budgets, and goals in one organized place."
}
```

- [ ] **Step 4: Run formatting and build verification**

Run:

```bash
npm run format:check
npm run build
```

Expected: both commands exit with code 0. If `format:check` reports formatting differences, run `npm run format`, inspect the diff, then rerun `npm run format:check`.

- [ ] **Step 5: Commit brand primitives**

Run:

```bash
git add resources/js/components/AppLogoIcon.vue resources/js/components/AppLogo.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "feat(ui): add Ogarniam Portfel brand primitives"
```

---

## Task 2: Theme Tokens And Sidebar

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/components/NavMain.vue`

- [ ] **Step 1: Update light and dark CSS variables**

In `resources/css/app.css`, replace only the variable values inside `:root` and `.dark`; keep the surrounding `@tailwind` and `@layer` structure.

Use these values:

```css
:root {
    --background: 210 40% 98%;
    --foreground: 222 47% 11%;
    --card: 0 0% 100%;
    --card-foreground: 222 47% 11%;
    --popover: 0 0% 100%;
    --popover-foreground: 222 47% 11%;
    --primary: 222 47% 11%;
    --primary-foreground: 0 0% 98%;
    --secondary: 210 40% 94%;
    --secondary-foreground: 222 47% 18%;
    --muted: 210 40% 94%;
    --muted-foreground: 215 16% 47%;
    --accent: 158 64% 92%;
    --accent-foreground: 164 86% 16%;
    --destructive: 0 84.2% 60.2%;
    --destructive-foreground: 0 0% 98%;
    --border: 214 32% 91%;
    --input: 214 32% 91%;
    --ring: 160 84% 39%;
    --chart-1: 160 84% 39%;
    --chart-2: 222 47% 24%;
    --chart-3: 199 89% 48%;
    --chart-4: 43 96% 56%;
    --chart-5: 340 82% 52%;
    --radius: 0.5rem;
    --sidebar-background: 0 0% 100%;
    --sidebar-foreground: 222 47% 18%;
    --sidebar-primary: 222 47% 11%;
    --sidebar-primary-foreground: 158 64% 92%;
    --sidebar-accent: 158 64% 92%;
    --sidebar-accent-foreground: 164 86% 16%;
    --sidebar-border: 214 32% 88%;
    --sidebar-ring: 160 84% 39%;
}

.dark {
    --background: 222 47% 6%;
    --foreground: 210 40% 98%;
    --card: 222 47% 8%;
    --card-foreground: 210 40% 98%;
    --popover: 222 47% 8%;
    --popover-foreground: 210 40% 98%;
    --primary: 158 64% 52%;
    --primary-foreground: 222 47% 7%;
    --secondary: 217 33% 17%;
    --secondary-foreground: 210 40% 96%;
    --muted: 217 33% 14%;
    --muted-foreground: 215 20% 72%;
    --accent: 166 76% 18%;
    --accent-foreground: 158 64% 88%;
    --destructive: 0 84% 60%;
    --destructive-foreground: 0 0% 98%;
    --border: 217 33% 18%;
    --input: 217 33% 18%;
    --ring: 158 64% 52%;
    --chart-1: 158 64% 52%;
    --chart-2: 199 89% 60%;
    --chart-3: 43 96% 64%;
    --chart-4: 280 65% 64%;
    --chart-5: 340 82% 62%;
    --sidebar-background: 222 47% 8%;
    --sidebar-foreground: 210 40% 96%;
    --sidebar-primary: 158 64% 52%;
    --sidebar-primary-foreground: 222 47% 7%;
    --sidebar-accent: 166 76% 18%;
    --sidebar-accent-foreground: 158 64% 88%;
    --sidebar-border: 217 33% 18%;
    --sidebar-ring: 158 64% 52%;
}
```

- [ ] **Step 2: Refine the sidebar container**

In `resources/js/components/AppSidebar.vue`, update the sidebar opening tag and header area to:

```vue
<Sidebar collapsible="icon" variant="inset" class="border-sidebar-border/80">
    <SidebarHeader class="border-b border-sidebar-border/70">
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" as-child class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                    <Link :href="route('dashboard')">
                        <AppLogo />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>
```

Keep the existing `SidebarContent`, `NavMain`, `SidebarFooter`, and `<slot />` structure unchanged.

- [ ] **Step 3: Refine nav active states**

In `resources/js/components/NavMain.vue`, change the menu button block to:

```vue
<SidebarMenuButton
    as-child
    :is-active="item.href === page.href"
    class="transition-colors data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-sidebar-accent-foreground"
>
    <Link :href="item.href" :aria-current="item.href === page.href ? 'page' : undefined">
        <component :is="item.icon" />
        <span>{{ item.title }}</span>
    </Link>
</SidebarMenuButton>
```

- [ ] **Step 4: Run formatting and build verification**

Run:

```bash
npm run format:check
npm run build
```

Expected: both commands exit with code 0. If `format:check` reports formatting differences, run `npm run format`, inspect the diff, then rerun `npm run format:check`.

- [ ] **Step 5: Commit theme and sidebar**

Run:

```bash
git add resources/css/app.css resources/js/components/AppSidebar.vue resources/js/components/NavMain.vue
git commit -m "feat(ui): apply navy emerald theme tokens"
```

---

## Task 3: Premium Auth Shell And Auth Copy

**Files:**
- Modify: `resources/js/layouts/auth/AuthSimpleLayout.vue`
- Modify: `resources/js/pages/auth/ConfirmPassword.vue`
- Modify: `resources/js/pages/auth/VerifyEmail.vue`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add auth brand locale keys**

In `resources/js/locales/pl.json`, extend the existing `auth` object with:

```json
"brand": {
    "eyebrow": "Ogarniam Portfel",
    "promise": "Odzyskaj kontrolę nad domowym budżetem",
    "description": "Importuj historię, porządkuj transakcje i śledź cele bez arkuszy kalkulacyjnych.",
    "benefits": {
        "imports": "Import historii z banku",
        "budget": "Budżet i kategorie w jednym miejscu",
        "goals": "Cele oszczędnościowe pod ręką"
    }
},
"confirmPassword": {
    "title": "Potwierdź hasło",
    "description": "To bezpieczna część aplikacji. Potwierdź hasło, aby kontynuować.",
    "headTitle": "Potwierdzenie hasła",
    "submit": "Potwierdź hasło"
},
"verifyEmail": {
    "title": "Zweryfikuj e-mail",
    "description": "Kliknij link, który wysłaliśmy na Twój adres e-mail.",
    "headTitle": "Weryfikacja e-mail",
    "sent": "Nowy link weryfikacyjny został wysłany na adres e-mail podany podczas rejestracji.",
    "submit": "Wyślij link ponownie",
    "logout": "Wyloguj się"
}
```

In `resources/js/locales/en.json`, extend `auth` with:

```json
"brand": {
    "eyebrow": "Ogarniam Portfel",
    "promise": "Take back control of your household budget",
    "description": "Import history, organize transactions, and track goals without spreadsheets.",
    "benefits": {
        "imports": "Bank history imports",
        "budget": "Budget and categories in one place",
        "goals": "Savings goals close at hand"
    }
},
"confirmPassword": {
    "title": "Confirm your password",
    "description": "This is a secure area of the application. Please confirm your password before continuing.",
    "headTitle": "Confirm password",
    "submit": "Confirm password"
},
"verifyEmail": {
    "title": "Verify email",
    "description": "Please verify your email address by clicking the link we just emailed to you.",
    "headTitle": "Email verification",
    "sent": "A new verification link has been sent to the email address you provided during registration.",
    "submit": "Resend verification email",
    "logout": "Log out"
}
```

- [ ] **Step 2: Replace `AuthSimpleLayout.vue` with the premium shell**

Update `resources/js/layouts/auth/AuthSimpleLayout.vue` to:

```vue
<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';
import { BarChart3, CircleDollarSign, Target } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    title?: string;
    description?: string;
}>();

const { t } = useI18n();

const benefits = [
    { key: 'imports', icon: CircleDollarSign },
    { key: 'budget', icon: BarChart3 },
    { key: 'goals', icon: Target },
];
</script>

<template>
    <div class="grid min-h-svh bg-slate-950 text-white lg:grid-cols-[1.05fr_0.95fr]">
        <section class="relative hidden overflow-hidden p-10 lg:flex lg:flex-col">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.28),transparent_34%),linear-gradient(135deg,#020617_0%,#0f172a_52%,#064e3b_100%)]" />
            <div class="absolute inset-x-10 top-1/3 h-px bg-gradient-to-r from-transparent via-emerald-300/40 to-transparent" />
            <div class="absolute bottom-16 right-12 h-48 w-48 rounded-full border border-emerald-300/20" />

            <div class="relative z-10 flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-950/40">
                    <AppLogoIcon class="size-7 fill-current" />
                </div>
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.28em] text-emerald-200">{{ t('auth.brand.eyebrow') }}</p>
                    <p class="text-lg font-semibold">{{ t('brand.name') }}</p>
                </div>
            </div>

            <div class="relative z-10 mt-auto max-w-xl space-y-8">
                <div class="space-y-4">
                    <p class="text-sm font-medium uppercase tracking-[0.28em] text-emerald-200">{{ t('brand.shortPromise') }}</p>
                    <h1 class="text-4xl font-semibold tracking-tight text-white xl:text-5xl">{{ t('auth.brand.promise') }}</h1>
                    <p class="max-w-lg text-base leading-7 text-slate-300">{{ t('auth.brand.description') }}</p>
                </div>

                <div class="grid gap-3">
                    <div v-for="benefit in benefits" :key="benefit.key" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-400/15 text-emerald-200">
                            <component :is="benefit.icon" class="size-5" />
                        </div>
                        <span class="text-sm font-medium text-slate-100">{{ t(`auth.brand.benefits.${benefit.key}`) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="flex min-h-svh items-center justify-center bg-background px-6 py-10 text-foreground lg:rounded-l-[2rem] lg:px-10">
            <div class="w-full max-w-md">
                <div class="mb-8 flex flex-col items-center gap-4 text-center lg:hidden">
                    <Link :href="route('home')" class="flex items-center gap-3 font-medium">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                            <AppLogoIcon class="size-7 fill-current" />
                        </div>
                        <span>{{ t('brand.name') }}</span>
                    </Link>
                </div>

                <div class="rounded-3xl border border-border/80 bg-card p-6 shadow-xl shadow-slate-950/5 sm:p-8">
                    <div class="mb-8 space-y-2 text-center">
                        <h1 v-if="title" class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                        <p v-if="description" class="text-sm leading-6 text-muted-foreground">{{ description }}</p>
                    </div>
                    <slot />
                </div>
            </div>
        </section>
    </div>
</template>
```

- [ ] **Step 3: Localize confirm password copy**

Update `resources/js/pages/auth/ConfirmPassword.vue` template strings to use i18n:

```vue
<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.confirmPassword.title')" :description="t('auth.confirmPassword.description')">
        <Head :title="t('auth.confirmPassword.headTitle')" />

        <form @submit.prevent="submit">
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label for="password">{{ t('auth.fields.password.label') }}</Label>
                    <Input id="password" type="password" class="mt-1 block w-full" v-model="form.password" required autocomplete="current-password" autofocus />

                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center">
                    <Button class="w-full" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ t('auth.confirmPassword.submit') }}
                    </Button>
                </div>
            </div>
        </form>
    </AuthLayout>
</template>
```

- [ ] **Step 4: Localize verify email copy**

Update `resources/js/pages/auth/VerifyEmail.vue` to:

```vue
<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    status?: string;
}>();

const { t } = useI18n();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <AuthLayout :title="t('auth.verifyEmail.title')" :description="t('auth.verifyEmail.description')">
        <Head :title="t('auth.verifyEmail.headTitle')" />

        <div v-if="status === 'verification-link-sent'" role="status" class="mb-4 text-center text-sm font-medium text-emerald-600">
            {{ t('auth.verifyEmail.sent') }}
        </div>

        <form @submit.prevent="submit" class="space-y-6 text-center">
            <Button :disabled="form.processing" variant="secondary">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                {{ t('auth.verifyEmail.submit') }}
            </Button>

            <TextLink :href="route('logout')" method="post" as="button" class="mx-auto block text-sm">
                {{ t('auth.verifyEmail.logout') }}
            </TextLink>
        </form>
    </AuthLayout>
</template>
```

- [ ] **Step 5: Run formatting and build verification**

Run:

```bash
npm run format:check
npm run build
```

Expected: both commands exit with code 0. If `format:check` reports formatting differences, run `npm run format`, inspect the diff, then rerun `npm run format:check`.

- [ ] **Step 6: Commit auth shell**

Run:

```bash
git add resources/js/layouts/auth/AuthSimpleLayout.vue resources/js/pages/auth/ConfirmPassword.vue resources/js/pages/auth/VerifyEmail.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "feat(auth): add branded premium auth shell"
```

---

## Task 4: Welcome Page

**Files:**
- Modify: `resources/js/pages/Welcome.vue`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add welcome locale keys**

In `resources/js/locales/pl.json`, add this top-level object:

```json
"welcome": {
    "headTitle": "Ogarniam Portfel",
    "eyebrow": "Budżet domowy bez chaosu",
    "title": "Odzyskaj kontrolę nad domowym budżetem",
    "description": "Zbieraj konta, transakcje, importy, budżet i cele w jednym miejscu. Szybciej zobaczysz, dokąd płyną pieniądze i co możesz poprawić.",
    "primaryCta": "Utwórz konto",
    "secondaryCta": "Zaloguj się",
    "dashboardCta": "Przejdź do dashboardu",
    "features": {
        "accounts": {
            "title": "Konta i saldo",
            "description": "Porządkuj konta i miej bieżący obraz pieniędzy."
        },
        "imports": {
            "title": "Import historii",
            "description": "Wczytuj wyciągi i szybciej porządkuj transakcje."
        },
        "goals": {
            "title": "Budżet i cele",
            "description": "Planuj wydatki, kategorie i odkładanie na cele."
        }
    }
}
```

In `resources/js/locales/en.json`, add:

```json
"welcome": {
    "headTitle": "Ogarniam Portfel",
    "eyebrow": "Household budget without chaos",
    "title": "Take back control of your household budget",
    "description": "Bring accounts, transactions, imports, budgets, and goals into one place. See where money flows and what you can improve faster.",
    "primaryCta": "Create account",
    "secondaryCta": "Log in",
    "dashboardCta": "Go to dashboard",
    "features": {
        "accounts": {
            "title": "Accounts and balance",
            "description": "Organize accounts and keep a current money overview."
        },
        "imports": {
            "title": "History imports",
            "description": "Upload statements and organize transactions faster."
        },
        "goals": {
            "title": "Budget and goals",
            "description": "Plan spending, categories, and savings goals."
        }
    }
}
```

- [ ] **Step 2: Replace `Welcome.vue`**

Update `resources/js/pages/Welcome.vue` to:

```vue
<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BarChart3, CircleDollarSign, Target } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const features = [
    { key: 'accounts', icon: CircleDollarSign },
    { key: 'imports', icon: ArrowRight },
    { key: 'goals', icon: Target },
];
</script>

<template>
    <Head :title="t('welcome.headTitle')" />

    <main class="min-h-screen overflow-hidden bg-background text-foreground">
        <section class="relative isolate flex min-h-screen items-center px-6 py-10 lg:px-8">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,hsl(var(--accent)),transparent_32%),linear-gradient(180deg,hsl(var(--background))_0%,hsl(var(--muted))_100%)]" />
            <div class="mx-auto grid w-full max-w-6xl gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                <div class="space-y-8">
                    <nav class="flex items-center justify-between">
                        <Link :href="route('home')" class="flex items-center gap-3 font-semibold">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <AppLogoIcon class="size-7 fill-current" />
                            </span>
                            <span>{{ t('brand.name') }}</span>
                        </Link>

                        <div class="flex items-center gap-3">
                            <Button v-if="$page.props.auth.user" as-child>
                                <Link :href="route('dashboard')">{{ t('welcome.dashboardCta') }}</Link>
                            </Button>
                            <template v-else>
                                <Button variant="ghost" as-child>
                                    <Link :href="route('login')">{{ t('welcome.secondaryCta') }}</Link>
                                </Button>
                                <Button as-child>
                                    <Link :href="route('register')">{{ t('welcome.primaryCta') }}</Link>
                                </Button>
                            </template>
                        </div>
                    </nav>

                    <div class="max-w-2xl space-y-6">
                        <p class="text-sm font-medium uppercase tracking-[0.28em] text-emerald-700 dark:text-emerald-300">{{ t('welcome.eyebrow') }}</p>
                        <h1 class="text-4xl font-semibold tracking-tight sm:text-6xl">{{ t('welcome.title') }}</h1>
                        <p class="text-lg leading-8 text-muted-foreground">{{ t('welcome.description') }}</p>
                    </div>

                    <div v-if="!$page.props.auth.user" class="flex flex-col gap-3 sm:flex-row">
                        <Button size="lg" as-child>
                            <Link :href="route('register')">{{ t('welcome.primaryCta') }}</Link>
                        </Button>
                        <Button size="lg" variant="outline" as-child>
                            <Link :href="route('login')">{{ t('welcome.secondaryCta') }}</Link>
                        </Button>
                    </div>
                </div>

                <aside class="rounded-[2rem] border border-border/80 bg-card/90 p-6 shadow-2xl shadow-slate-950/10 backdrop-blur">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">{{ t('brand.shortPromise') }}</p>
                            <h2 class="text-xl font-semibold">{{ t('brand.name') }}</h2>
                        </div>
                        <BarChart3 class="size-8 text-emerald-600 dark:text-emerald-300" />
                    </div>

                    <div class="space-y-4">
                        <div v-for="feature in features" :key="feature.key" class="rounded-2xl border border-border/80 bg-background/80 p-4">
                            <div class="mb-3 flex items-center gap-3">
                                <span class="flex size-10 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                                    <component :is="feature.icon" class="size-5" />
                                </span>
                                <h3 class="font-semibold">{{ t(`welcome.features.${feature.key}.title`) }}</h3>
                            </div>
                            <p class="text-sm leading-6 text-muted-foreground">{{ t(`welcome.features.${feature.key}.description`) }}</p>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </main>
</template>
```

- [ ] **Step 3: Run formatting and build verification**

Run:

```bash
npm run format:check
npm run build
```

Expected: both commands exit with code 0. If `format:check` reports formatting differences, run `npm run format`, inspect the diff, then rerun `npm run format:check`.

- [ ] **Step 4: Commit welcome page**

Run:

```bash
git add resources/js/pages/Welcome.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "feat(ui): replace starter welcome page"
```

---

## Task 5: Dashboard Branded Preview

**Files:**
- Modify: `resources/js/pages/Dashboard.vue`
- Modify: `resources/js/locales/pl.json`
- Modify: `resources/js/locales/en.json`

- [ ] **Step 1: Add dashboard locale keys**

In `resources/js/locales/pl.json`, add or extend the existing dashboard copy with:

```json
"dashboard": {
    "headTitle": "Dashboard",
    "title": "Dzień dobry w Ogarniam Portfel",
    "description": "Zacznij od kont, importu historii albo ręcznego dodania transakcji. Ten ekran będzie rosnąć razem z Twoimi danymi.",
    "quickActions": {
        "accounts": {
            "title": "Dodaj konto",
            "description": "Ustaw konto i saldo początkowe.",
            "cta": "Przejdź do kont"
        },
        "transactions": {
            "title": "Dodaj transakcję",
            "description": "Zapisz przychód, wydatek lub transfer.",
            "cta": "Przejdź do transakcji"
        },
        "goals": {
            "title": "Zaplanuj cel",
            "description": "Nadaj kierunek odkładanym środkom.",
            "cta": "Przejdź do celów"
        }
    },
    "preview": {
        "title": "Podgląd porządku w finansach",
        "items": {
            "imports": "Importy porządkują historię",
            "budget": "Budżet pokazuje plan i wykonanie",
            "goals": "Cele pokazują postęp oszczędzania"
        }
    }
}
```

In `resources/js/locales/en.json`, add matching keys:

```json
"dashboard": {
    "headTitle": "Dashboard",
    "title": "Welcome to Ogarniam Portfel",
    "description": "Start with accounts, history imports, or a manual transaction. This screen will grow with your data.",
    "quickActions": {
        "accounts": {
            "title": "Add account",
            "description": "Set up an account and opening balance.",
            "cta": "Go to accounts"
        },
        "transactions": {
            "title": "Add transaction",
            "description": "Record income, expense, or transfer.",
            "cta": "Go to transactions"
        },
        "goals": {
            "title": "Plan a goal",
            "description": "Give your savings a clear direction.",
            "cta": "Go to goals"
        }
    },
    "preview": {
        "title": "A cleaner money overview",
        "items": {
            "imports": "Imports organize history",
            "budget": "Budget shows plan and actuals",
            "goals": "Goals show savings progress"
        }
    }
}
```

- [ ] **Step 2: Replace starter dashboard panels**

Update `resources/js/pages/Dashboard.vue` to:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftRight, BarChart3, Target, Wallet } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const quickActions = [
    { key: 'accounts', href: route('accounts.index'), icon: Wallet },
    { key: 'transactions', href: route('transactions.index'), icon: ArrowLeftRight },
    { key: 'goals', href: route('goals.index'), icon: Target },
];

const previewItems = ['imports', 'budget', 'goals'];
</script>

<template>
    <Head :title="t('dashboard.headTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <section class="overflow-hidden rounded-3xl border border-sidebar-border/70 bg-gradient-to-br from-primary via-slate-800 to-emerald-900 p-6 text-primary-foreground shadow-sm dark:from-slate-950 dark:via-slate-900 dark:to-emerald-950 md:p-8">
                <div class="max-w-3xl space-y-3">
                    <p class="text-sm font-medium uppercase tracking-[0.28em] text-emerald-200">{{ t('brand.shortPromise') }}</p>
                    <h1 class="text-3xl font-semibold tracking-tight md:text-4xl">{{ t('dashboard.title') }}</h1>
                    <p class="max-w-2xl text-sm leading-6 text-slate-200 md:text-base">{{ t('dashboard.description') }}</p>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <article v-for="action in quickActions" :key="action.key" class="rounded-2xl border border-sidebar-border/70 bg-card p-5 shadow-sm dark:border-sidebar-border">
                    <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                        <component :is="action.icon" class="size-5" />
                    </div>
                    <h2 class="text-lg font-semibold">{{ t(`dashboard.quickActions.${action.key}.title`) }}</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ t(`dashboard.quickActions.${action.key}.description`) }}</p>
                    <Button class="mt-5 w-full" variant="outline" as-child>
                        <Link :href="action.href">{{ t(`dashboard.quickActions.${action.key}.cta`) }}</Link>
                    </Button>
                </article>
            </section>

            <section class="grid gap-4 rounded-3xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border lg:grid-cols-[0.9fr_1.1fr]">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-emerald-700 dark:text-emerald-300">{{ t('brand.name') }}</p>
                    <h2 class="mt-3 text-2xl font-semibold">{{ t('dashboard.preview.title') }}</h2>
                    <div class="mt-6 space-y-3">
                        <div v-for="item in previewItems" :key="item" class="flex items-center gap-3 rounded-xl bg-muted/60 p-3 text-sm">
                            <span class="size-2 rounded-full bg-emerald-500" />
                            <span>{{ t(`dashboard.preview.items.${item}`) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-border/80 bg-background p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground">{{ t('brand.shortPromise') }}</span>
                        <BarChart3 class="size-5 text-emerald-600 dark:text-emerald-300" />
                    </div>
                    <div class="space-y-3">
                        <div class="h-3 rounded-full bg-muted">
                            <div class="h-3 w-2/3 rounded-full bg-emerald-500" />
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="h-24 rounded-xl bg-emerald-500/20" />
                            <div class="h-24 rounded-xl bg-primary/15" />
                            <div class="h-24 rounded-xl bg-emerald-500/10" />
                        </div>
                        <div class="h-28 rounded-xl border border-dashed border-border bg-muted/40" />
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 3: Run formatting and build verification**

Run:

```bash
npm run format:check
npm run build
```

Expected: both commands exit with code 0. If `format:check` reports formatting differences, run `npm run format`, inspect the diff, then rerun `npm run format:check`.

- [ ] **Step 4: Commit dashboard preview**

Run:

```bash
git add resources/js/pages/Dashboard.vue resources/js/locales/pl.json resources/js/locales/en.json
git commit -m "feat(ui): add branded dashboard preview"
```

---

## Task 6: Final Verification And Polish

**Files:**
- Inspect all files changed in Tasks 1-5.
- No new files expected.

- [ ] **Step 1: Run formatter**

Run:

```bash
npm run format
```

Expected: command exits with code 0. Inspect the diff afterward because this command writes files.

- [ ] **Step 2: Run frontend lint and build**

Run:

```bash
npm run lint
npm run build
```

Expected: both commands exit with code 0. Inspect any lint auto-fixes before committing.

- [ ] **Step 3: Start the app for manual smoke**

Use the existing project workflow. If Sail is running, run:

```bash
./vendor/bin/sail npm run dev
```

If working without Docker, run:

```bash
composer run dev
```

Expected: Vite dev server starts without compile errors.

- [ ] **Step 4: Manual smoke affected screens**

Visit these routes in light and dark appearance modes:

```text
/
/login
/register
/forgot-password
/dashboard
```

Expected:

- Logo is visible and readable.
- Sidebar collapse still leaves a recognizable icon.
- Auth form labels, errors, and submit buttons remain visible.
- Welcome page has no Laravel starter copy.
- Dashboard shows static branded preview and does not show fake financial totals.
- Focus ring is visible on keyboard navigation.

- [ ] **Step 5: Check browser logs**

Run Laravel Boost browser log inspection after smoke testing:

```text
Use laravel-boost browser-logs with 20 entries.
```

Expected: no new Vue warnings, route errors, missing i18n keys, or Vite runtime errors related to the changed screens.

- [ ] **Step 6: Final git diff review**

Run:

```bash
git status --short
git diff
```

Expected: only UI personalization files are changed. No backend files, dependency files, generated manifests, or unrelated docs should be included.

- [ ] **Step 7: Commit final polish if needed**

If formatting, lint, or smoke fixes changed files, run:

```bash
git add resources/js resources/css
git commit -m "fix(ui): polish branded entry screens"
```

If there are no changes after verification, do not create an empty commit.

---

## Self-Review

- Spec coverage: Tasks 1-5 cover logo, name, tokens, sidebar, auth, welcome, dashboard, i18n, accessibility-preserving structure, and frontend-only data scope.
- Scope check: No task adds backend endpoints, database changes, route changes, onboarding wizard, or marketing landing sections.
- Verification: Each implementation task runs `npm run format:check` and `npm run build`; final verification runs formatter, lint, build, manual smoke, and browser log inspection.
- Type consistency: Locale keys introduced in each task match the Vue usage in the same task.
- Red flag scan: No incomplete-instruction markers remain.

