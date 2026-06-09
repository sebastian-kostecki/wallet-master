<script setup lang="ts">
import { Button } from '@/components/ui/button';
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
    <div role="group" class="inline-flex gap-1 rounded-lg border border-input bg-muted/30 p-1" :aria-label="t('accounts.index.view.toggleAria')">
        <Button
            type="button"
            :variant="modelValue === 'grid' ? 'secondary' : 'ghost'"
            class="h-9 px-3.5"
            :aria-pressed="modelValue === 'grid'"
            @click="$emit('update:modelValue', 'grid')"
        >
            <LayoutGrid aria-hidden="true" />
            {{ t('accounts.index.view.grid') }}
        </Button>
        <Button
            type="button"
            :variant="modelValue === 'list' ? 'secondary' : 'ghost'"
            class="h-9 px-3.5"
            :aria-pressed="modelValue === 'list'"
            @click="$emit('update:modelValue', 'list')"
        >
            <List aria-hidden="true" />
            {{ t('accounts.index.view.list') }}
        </Button>
    </div>
</template>
