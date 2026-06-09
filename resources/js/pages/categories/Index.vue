<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import DeleteCategoryDialog from '@/components/categories/modals/DeleteCategoryDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { filterCategoriesByType, type CategoryOption } from '@/lib/categories';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { GripVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';

type Category = CategoryOption & {
    type_label_key: string;
    is_system: boolean;
};

const props = defineProps<{
    categories: Category[];
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: t('categories.index.title'), href: route('categories.index') }]);

const expenseList = ref<Category[]>([]);
const incomeList = ref<Category[]>([]);

function syncLists(): void {
    expenseList.value = filterCategoriesByType(props.categories, 'expense') as Category[];
    incomeList.value = filterCategoriesByType(props.categories, 'income') as Category[];
}

watch(() => props.categories, syncLists, { deep: true, immediate: true });

const deletingCategoryId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const deleteProcessing = ref(false);
const deletingCategory = computed(() => props.categories.find((c) => c.id === deletingCategoryId.value) ?? null);

function openDeleteDialog(cat: Category) {
    deletingCategoryId.value = cat.id;
    deleteDialogOpen.value = true;
}

function onReorderEnd(type: 'expense' | 'income', event: SortableEvent): void {
    if (event.oldIndex === undefined || event.newIndex === undefined || event.oldIndex === event.newIndex) {
        return;
    }

    const list = type === 'expense' ? expenseList.value : incomeList.value;

    router.patch(
        route('categories.reorder'),
        {
            type,
            ids: list.map((c) => c.id),
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('categories.index.title')" />

        <template #headerActions>
            <Button as-child>
                <Link :href="route('categories.create')">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    {{ t('categories.index.add') }}
                </Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <section
                v-for="sectionKey in ['income', 'expense'] as const"
                :key="sectionKey"
                class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <h2 class="border-b border-sidebar-border/70 px-4 py-3 text-lg font-semibold dark:border-sidebar-border">
                    {{ t(`budget.sections.${sectionKey}`) }}
                </h2>

                <p v-if="(sectionKey === 'expense' ? expenseList : incomeList).length === 0" class="px-4 py-6 text-sm text-muted-foreground">
                    {{ t('categories.index.empty') }}
                </p>

                <VueDraggable
                    v-else-if="sectionKey === 'expense'"
                    v-model="expenseList"
                    tag="ul"
                    class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    handle=".drag-handle"
                    filter=".no-drag"
                    :animation="150"
                    ghost-class="opacity-50"
                    chosen-class="bg-muted/30"
                    @end="onReorderEnd('expense', $event)"
                >
                    <li
                        v-for="cat in expenseList"
                        :key="cat.id"
                        class="flex flex-col gap-3 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <button
                                type="button"
                                class="drag-handle shrink-0 cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-muted/50 active:cursor-grabbing"
                                :aria-label="t('categories.index.dragHandle')"
                            >
                                <GripVertical class="h-4 w-4" />
                            </button>

                            <Link :href="route('categories.edit', cat.id)" class="flex min-w-0 flex-1 items-center gap-3 hover:opacity-80">
                                <CategoryBadge :name="cat.name" :icon="cat.icon" :color="cat.color" size="md" />
                                <span v-if="cat.is_system" class="shrink-0 text-xs font-normal text-muted-foreground">
                                    ({{ t('categories.index.system') }})
                                </span>
                            </Link>
                        </div>

                        <div class="no-drag flex shrink-0 items-center gap-1">
                            <Button variant="ghost" size="icon" as-child>
                                <Link :href="route('categories.edit', cat.id)" :aria-label="t('actions.edit')">
                                    <Pencil class="h-4 w-4 text-muted-foreground" />
                                </Link>
                            </Button>
                            <Button
                                v-if="!cat.is_system"
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="no-drag"
                                :disabled="deleteProcessing"
                                :aria-label="t('categories.index.delete')"
                                @click="openDeleteDialog(cat)"
                            >
                                <Trash2 class="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </div>
                    </li>
                </VueDraggable>

                <VueDraggable
                    v-else
                    v-model="incomeList"
                    tag="ul"
                    class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    handle=".drag-handle"
                    filter=".no-drag"
                    :animation="150"
                    ghost-class="opacity-50"
                    chosen-class="bg-muted/30"
                    @end="onReorderEnd('income', $event)"
                >
                    <li
                        v-for="cat in incomeList"
                        :key="cat.id"
                        class="flex flex-col gap-3 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <button
                                type="button"
                                class="drag-handle shrink-0 cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-muted/50 active:cursor-grabbing"
                                :aria-label="t('categories.index.dragHandle')"
                            >
                                <GripVertical class="h-4 w-4" />
                            </button>

                            <Link :href="route('categories.edit', cat.id)" class="flex min-w-0 flex-1 items-center gap-3 hover:opacity-80">
                                <CategoryBadge :name="cat.name" :icon="cat.icon" :color="cat.color" size="md" />
                                <span v-if="cat.is_system" class="shrink-0 text-xs font-normal text-muted-foreground">
                                    ({{ t('categories.index.system') }})
                                </span>
                            </Link>
                        </div>

                        <div class="no-drag flex shrink-0 items-center gap-1">
                            <Button variant="ghost" size="icon" as-child>
                                <Link :href="route('categories.edit', cat.id)" :aria-label="t('actions.edit')">
                                    <Pencil class="h-4 w-4 text-muted-foreground" />
                                </Link>
                            </Button>
                            <Button
                                v-if="!cat.is_system"
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="no-drag"
                                :disabled="deleteProcessing"
                                :aria-label="t('categories.index.delete')"
                                @click="openDeleteDialog(cat)"
                            >
                                <Trash2 class="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </div>
                    </li>
                </VueDraggable>
            </section>
        </div>

        <DeleteCategoryDialog
            v-model:open="deleteDialogOpen"
            :category-id="deletingCategoryId"
            :category-name="deletingCategory?.name ?? null"
            :icon="deletingCategory?.icon ?? null"
            :color="deletingCategory?.color ?? null"
            :disabled="deleteProcessing"
            @processing="(value) => (deleteProcessing = value)"
            @success="deletingCategoryId = null"
        />
    </AppLayout>
</template>
