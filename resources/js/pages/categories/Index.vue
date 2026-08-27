<script setup lang="ts">
import CategoryRow, { type CategoryRowCategory } from '@/components/categories/CategoryRow.vue';
import CategoryFormDialog from '@/components/categories/modals/CategoryFormDialog.vue';
import DeleteCategoryDialog from '@/components/categories/modals/DeleteCategoryDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { filterCategoriesByType, type CategoryOption } from '@/lib/categories';
import { router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';

type IconOption = {
    value: string;
    label_key: string;
};

type ColorOption = {
    value: string;
};

type Category = CategoryOption & {
    type_label_key: string;
    is_system: boolean;
};

const props = defineProps<{
    categories: Category[];
    icons: IconOption[];
    colors: ColorOption[];
}>();

const { t } = useI18n();

const expenseList = ref<Category[]>([]);
const incomeList = ref<Category[]>([]);

const sectionKeys = ['income', 'expense'] as const;

function syncLists(): void {
    expenseList.value = filterCategoriesByType(props.categories, 'expense') as Category[];
    incomeList.value = filterCategoriesByType(props.categories, 'income') as Category[];
}

watch(() => props.categories, syncLists, { deep: true, immediate: true });

const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingCategory = ref<Category | null>(null);

const deletingCategoryId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const deleteProcessing = ref(false);
const deletingCategory = computed(() => props.categories.find((c) => c.id === deletingCategoryId.value) ?? null);

function openCreateDialog(): void {
    formMode.value = 'create';
    editingCategory.value = null;
    formOpen.value = true;
}

function openEditDialog(cat: CategoryRowCategory): void {
    const fullCategory = props.categories.find((c) => c.id === cat.id) ?? null;
    if (fullCategory === null) {
        return;
    }

    formMode.value = 'edit';
    editingCategory.value = fullCategory;
    formOpen.value = true;
}

function openDeleteDialog(cat: CategoryRowCategory) {
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
    <AppLayout>
        <template #headerActions>
            <Button type="button" @click="openCreateDialog">
                <Plus class="size-4" :stroke-width="1.7" aria-hidden="true" />
                {{ t('categories.index.add') }}
            </Button>
        </template>

        <div class="overflow-hidden rounded-2xl border border-transparent bg-card">
            <div v-for="sectionKey in sectionKeys" :key="sectionKey" :class="{ 'border-t border-border/50': sectionKey === 'expense' }">
                <h2 class="px-3 pb-2 pt-4 text-[11px] font-semibold uppercase tracking-widest text-muted-foreground/60">
                    {{ t(`budget.sections.${sectionKey}`) }}
                </h2>

                <p v-if="(sectionKey === 'expense' ? expenseList : incomeList).length === 0" class="px-3 py-4 text-sm text-muted-foreground">
                    {{ t('categories.index.empty') }}
                </p>

                <VueDraggable
                    v-else-if="sectionKey === 'expense'"
                    v-model="expenseList"
                    tag="ul"
                    handle=".drag-handle"
                    filter=".no-drag"
                    :animation="150"
                    ghost-class="opacity-50"
                    chosen-class="bg-muted/30"
                    @end="onReorderEnd('expense', $event)"
                >
                    <CategoryRow
                        v-for="cat in expenseList"
                        :key="cat.id"
                        :category="cat"
                        :delete-disabled="deleteProcessing"
                        @edit="openEditDialog"
                        @delete="openDeleteDialog"
                    />
                </VueDraggable>

                <VueDraggable
                    v-else
                    v-model="incomeList"
                    tag="ul"
                    handle=".drag-handle"
                    filter=".no-drag"
                    :animation="150"
                    ghost-class="opacity-50"
                    chosen-class="bg-muted/30"
                    @end="onReorderEnd('income', $event)"
                >
                    <CategoryRow
                        v-for="cat in incomeList"
                        :key="cat.id"
                        :category="cat"
                        :delete-disabled="deleteProcessing"
                        @edit="openEditDialog"
                        @delete="openDeleteDialog"
                    />
                </VueDraggable>
            </div>
        </div>

        <CategoryFormDialog v-model:open="formOpen" :mode="formMode" :category="editingCategory" :icons="icons" :colors="colors" />

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
