# Shared UI primitives — `resources/js/components/ui/`

Framework: **Laravel 13 + Inertia Vue 3 + Tailwind v3 + shadcn-vue** (radix-vue).
Primitives live in `resources/js/components/ui/`. Domain UI lives in `resources/js/components/{domain}/`, never in `ui/`.

Wallet overrides vs stock shadcn: buttons/inputs `rounded-xl`; cards `rounded-2xl border-transparent bg-card`; primary is ink (`--primary: 0 0% 9%` light / white in dark), not mint.

## Skipped (not in `components/ui/`)

- **table** — no `ui/table`
- **badge** — no `ui/badge` (domain badges: `categories/CategoryBadge.vue`, `pockets/PocketBadge.vue`)
- **select** — no `ui/select` (custom `forms/DropdownSelect.vue`)
- **tabs** — no `ui/tabs`
- **pagination** — no `ui/pagination` (app uses `components/pagination/PaginationBar.vue`)

Not dumped (80+ ui files): extra dropdown/dialog/sheet/sidebar subfiles. Prioritized Button, Input, Label, Card*, Breadcrumb* (all), Separator, Skeleton, DropdownMenu Trigger/Content/Item, Dialog/Content/Header/Title, Sheet+SheetContent, Tooltip*, Avatar*, Sidebar.vue + SidebarTrigger + SidebarInset + SidebarMenuButton.

## buttonVariants / Button barrel

- File: `resources/js/components/ui/button/index.ts`
- Description: CVA variants for Button: default/destructive/outline/secondary/ghost/link; sizes default/sm/lg/icon. Base uses rounded-xl.
- Key props: `variant, size`

```ts
import { cva, type VariantProps } from 'class-variance-authority';

export { default as Button } from './Button.vue';

export const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-sm font-medium transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground hover:bg-primary/80',
                destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                outline: 'border border-input bg-transparent text-foreground/60 hover:bg-foreground/[0.04] hover:text-foreground',
                secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
                ghost: 'text-foreground/60 hover:bg-foreground/[0.06] hover:text-foreground',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-10 px-4',
                sm: 'h-8 rounded-lg px-3 text-xs',
                lg: 'h-12 rounded-xl px-8',
                icon: 'h-9 w-9 rounded-xl',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export type ButtonVariants = VariantProps<typeof buttonVariants>;
```

## Button

- File: `resources/js/components/ui/button/Button.vue`
- Description: Polymorphic shadcn-vue button via radix-vue Primitive.
- Key props: `variant, size, as, asChild, class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Primitive, type PrimitiveProps } from 'radix-vue';
import type { HTMLAttributes } from 'vue';
import { buttonVariants, type ButtonVariants } from '.';

interface Props extends PrimitiveProps {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    as: 'button',
});
</script>

<template>
    <Primitive :as="as" :as-child="asChild" :class="cn(buttonVariants({ variant, size }), props.class)">
        <slot />
    </Primitive>
</template>
```

## Input barrel

- File: `resources/js/components/ui/input/index.ts`
- Description: Re-exports Input.

```ts
export { default as Input } from './Input.vue';
```

## Input

- File: `resources/js/components/ui/input/Input.vue`
- Description: Text input primitive.
- Key props: `class, plus native input attrs`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <input
        v-model="modelValue"
        :class="
            cn(
                'flex h-11 w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground/70 focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50',
                props.class,
            )
        "
    />
</template>
```

## Label barrel

- File: `resources/js/components/ui/label/index.ts`
- Description: Re-exports Label.

```ts
export { default as Label } from './Label.vue';
```

## Label

- File: `resources/js/components/ui/label/Label.vue`
- Description: Form label primitive.
- Key props: `class, for`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Label, type LabelProps } from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<LabelProps & { class?: HTMLAttributes['class'] }>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});
</script>

<template>
    <Label
        v-bind="delegatedProps"
        :class="cn('text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70', props.class)"
    >
        <slot />
    </Label>
</template>
```

## Card barrel

- File: `resources/js/components/ui/card/index.ts`
- Description: Re-exports Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter.

```ts
export { default as Card } from './Card.vue';
export { default as CardContent } from './CardContent.vue';
export { default as CardDescription } from './CardDescription.vue';
export { default as CardFooter } from './CardFooter.vue';
export { default as CardHeader } from './CardHeader.vue';
export { default as CardTitle } from './CardTitle.vue';
```

