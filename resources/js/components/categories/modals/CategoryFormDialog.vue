<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import CategoryColorPicker from '@/components/categories/CategoryColorPicker.vue';
import CategoryIconPicker from '@/components/categories/CategoryIconPicker.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

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
    open: boolean;
    mode: 'create' | 'edit';
    category: Category | null;
    icons: IconOption[];
    colors: ColorOption[];
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();

const colorError = ref<string | null>(null);

const form = useForm<{
    name: string;
    type: string;
    icon: string;
    color: string | null;
}>({
    name: '',
    type: 'expense',
    icon: 'tag',
    color: null,
});

const typeOptions = computed<DropdownOption<string>[]>(() => [
    { value: 'expense', label: t('categories.enums.type.expense') },
    { value: 'income', label: t('categories.enums.type.income') },
]);

const title = computed(() => (props.mode === 'edit' ? t('categories.edit.title') : t('categories.create.title')));

const description = computed(() => {
    if (props.mode === 'edit') {
        return props.category?.name ?? '';
    }

    return t('categories.create.description');
});

const previewName = computed(() => {
    const trimmed = form.name.trim();

    if (trimmed !== '') {
        return trimmed;
    }

    if (props.mode === 'edit' && props.category) {
        return props.category.name;
    }

    return t('categories.fields.preview');
});

function resetForm(): void {
    colorError.value = null;
    form.clearErrors();

    if (props.mode === 'edit' && props.category) {
        form.name = props.category.name;
        form.type = props.category.type;
        form.icon = props.category.icon;
        form.color = props.category.color;
        return;
    }

    form.reset();
    form.name = '';
    form.type = 'expense';
    form.icon = 'tag';
    form.color = null;
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);

function onOpenChange(value: boolean): void {
    if (form.processing && !value) {
        return;
    }

    emit('update:open', value);
}

function submit(): void {
    colorError.value = null;

    if (props.mode === 'create' && form.color === null) {
        colorError.value = t('categories.validation.colorRequired');
        return;
    }

    const visit = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    };

    if (props.mode === 'edit' && props.category) {
        form.transform((data) => ({
            name: data.name,
            icon: data.icon,
            color: data.color,
        })).patch(route('categories.update', props.category.id), visit);
        return;
    }

    form.transform((data) => data).post(route('categories.store'), visit);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <form class="grid gap-6" @submit.prevent="submit">
                <div class="flex items-center gap-4 rounded-xl border border-border/50 p-4">
                    <CategoryBadge v-if="form.color" :name="previewName" :icon="form.icon" :color="form.color" size="md" />
                    <p v-else class="text-sm text-muted-foreground">{{ t('categories.fields.previewHint') }}</p>
                </div>

                <p v-if="mode === 'edit' && category?.is_system" class="text-sm text-muted-foreground">
                    {{ t('categories.index.system') }}
                </p>

                <FormField for-id="category_name" :label="t('categories.index.fields.name')" :error="form.errors.name">
                    <Input id="category_name" v-model="form.name" required autofocus />
                </FormField>

                <FormField v-if="mode === 'create'" for-id="category_type" :label="t('categories.index.fields.type')" :error="form.errors.type">
                    <DropdownSelect
                        id="category_type"
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

                <DialogFooter>
                    <Button type="button" variant="secondary" :disabled="form.processing" @click="onOpenChange(false)">
                        {{ t('actions.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing" :aria-busy="form.processing || undefined">
                        {{ t('actions.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
