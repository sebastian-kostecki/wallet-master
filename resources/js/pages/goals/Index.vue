<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Goal = {
    id: number;
    name: string;
    sort_order: number;
    annual_estimate_amount: string | null;
};

const props = defineProps<{
    goals: Goal[];
    year: number;
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('goals.index.title'), href: route('goals.index') },
]);

const createForm = useForm({
    name: '',
});

const editingId = ref<number | null>(null);
const editingName = ref('');

function submitCreate() {
    createForm.post(route('goals.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function startEdit(goal: Goal) {
    editingId.value = goal.id;
    editingName.value = goal.name;
}

function cancelEdit() {
    editingId.value = null;
    editingName.value = '';
}

function saveName(goal: Goal) {
    const trimmed = editingName.value.trim();
    if (trimmed === '' || trimmed === goal.name) {
        cancelEdit();
        return;
    }

    router.patch(route('goals.update', goal.id), { name: trimmed }, {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
}

function saveAnnualEstimate(goal: Goal, rawValue: string) {
    const trimmed = rawValue.trim();
    const normalized = trimmed.replace(',', '.');
    const current = goal.annual_estimate_amount ?? '';

    if (normalized === current || (normalized === '' && current === '')) {
        return;
    }

    const amount = trimmed === '' ? null : normalized;

    router.patch(route('goals.estimates.annual', goal.id), { year: props.year, amount }, { preserveScroll: true });
}

function deleteGoal(goal: Goal) {
    if (!window.confirm(t('goals.index.deleteConfirm', { name: goal.name }))) {
        return;
    }

    router.delete(route('goals.destroy', goal.id), { preserveScroll: true });
}

function changeYear(delta: number) {
    router.get(route('goals.index', { year: props.year + delta }), {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('goals.index.title')" />

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
                <h2 class="text-lg font-semibold">{{ t('goals.index.add') }}</h2>
                <FormField :label="t('goals.index.fields.name')" :error="createForm.errors.name">
                    <Input v-model="createForm.name" />
                </FormField>
                <Button type="submit" :disabled="createForm.processing">{{ t('goals.index.add') }}</Button>
            </form>

            <section class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <h2 class="border-b border-sidebar-border/70 px-4 py-3 text-lg font-semibold dark:border-sidebar-border">
                    {{ t('goals.index.listTitle') }}
                </h2>

                <p v-if="goals.length === 0" class="px-4 py-6 text-sm text-muted-foreground">
                    {{ t('goals.index.empty') }}
                </p>

                <ul v-else class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                    <li
                        v-for="goal in goals"
                        :key="goal.id"
                        class="flex flex-col gap-3 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
                            <div v-if="editingId === goal.id" class="flex min-w-0 flex-1 items-center gap-2">
                                <Input v-model="editingName" class="h-8" @keyup.enter="saveName(goal)" @keyup.escape="cancelEdit" />
                                <Button size="sm" type="button" @click="saveName(goal)">{{ t('actions.save') }}</Button>
                                <Button size="sm" variant="ghost" type="button" @click="cancelEdit">{{ t('actions.cancel') }}</Button>
                            </div>
                            <button
                                v-else
                                type="button"
                                class="truncate text-left font-medium hover:underline"
                                @click="startEdit(goal)"
                            >
                                {{ goal.name }}
                            </button>

                            <div class="flex items-center gap-2 sm:w-48">
                                <label class="sr-only" :for="`annual-${goal.id}`">{{ t('goals.index.annualEstimate') }}</label>
                                <Input
                                    :id="`annual-${goal.id}`"
                                    :key="`${year}-${goal.id}-${goal.annual_estimate_amount ?? ''}`"
                                    type="text"
                                    inputmode="decimal"
                                    class="h-8 tabular-nums"
                                    :placeholder="t('goals.index.annualEstimatePlaceholder')"
                                    :default-value="goal.annual_estimate_amount ?? ''"
                                    @blur="(e) => saveAnnualEstimate(goal, (e.target as HTMLInputElement).value)"
                                />
                            </div>
                        </div>

                        <Button
                            variant="ghost"
                            size="icon"
                            type="button"
                            :aria-label="t('goals.index.delete')"
                            @click="deleteGoal(goal)"
                        >
                            <Trash2 class="h-4 w-4 text-muted-foreground" />
                        </Button>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
