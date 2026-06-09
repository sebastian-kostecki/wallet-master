<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import CategoryColorPicker from '@/components/categories/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/categories/CategoryIconPicker.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type IconOption = {
    value: string;
    label_key: string;
};

type ColorOption = {
    value: string;
};

const props = defineProps<{
    icons: IconOption[];
    colors: ColorOption[];
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('categories.index.title'), href: route('categories.index') },
    { title: t('categories.create.title'), href: route('categories.create') },
]);

const typeOptions = computed<DropdownOption<string>[]>(() => [
    { value: 'expense', label: t('categories.enums.type.expense') },
    { value: 'income', label: t('categories.enums.type.income') },
]);

const colorError = ref<string | null>(null);

const form = useForm({
    name: '',
    type: 'expense' as string,
    icon: 'tag',
    color: null as string | null,
});

const previewName = computed(() => form.name.trim() || t('categories.fields.preview'));

function submit() {
    colorError.value = null;

    if (form.color === null) {
        colorError.value = t('categories.validation.colorRequired');
        return;
    }

    form.post(route('categories.store'));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('categories.create.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <form class="grid gap-6" @submit.prevent="submit">
                    <div class="flex items-center gap-4 rounded-lg border border-sidebar-border/50 p-4 dark:border-sidebar-border">
                        <CategoryBadge v-if="form.color" :name="previewName" :icon="form.icon" :color="form.color" size="md" />
                        <p v-else class="text-sm text-muted-foreground">{{ t('categories.fields.previewHint') }}</p>
                    </div>

                    <FormField for-id="name" :label="t('categories.index.fields.name')" :error="form.errors.name">
                        <Input id="name" v-model="form.name" required autofocus />
                    </FormField>

                    <FormField for-id="type" :label="t('categories.index.fields.type')" :error="form.errors.type">
                        <DropdownSelect
                            id="type"
                            :model-value="form.type"
                            :options="typeOptions"
                            :placeholder="t('categories.index.fields.type')"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.type = value ?? '')"
                        />
                    </FormField>

                    <FormField :label="t('categories.fields.color')" :error="form.errors.color ?? colorError ?? undefined">
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
