<script setup lang="ts">
import { PopoverTrigger, useForwardProps, type PopoverTriggerProps } from 'radix-vue';

const props = defineProps<PopoverTriggerProps>();

const forwarded = useForwardProps(props);
</script>

<template>
    <PopoverTrigger v-bind="forwarded">
        <slot />
    </PopoverTrigger>
</template>
