<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { Button } from '@/components/ui/button';
import { GripVertical, Pencil, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

export type CategoryRowCategory = {
    id: number;
    name: string;
    icon: string;
    color: string;
    is_system: boolean;
};

defineProps<{
    category: CategoryRowCategory;
    deleteDisabled: boolean;
}>();

defineEmits<{
    edit: [category: CategoryRowCategory];
    delete: [category: CategoryRowCategory];
}>();

const { t } = useI18n();
</script>

<template>
    <li class="list-row-hover flex items-center gap-2 border-b border-border/50 px-3 py-2.5 last:border-b-0 hover:bg-foreground/[0.03] sm:gap-3">
        <button
            type="button"
            class="drag-handle shrink-0 cursor-grab touch-none rounded-lg p-1 text-muted-foreground hover:bg-foreground/[0.06] active:cursor-grabbing"
            :aria-label="t('categories.index.dragHandle')"
        >
            <GripVertical class="size-4" :stroke-width="1.7" aria-hidden="true" />
        </button>

        <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left sm:gap-3" @click="$emit('edit', category)">
            <CategoryBadge :name="category.name" :icon="category.icon" :color="category.color" size="md" />
            <span v-if="category.is_system" class="shrink-0 text-xs text-muted-foreground"> ({{ t('categories.index.system') }}) </span>
        </button>

        <div class="no-drag flex shrink-0 items-center gap-1">
            <Button variant="ghost" size="icon" type="button" :aria-label="t('actions.edit')" @click="$emit('edit', category)">
                <Pencil class="size-4 text-muted-foreground" :stroke-width="1.7" aria-hidden="true" />
            </Button>
            <Button
                v-if="!category.is_system"
                variant="ghost"
                size="icon"
                type="button"
                class="no-drag"
                :disabled="deleteDisabled"
                :aria-label="t('categories.index.delete')"
                @click="$emit('delete', category)"
            >
                <Trash2 class="size-4 text-muted-foreground" :stroke-width="1.7" aria-hidden="true" />
            </Button>
        </div>
    </li>
</template>
