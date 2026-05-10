<script setup lang="ts">
import { computed, useSlots } from 'vue';

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
</script>

<template>
    <section
        class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        data-advanced-section
    >
        <h2 class="text-base font-semibold text-foreground">
            <slot name="title" />
        </h2>
        <p v-if="hasHint" class="mt-1 text-sm text-muted-foreground">
            <slot name="hint" />
        </p>
        <fieldset class="mt-4 min-w-0 space-y-6 border-0 p-0 m-0 disabled:opacity-60" :disabled="disabled">
            <slot />
        </fieldset>
    </section>
</template>
