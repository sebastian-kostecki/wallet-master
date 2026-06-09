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
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" :class="className" role="img" aria-hidden="true" v-bind="$attrs">
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
        <path
            d="M28.5 23.5H31.5V26.5"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="text-emerald-300 dark:text-emerald-400"
        />
    </svg>
</template>
