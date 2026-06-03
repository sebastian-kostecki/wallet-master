<script setup lang="ts">
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Category = {
    id: number;
    name: string;
    type: string;
    type_label_key: string;
    sort_order: number;
    is_system: boolean;
    annual_estimate_amount: string | null;
};

const props = defineProps<{
    categories: Category[];
    year: number;
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('categories.index.title'), href: route('categories.index') },
]);

const typeOptions = computed<DropdownOption<string>[]>(() => [
    { value: 'expense', label: t('categories.enums.type.expense') },
    { value: 'income', label: t('categories.enums.type.income') },
]);

const createForm = useForm({
    name: '',
    type: 'expense' as string,
});

const editingId = ref<number | null>(null);
const editingName = ref('');

const expenseCategories = computed(() => props.categories.filter((c) => c.type === 'expense'));
const incomeCategories = computed(() => props.categories.filter((c) => c.type === 'income'));

function submitCreate() {
    createForm.post(route('categories.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function startEdit(cat: Category) {
    editingId.value = cat.id;
    editingName.value = cat.name;
}

function cancelEdit() {
    editingId.value = null;
    editingName.value = '';
}

function saveName(cat: Category) {
    const trimmed = editingName.value.trim();
    if (trimmed === '' || trimmed === cat.name) {
        cancelEdit();
        return;
    }

    router.patch(route('categories.update', cat.id), { name: trimmed }, {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
}

function saveAnnualEstimate(cat: Category, rawValue: string) {
    const trimmed = rawValue.trim();
    const amount = trimmed === '' ? null : trimmed.replace(',', '.');

    router.patch(route('categories.estimates.annual', cat.id), { year: props.year, amount }, { preserveScroll: true });
}

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

function changeYear(delta: number) {
    router.get(route('categories.index', { year: props.year + delta }), {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('categories.index.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <Button variant="outline" as-child>
                    <Link :href="route('budget.monthly')">{{ t('budget.nav') }}</Link>
                </Button>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="changeYear(-1)">←</Button>
                    <span class="text-sm font-medium">{{ year }}</span>
                    <Button variant="outline" @click="changeYear(1)">→</Button>
                </div>
            </div>

            <form
                class="flex max-w-md flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                @submit.prevent="submitCreate"
            >
                <h2 class="text-lg font-semibold">{{ t('categories.index.add') }}</h2>
                <FormField :label="t('categories.index.fields.name')" :error="createForm.errors.name">
                    <Input v-model="createForm.name" />
                </FormField>
                <FormField :label="t('categories.index.fields.type')" :error="createForm.errors.type">
                    <DropdownSelect v-model="createForm.type" :options="typeOptions" />
                </FormField>
                <Button type="submit" :disabled="createForm.processing">{{ t('categories.index.add') }}</Button>
            </form>

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
                        <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
                            <div class="flex min-w-0 items-center gap-2">
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

                                <div v-if="editingId === cat.id" class="flex min-w-0 flex-1 items-center gap-2">
                                    <Input v-model="editingName" class="h-8" @keyup.enter="saveName(cat)" @keyup.escape="cancelEdit" />
                                    <Button size="sm" type="button" @click="saveName(cat)">{{ t('actions.save') }}</Button>
                                    <Button size="sm" variant="ghost" type="button" @click="cancelEdit">{{ t('actions.cancel') }}</Button>
                                </div>
                                <button
                                    v-else
                                    type="button"
                                    class="truncate text-left font-medium hover:underline"
                                    @click="startEdit(cat)"
                                >
                                    {{ cat.name }}
                                    <span v-if="cat.is_system" class="ml-1 text-xs font-normal text-muted-foreground">
                                        ({{ t('categories.index.system') }})
                                    </span>
                                </button>
                            </div>

                            <div class="flex items-center gap-2 sm:w-48">
                                <label class="sr-only" :for="`annual-${cat.id}`">{{ t('categories.index.annualEstimate') }}</label>
                                <Input
                                    :id="`annual-${cat.id}`"
                                    :key="`${year}-${cat.id}-${cat.annual_estimate_amount ?? ''}`"
                                    type="text"
                                    inputmode="decimal"
                                    class="h-8 tabular-nums"
                                    :placeholder="t('categories.index.annualEstimatePlaceholder')"
                                    :default-value="cat.annual_estimate_amount ?? ''"
                                    @blur="(e) => saveAnnualEstimate(cat, (e.target as HTMLInputElement).value)"
                                />
                            </div>
                        </div>

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
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
