<script setup lang="ts">
import GoalArchiveDialog from '@/components/goals/modals/GoalArchiveDialog.vue';
import GoalBadge from '@/components/goals/GoalBadge.vue';
import GoalProgressBar from '@/components/goals/GoalProgressBar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, GripVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';

type Goal = {
    id: number;
    name: string;
    icon: string;
    color: string;
    sort_order: number;
    target_amount: string | null;
    is_archived: boolean;
    is_completed: boolean;
    is_overdue: boolean;
    progress_percent: number | null;
    balance: string;
};

type GoalFilter = 'active' | 'archived' | 'all';

const props = defineProps<{
    goals: Goal[];
    filter: GoalFilter;
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('goals.index.title'), href: route('goals.index') },
]);

const list = ref<Goal[]>([]);
const archiveDialogOpen = ref(false);
const archivingGoalId = ref<number | null>(null);
const archiveProcessing = ref(false);

const archivingGoal = computed(() => list.value.find((goal) => goal.id === archivingGoalId.value) ?? null);

watch(
    () => props.goals,
    (goals) => {
        list.value = [...goals];
    },
    { immediate: true },
);

const tabs = computed<{ key: GoalFilter; label: string }[]>(() => [
    { key: 'active', label: t('goals.filters.active') },
    { key: 'archived', label: t('goals.filters.archived') },
    { key: 'all', label: t('goals.filters.all') },
]);

function onReorderEnd(event: SortableEvent): void {
    if (event.oldIndex === undefined || event.newIndex === undefined || event.oldIndex === event.newIndex) {
        return;
    }

    router.patch(
        route('goals.reorder'),
        {
            ids: list.value.map((goal) => goal.id),
        },
        { preserveScroll: true },
    );
}

function openArchiveDialog(goal: Goal): void {
    archivingGoalId.value = goal.id;
    archiveDialogOpen.value = true;
}

function deleteGoal(goal: Goal): void {
    if (!window.confirm(t('goals.index.deleteConfirm', { name: goal.name }))) {
        return;
    }

    router.delete(route('goals.destroy', goal.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('goals.index.title')" />

        <template #headerActions>
            <Button as-child>
                <Link :href="route('goals.create')">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    {{ t('goals.index.add') }}
                </Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <div class="inline-flex rounded-lg bg-muted p-1">
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="route('goals.index', { filter: tab.key })"
                    class="rounded-md px-3 py-1.5 text-sm transition-colors"
                    :class="cn(props.filter === tab.key ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground')"
                >
                    {{ tab.label }}
                </Link>
            </div>

            <section class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <h2 class="border-b border-sidebar-border/70 px-4 py-3 text-lg font-semibold dark:border-sidebar-border">
                    {{ t('goals.index.listTitle') }}
                </h2>

                <p v-if="list.length === 0" class="px-4 py-6 text-sm text-muted-foreground">
                    {{ t('goals.index.empty') }}
                </p>

                <VueDraggable
                    v-else
                    v-model="list"
                    tag="ul"
                    class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    handle=".drag-handle"
                    filter=".no-drag"
                    :animation="150"
                    ghost-class="opacity-50"
                    chosen-class="bg-muted/30"
                    @end="onReorderEnd"
                >
                    <li
                        v-for="goal in list"
                        :key="goal.id"
                        class="flex flex-col gap-3 px-4 py-3 text-sm lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex min-w-0 flex-1 items-start gap-2">
                            <button
                                type="button"
                                class="drag-handle mt-1 shrink-0 cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-muted/50 active:cursor-grabbing"
                                :aria-label="t('categories.index.dragHandle')"
                            >
                                <GripVertical class="h-4 w-4" />
                            </button>

                            <div class="min-w-0 flex-1 space-y-2">
                                <GoalBadge :name="goal.name" :icon="goal.icon" :color="goal.color" />
                                <GoalProgressBar :percent="goal.progress_percent" :balance="goal.balance" :target-amount="goal.target_amount" />
                            </div>
                        </div>

                        <div class="no-drag flex flex-wrap items-center gap-2 lg:justify-end">
                            <span class="text-sm font-medium tabular-nums">{{ goal.balance }}</span>

                            <span
                                v-if="goal.is_completed"
                                class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                            >
                                {{ t('goals.status.completed') }}
                            </span>
                            <span
                                v-if="goal.is_overdue"
                                class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                            >
                                {{ t('goals.status.overdue') }}
                            </span>
                            <span
                                v-if="goal.is_archived"
                                class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ t('goals.status.archived') }}
                            </span>

                            <Button variant="ghost" size="icon" as-child>
                                <Link :href="route('goals.edit', goal.id)" :aria-label="t('actions.edit')">
                                    <Pencil class="h-4 w-4 text-muted-foreground" />
                                </Link>
                            </Button>

                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="no-drag"
                                :disabled="archiveProcessing"
                                :aria-label="goal.is_archived ? t('goals.index.unarchive') : t('goals.index.archive')"
                                @click="openArchiveDialog(goal)"
                            >
                                <ArchiveRestore v-if="goal.is_archived" class="h-4 w-4 text-muted-foreground" />
                                <Archive v-else class="h-4 w-4 text-muted-foreground" />
                            </Button>

                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                :aria-label="t('goals.index.delete')"
                                @click="deleteGoal(goal)"
                            >
                                <Trash2 class="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </div>
                    </li>
                </VueDraggable>
            </section>
        </div>

        <GoalArchiveDialog
            v-model:open="archiveDialogOpen"
            :goal-id="archivingGoalId"
            :goal-name="archivingGoal?.name ?? null"
            :icon="archivingGoal?.icon ?? null"
            :color="archivingGoal?.color ?? null"
            :is-archived="archivingGoal?.is_archived ?? false"
            :disabled="archiveProcessing"
            @processing="(value) => (archiveProcessing = value)"
            @success="archivingGoalId = null"
        />
    </AppLayout>
</template>