## Card

- File: `resources/js/components/ui/card/Card.vue`
- Description: Lifted white surface: rounded-2xl, border-transparent, bg-card (page is grey, cards are white).
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('rounded-2xl border border-transparent bg-card text-card-foreground', props.class)">
        <slot />
    </div>
</template>
```

## CardHeader

- File: `resources/js/components/ui/card/CardHeader.vue`
- Description: Card header slot wrapper.
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('flex flex-col gap-y-1.5 p-6', props.class)">
        <slot />
    </div>
</template>
```

## CardTitle

- File: `resources/js/components/ui/card/CardTitle.vue`
- Description: Card title text.
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <h3 :class="cn('text-2xl font-semibold leading-none tracking-tight', props.class)">
        <slot />
    </h3>
</template>
```

## CardDescription

- File: `resources/js/components/ui/card/CardDescription.vue`
- Description: Muted card description text.
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <p :class="cn('text-sm text-muted-foreground', props.class)">
        <slot />
    </p>
</template>
```

## CardContent

- File: `resources/js/components/ui/card/CardContent.vue`
- Description: Card body slot wrapper.
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('p-6 pt-0', props.class)">
        <slot />
    </div>
</template>
```

## CardFooter

- File: `resources/js/components/ui/card/CardFooter.vue`
- Description: Card footer slot wrapper.
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('flex items-center p-6 pt-0', props.class)">
        <slot />
    </div>
</template>
```

## Checkbox barrel

- File: `resources/js/components/ui/checkbox/index.ts`
- Description: Re-exports Checkbox.

```ts
export { default as Checkbox } from './Checkbox.vue'
```

## Checkbox

- File: `resources/js/components/ui/checkbox/Checkbox.vue`
- Description: Radix checkbox primitive.
- Key props: `checked, disabled, class`

```vue
<script setup lang="ts">
import type { CheckboxRootEmits, CheckboxRootProps } from 'radix-vue'
import { cn } from '@/lib/utils'
import { Check } from 'lucide-vue-next'
import { CheckboxIndicator, CheckboxRoot, useForwardPropsEmits } from 'radix-vue'
import { computed, type HTMLAttributes } from 'vue'

const props = defineProps<CheckboxRootProps & { class?: HTMLAttributes['class'] }>()
const emits = defineEmits<CheckboxRootEmits>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <CheckboxRoot
    v-bind="forwarded"
    :class="
      cn('peer size-5 shrink-0 rounded-sm border border-input ring-offset-background focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-accent-foreground',
         props.class)"
  >
    <CheckboxIndicator class="flex h-full w-full items-center justify-center text-current">
      <slot>
        <Check class="size-3.5 stroke-[3]" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
```

## Dialog barrel

- File: `resources/js/components/ui/dialog/index.ts`
- Description: Re-exports Dialog root and parts (Close, Content, Description, Footer, Header, ScrollContent, Title, Trigger).

```ts
export { default as Dialog } from './Dialog.vue';
export { default as DialogClose } from './DialogClose.vue';
export { default as DialogContent } from './DialogContent.vue';
export { default as DialogDescription } from './DialogDescription.vue';
export { default as DialogFooter } from './DialogFooter.vue';
export { default as DialogHeader } from './DialogHeader.vue';
export { default as DialogScrollContent } from './DialogScrollContent.vue';
export { default as DialogTitle } from './DialogTitle.vue';
export { default as DialogTrigger } from './DialogTrigger.vue';
```

## Dialog

- File: `resources/js/components/ui/dialog/Dialog.vue`
- Description: Dialog root (radix-vue DialogRoot).
- Key props: `open, defaultOpen`

```vue
<script setup lang="ts">
import { DialogRoot, useForwardPropsEmits, type DialogRootEmits, type DialogRootProps } from 'radix-vue';

const props = defineProps<DialogRootProps>();
const emits = defineEmits<DialogRootEmits>();

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
    <DialogRoot v-bind="forwarded">
        <slot />
    </DialogRoot>
</template>
```

## DialogContent

