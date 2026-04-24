<script setup lang="ts">
import { LayoutGrid, List } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

type AccountsViewMode = 'grid' | 'list';

defineProps<{
    modelValue: AccountsViewMode;
}>();

defineEmits<{
    'update:modelValue': [mode: AccountsViewMode];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800" :aria-label="t('accounts.index.view.toggleAria')">
        <button
            type="button"
            @click="$emit('update:modelValue', 'grid')"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                modelValue === 'grid'
                    ? 'bg-white shadow-sm dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <LayoutGrid class="-ml-1 h-4 w-4" aria-hidden="true" />
            <span class="ml-1.5 text-sm">{{ t('accounts.index.view.grid') }}</span>
        </button>
        <button
            type="button"
            @click="$emit('update:modelValue', 'list')"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                modelValue === 'list'
                    ? 'bg-white shadow-sm dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <List class="-ml-1 h-4 w-4" aria-hidden="true" />
            <span class="ml-1.5 text-sm">{{ t('accounts.index.view.list') }}</span>
        </button>
    </div>
</template>
