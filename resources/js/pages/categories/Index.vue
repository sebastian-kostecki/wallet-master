<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Category = {
    id: number;
    name: string;
    type: string;
    type_label_key: string;
    icon: string;
    color: string;
    sort_order: number;
    is_system: boolean;
};

const props = defineProps<{
    categories: Category[];
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('categories.index.title'), href: route('categories.index') },
]);

const expenseCategories = computed(() => props.categories.filter((c) => c.type === 'expense'));
const incomeCategories = computed(() => props.categories.filter((c) => c.type === 'income'));

function deleteCategory(cat: Category) {
    if (!window.confirm(t('categories.index.deleteConfirm', { name: cat.name }))) {
        return;
    }

    router.delete(route('categories.destroy', cat.id), { preserveScroll: true });
}

function moveCategory(cat: Category, direction: 'up' | 'down', siblings: Category[]) {
    const index = siblings.findIndex((c) => c.id === cat.id);
    const swapIndex = direction === 'up' ? index - 1 : index + 1;
    const neighbor = siblings[swapIndex];

    if (neighbor === undefined) {
        return;
    }

    router.patch(
        route('categories.update', cat.id),
        { sort_order: neighbor.sort_order },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.patch(route('categories.update', neighbor.id), { sort_order: cat.sort_order }, { preserveScroll: true });
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('categories.index.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <Button as-child>
                    <Link :href="route('categories.create')">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ t('categories.index.add') }}
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('budget.monthly')">{{ t('budget.nav') }}</Link>
                </Button>
            </div>

            <section
                v-for="(sectionCategories, sectionKey) in { expense: expenseCategories, income: incomeCategories }"
                :key="sectionKey"
                class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <h2 class="border-b border-sidebar-border/70 px-4 py-3 text-lg font-semibold dark:border-sidebar-border">
                    {{ t(`budget.sections.${sectionKey}`) }}
                </h2>

                <p v-if="sectionCategories.length === 0" class="px-4 py-6 text-sm text-muted-foreground">
                    {{ t('categories.index.empty') }}
                </p>

                <ul v-else class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                    <li
                        v-for="(cat, index) in sectionCategories"
                        :key="cat.id"
                        class="flex flex-col gap-3 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <div class="flex shrink-0 flex-col gap-0.5">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-6 w-6"
                                    type="button"
                                    :disabled="index === 0"
                                    :aria-label="t('categories.index.moveUp')"
                                    @click="moveCategory(cat, 'up', sectionCategories)"
                                >
                                    <ArrowUp class="h-3 w-3" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-6 w-6"
                                    type="button"
                                    :disabled="index === sectionCategories.length - 1"
                                    :aria-label="t('categories.index.moveDown')"
                                    @click="moveCategory(cat, 'down', sectionCategories)"
                                >
                                    <ArrowDown class="h-3 w-3" />
                                </Button>
                            </div>

                            <Link :href="route('categories.edit', cat.id)" class="flex min-w-0 flex-1 items-center gap-3 hover:opacity-80">
                                <CategoryBadge :name="cat.name" :icon="cat.icon" :color="cat.color" size="md" />
                                <span v-if="cat.is_system" class="shrink-0 text-xs font-normal text-muted-foreground">
                                    ({{ t('categories.index.system') }})
                                </span>
                            </Link>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
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
                                :aria-label="t('categories.index.delete')"
                                @click="deleteCategory(cat)"
                            >
                                <Trash2 class="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