- File: `resources/js/components/ui/dialog/DialogContent.vue`
- Description: Modal panel with overlay and close button.
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import { X } from 'lucide-vue-next';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    useForwardPropsEmits,
    type DialogContentEmits,
    type DialogContentProps,
} from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<DialogContentProps & { class?: HTMLAttributes['class'] }>();
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <DialogPortal>
        <DialogOverlay
            class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent
            v-bind="forwarded"
            :class="
                cn(
                    'fixed left-1/2 top-1/2 z-50 grid w-full max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%] sm:rounded-lg',
                    props.class,
                )
            "
        >
            <slot />

            <DialogClose
                class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground"
            >
                <X class="h-4 w-4" />
                <span class="sr-only">Close</span>
            </DialogClose>
        </DialogContent>
    </DialogPortal>
</template>
```

## DialogHeader

- File: `resources/js/components/ui/dialog/DialogHeader.vue`
- Description: Dialog header stack.
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('flex flex-col gap-y-1.5 text-center sm:text-left', props.class)">
        <slot />
    </div>
</template>
```

## DialogTitle

- File: `resources/js/components/ui/dialog/DialogTitle.vue`
- Description: Accessible dialog title.
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import { DialogTitle, useForwardProps, type DialogTitleProps } from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<DialogTitleProps & { class?: HTMLAttributes['class'] }>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
    <DialogTitle v-bind="forwardedProps" :class="cn('text-lg font-semibold leading-none tracking-tight', props.class)">
        <slot />
    </DialogTitle>
</template>
```

## DropdownMenu barrel

- File: `resources/js/components/ui/dropdown-menu/index.ts`
- Description: Re-exports dropdown parts. Main pieces dumped below: Trigger, Content, Item.

```ts
export { default as DropdownMenu } from './DropdownMenu.vue';

export { DropdownMenuPortal } from 'radix-vue';
export { default as DropdownMenuCheckboxItem } from './DropdownMenuCheckboxItem.vue';
export { default as DropdownMenuContent } from './DropdownMenuContent.vue';
export { default as DropdownMenuGroup } from './DropdownMenuGroup.vue';
export { default as DropdownMenuItem } from './DropdownMenuItem.vue';
export { default as DropdownMenuLabel } from './DropdownMenuLabel.vue';
export { default as DropdownMenuRadioGroup } from './DropdownMenuRadioGroup.vue';
export { default as DropdownMenuRadioItem } from './DropdownMenuRadioItem.vue';
export { default as DropdownMenuSeparator } from './DropdownMenuSeparator.vue';
export { default as DropdownMenuShortcut } from './DropdownMenuShortcut.vue';
export { default as DropdownMenuSub } from './DropdownMenuSub.vue';
export { default as DropdownMenuSubContent } from './DropdownMenuSubContent.vue';
export { default as DropdownMenuSubTrigger } from './DropdownMenuSubTrigger.vue';
export { default as DropdownMenuTrigger } from './DropdownMenuTrigger.vue';
```

## DropdownMenu

- File: `resources/js/components/ui/dropdown-menu/DropdownMenu.vue`
- Description: Dropdown menu root.
- Key props: `open`

```vue
<script setup lang="ts">
import { DropdownMenuRoot, useForwardPropsEmits, type DropdownMenuRootEmits, type DropdownMenuRootProps } from 'radix-vue';

const props = defineProps<DropdownMenuRootProps>();
const emits = defineEmits<DropdownMenuRootEmits>();

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
    <DropdownMenuRoot v-bind="forwarded">
        <slot />
    </DropdownMenuRoot>
</template>
```

## DropdownMenuTrigger

- File: `resources/js/components/ui/dropdown-menu/DropdownMenuTrigger.vue`
- Description: Opens the dropdown.
- Key props: `asChild`

```vue
<script setup lang="ts">
import { DropdownMenuTrigger, useForwardProps, type DropdownMenuTriggerProps } from 'radix-vue';

const props = defineProps<DropdownMenuTriggerProps>();

const forwardedProps = useForwardProps(props);
</script>

<template>
    <DropdownMenuTrigger class="outline-none" v-bind="forwardedProps">
        <slot />
    </DropdownMenuTrigger>
</template>
```

## DropdownMenuContent

- File: `resources/js/components/ui/dropdown-menu/DropdownMenuContent.vue`
- Description: Positioned dropdown panel.
- Key props: `align, sideOffset, class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import {
    DropdownMenuContent,
    DropdownMenuPortal,
    useForwardPropsEmits,
    type DropdownMenuContentEmits,
    type DropdownMenuContentProps,
} from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = withDefaults(defineProps<DropdownMenuContentProps & { class?: HTMLAttributes['class'] }>(), {
    sideOffset: 4,
});
const emits = defineEmits<DropdownMenuContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <DropdownMenuPortal>
        <DropdownMenuContent
            v-bind="forwarded"
            :class="
                cn(
                    'z-50 min-w-32 overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                    props.class,
                )
            "
        >
            <slot />
        </DropdownMenuContent>
    </DropdownMenuPortal>
