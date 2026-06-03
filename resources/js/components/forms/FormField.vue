<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        forId: string;
        label: string;
        error?: string | null;
        hint?: string | null;
    }>(),
    {
        error: null,
        hint: null,
    },
);

const errorId = computed(() => `${props.forId}-error`);
const hintId = computed(() => `${props.forId}-hint`);
const hasError = computed(() => Boolean(props.error));
const hasHint = computed(() => Boolean(props.hint));
</script>

<template>
    <div class="grid gap-2">
        <Label :for="props.forId">{{ props.label }}</Label>
        <slot :error-id="errorId" :hint-id="hintId" :has-error="hasError" :has-hint="hasHint" />
        <InputError :id="errorId" :message="props.error ?? undefined" />
        <p v-if="props.hint" :id="hintId" class="text-sm text-muted-foreground">{{ props.hint }}</p>
    </div>
</template>
