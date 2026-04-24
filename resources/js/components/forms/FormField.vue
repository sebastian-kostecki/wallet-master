<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

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
</script>

<template>
    <div class="grid gap-2">
        <Label :for="props.forId">{{ props.label }}</Label>
        <slot />
        <InputError :message="props.error ?? undefined" />
        <p v-if="props.hint" class="text-sm text-muted-foreground">{{ props.hint }}</p>
    </div>
</template>