</template>
```

## DropdownMenuItem

- File: `resources/js/components/ui/dropdown-menu/DropdownMenuItem.vue`
- Description: Selectable dropdown row.
- Key props: `disabled, inset, class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import { DropdownMenuItem, useForwardProps, type DropdownMenuItemProps } from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<DropdownMenuItemProps & { class?: HTMLAttributes['class']; inset?: boolean }>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
    <DropdownMenuItem
        v-bind="forwardedProps"
        :class="
            cn(
                'relative flex cursor-default select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none transition-colors focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&>svg]:size-4 [&>svg]:shrink-0',
                inset && 'pl-8',
                props.class,
            )
        "
    >
        <slot />
    </DropdownMenuItem>
</template>
```

## Breadcrumb barrel

- File: `resources/js/components/ui/breadcrumb/index.ts`
- Description: Re-exports all breadcrumb primitives. ALL files in this folder are included (critical for layout).

```ts
export { default as Breadcrumb } from './Breadcrumb.vue';
export { default as BreadcrumbEllipsis } from './BreadcrumbEllipsis.vue';
export { default as BreadcrumbItem } from './BreadcrumbItem.vue';
export { default as BreadcrumbLink } from './BreadcrumbLink.vue';
export { default as BreadcrumbList } from './BreadcrumbList.vue';
export { default as BreadcrumbPage } from './BreadcrumbPage.vue';
export { default as BreadcrumbSeparator } from './BreadcrumbSeparator.vue';
```

## Breadcrumb

- File: `resources/js/components/ui/breadcrumb/Breadcrumb.vue`
- Description: Nav landmark wrapping the crumb list.
- Key props: `class`

```vue
<script lang="ts" setup>
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <nav aria-label="breadcrumb" :class="props.class">
        <slot />
    </nav>
</template>
```

## BreadcrumbList

- File: `resources/js/components/ui/breadcrumb/BreadcrumbList.vue`
- Description: Ordered list of crumbs.
- Key props: `class`

```vue
<script lang="ts" setup>
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <ol :class="cn('flex flex-wrap items-center gap-1.5 break-words text-sm text-muted-foreground sm:gap-2.5', props.class)">
        <slot />
    </ol>
</template>
```

## BreadcrumbItem

- File: `resources/js/components/ui/breadcrumb/BreadcrumbItem.vue`
- Description: Single crumb list item.
- Key props: `class`

```vue
<script lang="ts" setup>
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <li :class="cn('inline-flex items-center gap-1.5', props.class)">
        <slot />
    </li>
</template>
```

## BreadcrumbLink

- File: `resources/js/components/ui/breadcrumb/BreadcrumbLink.vue`
- Description: Clickable ancestor crumb.
- Key props: `href, asChild, class`

```vue
<script lang="ts" setup>
import { cn } from '@/lib/utils';
import { Primitive, type PrimitiveProps } from 'radix-vue';
import type { HTMLAttributes } from 'vue';

const props = withDefaults(defineProps<PrimitiveProps & { class?: HTMLAttributes['class'] }>(), {
    as: 'a',
});
</script>

<template>
    <Primitive :as="as" :as-child="asChild" :class="cn('transition-colors hover:text-foreground', props.class)">
        <slot />
    </Primitive>
</template>
```

## BreadcrumbPage

- File: `resources/js/components/ui/breadcrumb/BreadcrumbPage.vue`
- Description: Current-page crumb (non-link).
- Key props: `class`

```vue
<script lang="ts" setup>
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span role="link" aria-disabled="true" aria-current="page" :class="cn('font-normal text-foreground', props.class)">
        <slot />
    </span>
</template>
```

## BreadcrumbSeparator

- File: `resources/js/components/ui/breadcrumb/BreadcrumbSeparator.vue`
- Description: Chevron separator between crumbs.
- Key props: `class`

```vue
<script lang="ts" setup>
import { cn } from '@/lib/utils';
import { ChevronRight } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <li role="presentation" aria-hidden="true" :class="cn('[&>svg]:h-3.5 [&>svg]:w-3.5', props.class)">
        <slot>
            <ChevronRight />
        </slot>
    </li>
