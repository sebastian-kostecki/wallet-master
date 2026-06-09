<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import CategoryColorPicker from '@/components/categories/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/categories/CategoryIconPicker.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type IconOption = {
    value: string;
    label_key: string;
};

type ColorOption = {
    value: string;
};

type Category = {
    id: number;
    name: string;
    type: string;
    icon: string;
    color: string;
    is_system: boolean;
};

const props = defineProps<{
    category: Category;
    icons: IconOption[];
    colors: ColorOption[];
    has_transactions: boolean;
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('categories.index.title'), href: route('categories.index') },
    { title: t('categories.edit.title'), href: route('categories.edit', props.category.id) },
]);

const form = useForm({
    name: props.category.name,
    icon: props.category.icon,
    color: props.category.color,
});

const previewName = computed(() => form.name.trim() || props.category.name);

function submit() {
    form.patch(route('categories.update', props.category.id));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('categories.edit.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <form class="grid gap-6" @submit.prevent="submit">
                    <CategoryBadge :name="previewName" :icon="form.icon" :color="form.color" size="md" />

                    <p v-if="category.is_system" class="text-sm text-muted-foreground">
                        {{ t('categories.index.system') }}
                    </p>

                    <FormField for-id="name" :label="t('categories.index.fields.name')" :error="form.errors.name">
                        <Input id="name" v-model="form.name" required autofocus />
                    </FormField>

                    <FormField :label="t('categories.fields.color')" :error="form.errors.color">
                        <CategoryColorPicker :colors="props.colors" :model-value="form.color" @update:model-value="(v) => (form.color = v)" />
                    </FormField>

                    <FormField :label="t('categories.fields.icon')" :error="form.errors.icon">
                        <CategoryIconPicker :icons="props.icons" :model-value="form.icon" @update:model-value="(v) => (form.icon = v)" />
                    </FormField>

                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" :disabled="form.processing">{{ t('actions.save') }}</Button>
                        <Button variant="outline" as-child>
                            <Link :href="route('categories.index')">{{ t('actions.cancel') }}</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
