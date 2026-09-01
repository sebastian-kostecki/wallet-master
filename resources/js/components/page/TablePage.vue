<script setup lang="ts">
import PaginationBar from '@/components/pagination/PaginationBar.vue';
import type { Paginator } from '@/components/pagination/types';

defineProps<{
    paginator?: Paginator;
    query?: Record<string, unknown>;
    isEmpty?: boolean;
}>();
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="$slots.toolbar" class="flex flex-wrap items-center gap-2">
            <slot name="toolbar" />
        </div>

        <slot v-if="isEmpty" name="empty" />
        <slot v-else />

        <PaginationBar v-if="paginator && !isEmpty" :paginator="paginator" :query="query ?? {}" />
    </div>
</template>
