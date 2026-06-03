<script setup lang="ts">
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, useSlots } from 'vue';

withDefaults(
    defineProps<{
        /** Disables all fields inside the advanced section (e.g. while the parent form is submitting). */
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const slots = useSlots();

const hasHint = computed(() => Boolean(slots.hint));

const isOpen = ref(false);
</script>

<template>
    <section
        class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        data-advanced-section
    >
        <Collapsible v-model:open="isOpen" class="w-full" :disabled="disabled">
            <CollapsibleTrigger
                type="button"
                class="flex w-full items-center justify-between gap-3 rounded-lg text-left outline-none ring-offset-background transition-colors hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-60 -m-1 p-1"
            >
                <span class="min-w-0 flex-1">
                    <span class="block text-base font-semibold text-foreground">
                        <slot name="title" />
                    </span>
                </span>
                <ChevronDown
                    class="h-5 w-5 shrink-0 text-muted-foreground transition-transform duration-200"
                    :class="cn(isOpen && 'rotate-180')"
                    aria-hidden="true"
                />
            </CollapsibleTrigger>

            <CollapsibleContent>
                <p v-if="hasHint" class="mt-3 text-sm text-muted-foreground">
                    <slot name="hint" />
                </p>
                <fieldset
                    class="mt-4 min-w-0 space-y-6 border-0 p-0 m-0 disabled:opacity-60"
                    :disabled="disabled || !isOpen"
                >
                    <slot />
                </fieldset>
            </CollapsibleContent>
        </Collapsible>
    </section>
</template>