</template>
```

## BreadcrumbEllipsis

- File: `resources/js/components/ui/breadcrumb/BreadcrumbEllipsis.vue`
- Description: Collapsed crumbs ellipsis.
- Key props: `class`

```vue
<script lang="ts" setup>
import { cn } from '@/lib/utils';
import { MoreHorizontal } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span role="presentation" aria-hidden="true" :class="cn('flex h-9 w-9 items-center justify-center', props.class)">
        <slot>
            <MoreHorizontal class="h-4 w-4" />
        </slot>
        <span class="sr-only">More</span>
    </span>
</template>
```

## Separator barrel

- File: `resources/js/components/ui/separator/index.ts`
- Description: Re-exports Separator.

```ts
export { default as Separator } from './Separator.vue';
```

## Separator

- File: `resources/js/components/ui/separator/Separator.vue`
- Description: Horizontal/vertical divider.
- Key props: `orientation, decorative, class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Separator, type SeparatorProps } from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<SeparatorProps & { class?: HTMLAttributes['class']; label?: string }>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});
</script>

<template>
    <Separator
        v-bind="delegatedProps"
        :class="cn('relative shrink-0 bg-border', props.orientation === 'vertical' ? 'h-full w-px' : 'h-px w-full', props.class)"
    >
        <span
            v-if="props.label"
            :class="
                cn(
                    'absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center bg-background text-xs text-muted-foreground',
                    props.orientation === 'vertical' ? 'w-[1px] px-1 py-2' : 'h-[1px] px-2 py-1',
                )
            "
            >{{ props.label }}</span
        >
    </Separator>
</template>
```

## Skeleton barrel

- File: `resources/js/components/ui/skeleton/index.ts`
- Description: Re-exports Skeleton.

```ts
export { default as Skeleton } from './Skeleton.vue';
```

## Skeleton

- File: `resources/js/components/ui/skeleton/Skeleton.vue`
- Description: Loading placeholder pulse.
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

interface SkeletonProps {
    class?: HTMLAttributes['class'];
}

const props = defineProps<SkeletonProps>();
</script>

<template>
    <div :class="cn('animate-pulse rounded-md bg-muted', props.class)" />
</template>
```

## Sheet barrel

- File: `resources/js/components/ui/sheet/index.ts`
- Description: Re-exports Sheet parts plus sheetVariants CVA (side: top/bottom/left/right).
- Key props: `side`

```ts
import { cva, type VariantProps } from 'class-variance-authority';

export { default as Sheet } from './Sheet.vue';
export { default as SheetClose } from './SheetClose.vue';
export { default as SheetContent } from './SheetContent.vue';
export { default as SheetDescription } from './SheetDescription.vue';
export { default as SheetFooter } from './SheetFooter.vue';
export { default as SheetHeader } from './SheetHeader.vue';
export { default as SheetTitle } from './SheetTitle.vue';
export { default as SheetTrigger } from './SheetTrigger.vue';

export const sheetVariants = cva(
    'fixed z-50 gap-4 bg-background p-6 shadow-lg transition ease-in-out data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:duration-300 data-[state=open]:duration-500',
    {
        variants: {
            side: {
                top: 'inset-x-0 top-0 border-b data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top',
                bottom: 'inset-x-0 bottom-0 border-t data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom',
                left: 'inset-y-0 left-0 h-full w-3/4 border-r data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left sm:max-w-sm',
                right: 'inset-y-0 right-0 h-full w-3/4 border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right sm:max-w-sm',
            },
        },
        defaultVariants: {
            side: 'right',
        },
    },
);

export type SheetVariants = VariantProps<typeof sheetVariants>;
```

## Sheet

- File: `resources/js/components/ui/sheet/Sheet.vue`
- Description: Sheet/drawer root.
- Key props: `open`

```vue
<script setup lang="ts">
import { DialogRoot, useForwardPropsEmits, type DialogRootEmits, type DialogRootProps } from 'radix-vue';

const props = defineProps<DialogRootProps>();
const emits = defineEmits<DialogRootEmits>();

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
    <DialogRoot v-bind="forwarded">
        <slot />
    </DialogRoot>
</template>
```

## SheetContent

- File: `resources/js/components/ui/sheet/SheetContent.vue`
- Description: Slide-over panel (used by mobile sidebar).
- Key props: `side, class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import { X } from 'lucide-vue-next';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    useForwardPropsEmits,
    type DialogContentEmits,
    type DialogContentProps,
} from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';
import { sheetVariants, type SheetVariants } from '.';

interface SheetContentProps extends DialogContentProps {
    class?: HTMLAttributes['class'];
    side?: SheetVariants['side'];
}

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<SheetContentProps>();

const emits = defineEmits<DialogContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, side, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <DialogPortal>
        <DialogOverlay
            class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
        />
        <DialogContent :class="cn(sheetVariants({ side }), props.class)" v-bind="{ ...forwarded, ...$attrs }">
            <slot />

            <DialogClose
                class="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-secondary"
            >
                <X class="h-4 w-4 text-muted-foreground" />
            </DialogClose>
        </DialogContent>
    </DialogPortal>
</template>
```

## Tooltip barrel

- File: `resources/js/components/ui/tooltip/index.ts`
- Description: Re-exports Tooltip, TooltipContent, TooltipProvider, TooltipTrigger.

```ts
export { default as Tooltip } from './Tooltip.vue';
export { default as TooltipContent } from './TooltipContent.vue';
export { default as TooltipProvider } from './TooltipProvider.vue';
export { default as TooltipTrigger } from './TooltipTrigger.vue';
```

## TooltipProvider

- File: `resources/js/components/ui/tooltip/TooltipProvider.vue`
- Description: Tooltip delay/context provider.
- Key props: `delayDuration`

```vue
<script setup lang="ts">
import { TooltipProvider, type TooltipProviderProps } from 'radix-vue';

const props = defineProps<TooltipProviderProps>();
</script>

<template>
    <TooltipProvider v-bind="props">
        <slot />
    </TooltipProvider>
</template>
```

## Tooltip

- File: `resources/js/components/ui/tooltip/Tooltip.vue`
- Description: Tooltip root.
- Key props: `open`

```vue
<script setup lang="ts">
import { TooltipRoot, useForwardPropsEmits, type TooltipRootEmits, type TooltipRootProps } from 'radix-vue';

const props = defineProps<TooltipRootProps>();
const emits = defineEmits<TooltipRootEmits>();

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
    <TooltipRoot v-bind="forwarded">
        <slot />
    </TooltipRoot>
</template>
```

## TooltipTrigger

- File: `resources/js/components/ui/tooltip/TooltipTrigger.vue`
- Description: Element that shows the tooltip.
- Key props: `asChild`

```vue
<script setup lang="ts">
import { TooltipTrigger, type TooltipTriggerProps } from 'radix-vue';

const props = defineProps<TooltipTriggerProps>();
</script>

<template>
    <TooltipTrigger v-bind="props">
        <slot />
    </TooltipTrigger>
</template>
```

## TooltipContent

- File: `resources/js/components/ui/tooltip/TooltipContent.vue`
- Description: Tooltip popup.
- Key props: `side, class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import { TooltipContent, TooltipPortal, useForwardPropsEmits, type TooltipContentEmits, type TooltipContentProps } from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(defineProps<TooltipContentProps & { class?: HTMLAttributes['class'] }>(), {
    sideOffset: 4,
});

const emits = defineEmits<TooltipContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <TooltipPortal>
        <TooltipContent
            v-bind="{ ...forwarded, ...$attrs }"
            :class="
                cn(
                    'z-50 overflow-hidden rounded-md border bg-popover px-3 py-1.5 text-sm text-popover-foreground shadow-md animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                    props.class,
                )
            "
        >
            <slot />
        </TooltipContent>
    </TooltipPortal>
</template>
```

## Avatar barrel

- File: `resources/js/components/ui/avatar/index.ts`
- Description: Re-exports Avatar, AvatarImage, AvatarFallback.

```ts
import { cva, type VariantProps } from 'class-variance-authority';

export { default as Avatar } from './Avatar.vue';
export { default as AvatarFallback } from './AvatarFallback.vue';
export { default as AvatarImage } from './AvatarImage.vue';

export const avatarVariant = cva(
    'inline-flex items-center justify-center font-normal text-foreground select-none shrink-0 bg-secondary overflow-hidden',
    {
        variants: {
            size: {
                sm: 'h-10 w-10 text-xs',
                base: 'h-16 w-16 text-2xl',
                lg: 'h-32 w-32 text-5xl',
            },
            shape: {
                circle: 'rounded-full',
                square: 'rounded-md',
            },
        },
    },
);

export type AvatarVariants = VariantProps<typeof avatarVariant>;
```

## Avatar

- File: `resources/js/components/ui/avatar/Avatar.vue`
- Description: User avatar root.
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import { AvatarRoot } from 'radix-vue';
import type { HTMLAttributes } from 'vue';
import { avatarVariant, type AvatarVariants } from '.';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        size?: AvatarVariants['size'];
        shape?: AvatarVariants['shape'];
    }>(),
    {
        size: 'sm',
        shape: 'circle',
    },
);
</script>

<template>
    <AvatarRoot :class="cn(avatarVariant({ size, shape }), props.class)">
        <slot />
    </AvatarRoot>
</template>
```

## AvatarImage

- File: `resources/js/components/ui/avatar/AvatarImage.vue`
- Description: Avatar photo.
- Key props: `src, alt`

```vue
<script setup lang="ts">
import { AvatarImage, type AvatarImageProps } from 'radix-vue';

const props = defineProps<AvatarImageProps>();
</script>

<template>
    <AvatarImage v-bind="props" class="h-full w-full object-cover" />
</template>
```

## AvatarFallback

- File: `resources/js/components/ui/avatar/AvatarFallback.vue`
- Description: Initials fallback when image missing.
- Key props: `class`

```vue
<script setup lang="ts">
import { AvatarFallback, type AvatarFallbackProps } from 'radix-vue';

const props = defineProps<AvatarFallbackProps>();
</script>

<template>
    <AvatarFallback v-bind="props">
        <slot />
    </AvatarFallback>
</template>
```

## Sidebar

- File: `resources/js/components/ui/sidebar/Sidebar.vue`
- Description: Sidebar shell; AppSidebar uses variant=inset (floating card). Mobile uses Sheet.
- Key props: `side, variant (sidebar|floating|inset), collapsible (offcanvas|icon|none), class`

```vue
<script setup lang="ts">
import Sheet from '@/components/ui/sheet/Sheet.vue';
import SheetContent from '@/components/ui/sheet/SheetContent.vue';
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';
import { SIDEBAR_WIDTH_MOBILE, useSidebar } from './utils';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<{
        side?: 'left' | 'right';
        variant?: 'sidebar' | 'floating' | 'inset';
        collapsible?: 'offcanvas' | 'icon' | 'none';
        class?: HTMLAttributes['class'];
    }>(),
    {
        side: 'left',
        variant: 'sidebar',
        collapsible: 'offcanvas',
    },
);

const { isMobile, state, openMobile, setOpenMobile } = useSidebar();
</script>

<template>
    <div
        v-if="collapsible === 'none'"
        :class="cn('flex h-full w-[--sidebar-width] flex-col bg-sidebar text-sidebar-foreground', props.class)"
        v-bind="$attrs"
    >
        <slot />
    </div>

    <Sheet v-else-if="isMobile" :open="openMobile" v-bind="$attrs" @update:open="setOpenMobile">
        <SheetContent
            data-sidebar="sidebar"
            data-mobile="true"
            :side="side"
            class="w-[--sidebar-width] bg-sidebar p-0 text-sidebar-foreground [&>button]:hidden"
            :style="{
                '--sidebar-width': SIDEBAR_WIDTH_MOBILE,
            }"
        >
            <div class="flex h-full w-full flex-col">
                <slot />
            </div>
        </SheetContent>
    </Sheet>

    <div
        v-else
        class="group peer hidden md:block"
        :data-state="state"
        :data-collapsible="state === 'collapsed' ? collapsible : ''"
        :data-variant="variant"
        :data-side="side"
    >
        <!-- This is what handles the sidebar gap on desktop  -->
        <div
            :class="
                cn(
                    'relative h-svh w-[--sidebar-width] bg-transparent transition-[width] duration-200 ease-linear',
                    'group-data-[collapsible=offcanvas]:w-0',
                    'group-data-[side=right]:rotate-180',
                    variant === 'floating' || variant === 'inset'
                        ? 'group-data-[collapsible=icon]:w-[calc(var(--sidebar-width-icon)_+_theme(spacing.4))]'
                        : 'group-data-[collapsible=icon]:w-[--sidebar-width-icon]',
                )
            "
        />
        <div
            :class="
                cn(
                    'fixed inset-y-0 z-10 hidden h-svh w-[--sidebar-width] transition-[left,right,width] duration-200 ease-linear md:flex',
                    side === 'left'
                        ? 'left-0 group-data-[collapsible=offcanvas]:left-[calc(var(--sidebar-width)*-1)]'
                        : 'right-0 group-data-[collapsible=offcanvas]:right-[calc(var(--sidebar-width)*-1)]',
                    // Adjust the padding for floating and inset variants.
                    variant === 'floating' || variant === 'inset'
                        ? 'p-2 group-data-[collapsible=icon]:w-[calc(var(--sidebar-width-icon)_+_theme(spacing.4)_+2px)]'
                        : 'group-data-[collapsible=icon]:w-[--sidebar-width-icon] group-data-[side=left]:border-r group-data-[side=right]:border-l',
                    props.class,
                )
            "
            v-bind="$attrs"
        >
            <div
                data-sidebar="sidebar"
                class="flex h-full w-full flex-col overflow-hidden bg-sidebar group-data-[variant=floating]:rounded-lg group-data-[variant=floating]:border group-data-[variant=floating]:border-sidebar-border group-data-[variant=floating]:shadow group-data-[variant=inset]:rounded-2xl group-data-[variant=inset]:border-0"
            >
                <slot />
            </div>
        </div>
    </div>
</template>
```

## SidebarTrigger

- File: `resources/js/components/ui/sidebar/SidebarTrigger.vue`
- Description: Toggles sidebar open state (shown in AppSidebarHeader on md:hidden).
- Key props: `class`

```vue
<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { cn } from '@/lib/utils';
import { PanelLeft } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { useSidebar } from './utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const { toggleSidebar } = useSidebar();
</script>

<template>
    <Button data-sidebar="trigger" variant="ghost" size="icon" :class="cn('h-7 w-7', props.class)" @click="toggleSidebar">
        <PanelLeft />
        <span class="sr-only">Toggle Sidebar</span>
    </Button>
</template>
```

## SidebarInset

- File: `resources/js/components/ui/sidebar/SidebarInset.vue`
- Description: Main content inset next to sidebar (used by AppContent variant=sidebar).
- Key props: `class`

```vue
<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <main
        id="main-content"
        :class="
            cn(
                'relative flex min-h-svh flex-1 flex-col overflow-y-auto bg-background md:min-h-0',
                'peer-data-[variant=inset]:min-h-[calc(100svh-theme(spacing.4))] md:peer-data-[variant=inset]:min-h-0',
                props.class,
            )
        "
    >
        <slot />
    </main>
</template>
```

## SidebarMenuButton

- File: `resources/js/components/ui/sidebar/SidebarMenuButton.vue`
- Description: Nav item button; App NavMain overrides to rounded-xl + ink active styles.
- Key props: `isActive, tooltip, variant, size, asChild, class`

```vue
<script setup lang="ts">
import Tooltip from '@/components/ui/tooltip/Tooltip.vue';
import TooltipContent from '@/components/ui/tooltip/TooltipContent.vue';
import TooltipTrigger from '@/components/ui/tooltip/TooltipTrigger.vue';
import { computed, type Component } from 'vue';
import SidebarMenuButtonChild, { type SidebarMenuButtonProps } from './SidebarMenuButtonChild.vue';
import { useSidebar } from './utils';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<
        SidebarMenuButtonProps & {
            tooltip?: string | Component;
        }
    >(),
    {
        as: 'button',
        variant: 'default',
        size: 'default',
    },
);

const { isMobile, state } = useSidebar();

const delegatedProps = computed(() => {
    const { tooltip, ...delegated } = props;
    return delegated;
});
</script>

<template>
    <SidebarMenuButtonChild v-if="!tooltip" v-bind="{ ...delegatedProps, ...$attrs }">
        <slot />
    </SidebarMenuButtonChild>

    <Tooltip v-else>
        <TooltipTrigger as-child>
            <SidebarMenuButtonChild v-bind="{ ...delegatedProps, ...$attrs }">
                <slot />
            </SidebarMenuButtonChild>
        </TooltipTrigger>
        <TooltipContent side="right" align="center" :hidden="state !== 'collapsed' || isMobile">
            <template v-if="typeof tooltip === 'string'">
                {{ tooltip }}
            </template>
            <component :is="tooltip" v-else />
        </TooltipContent>
    </Tooltip>
</template>
```

