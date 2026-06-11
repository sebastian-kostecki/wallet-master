<script setup lang="ts">
import PocketArchiveDialog from '@/components/pockets/modals/PocketArchiveDialog.vue';
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import PocketProgressBar from '@/components/pockets/PocketProgressBar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/formatMoney';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, GripVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';

type Pocket = {
    id: number;
    name: string;
    icon: string;
    color: string;
    sort_order: number;
    currency: {
        code: string;
        symbol: string;
        precision: number;
    };
    target_amount: string | null;
    is_archived: boolean;
    is_completed: boolean;
    is_overdue: boolean;
    progress_percent: number | null;
    balance: string;
};

type PocketFilter = 'active' | 'archived' | 'all';

const props = defineProps<{
    pockets: Pocket[];
    filter: PocketFilter;
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: t('pockets.index.title'), href: route('pockets.index') }]);

const list = ref<Pocket[]>([]);
const archiveDialogOpen = ref(false);
const archivingPocketId = ref<number | null>(null);
const archiveProcessing = ref(false);

const archivingPocket = computed(() => list.value.find((pocket) => pocket.id === archivingPocketId.value) ?? null);

watch(
    () => props.pockets,
    (pockets) => {
        list.value = [...pockets];
    },
    { immediate: true },
);

const tabs = computed<{ key: PocketFilter; label: string }[]>(() => [
    { key: 'active', label: t('pockets.filters.active') },
    { key: 'archived', label: t('pockets.filters.archived') },
    { key: 'all', label: t('pockets.filters.all') },
]);

function onReorderEnd(event: SortableEvent): void {
    if (event.oldIndex === undefined || event.newIndex === undefined || event.oldIndex === event.newIndex) {
        return;
    }

    router.patch(
        route('pockets.reorder'),
        {
            ids: list.value.map((pocket) => pocket.id),
        },
        { preserveScroll: true },
    );
}

function openArchiveDialog(pocket: Pocket): void {
    archivingPocketId.value = pocket.id;
    archiveDialogOpen.value = true;
}

function deletePocket(pocket: Pocket): void {
    if (!window.confirm(t('pockets.index.deleteConfirm', { name: pocket.name }))) {
        return;
    }

    router.delete(route('pockets.destroy', pocket.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('pockets.index.title')" />

        <template #headerActions>
            <Button as-child>
                <Link :href="route('pockets.create')">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    {{ t('pockets.index.add') }}
                </Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <div class="inline-flex rounded-lg bg-muted p-1">
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="route('pockets.index', { filter: tab.key })"
                    class="rounded-md px-3 py-1.5 text-sm transition-colors"
                    :class="cn(props.filter === tab.key ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground')"
                >
                    {{ tab.label }}
                </Link>
            </div>

            <section class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <h2 class="border-b border-sidebar-border/70 px-4 py-3 text-lg font-semibold dark:border-sidebar-border">
                    {{ t('pockets.index.listTitle') }}
                </h2>

                <p v-if="list.length === 0" class="px-4 py-6 text-sm text-muted-foreground">
                    {{ t('pockets.index.empty') }}
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
                        v-for="pocket in list"
                        :key="pocket.id"
                        class="list-row-hover flex flex-col gap-3 px-4 py-3 text-sm lg:flex-row lg:items-center lg:justify-between"
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
                                <PocketBadge :name="pocket.name" :icon="pocket.icon" :color="pocket.color" />
                                <PocketProgressBar
                                    :percent="pocket.progress_percent"
                                    :balance="pocket.balance"
                                    :target-amount="pocket.target_amount"
                                    :currency="pocket.currency"
                                />
                            </div>
                        </div>

                        <div class="no-drag flex flex-wrap items-center gap-2 lg:justify-end">
                            <span class="text-sm font-medium tabular-nums">{{ formatMoney(pocket.balance, pocket.currency) }}</span>

                            <span
                                v-if="pocket.is_completed"
                                class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                            >
                                {{ t('pockets.status.completed') }}
                            </span>
                            <span
                                v-if="pocket.is_overdue"
                                class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                            >
                                {{ t('pockets.status.overdue') }}
                            </span>
                            <span
                                v-if="pocket.is_archived"
                                class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ t('pockets.status.archived') }}
                            </span>

                            <Button variant="ghost" size="icon" as-child>
                                <Link :href="route('pockets.edit', pocket.id)" :aria-label="t('actions.edit')">
                                    <Pencil class="h-4 w-4 text-muted-foreground" />
                                </Link>
                            </Button>

                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="no-drag"
                                :disabled="archiveProcessing"
                                :aria-label="pocket.is_archived ? t('pockets.index.unarchive') : t('pockets.index.archive')"
                                @click="openArchiveDialog(pocket)"
                            >
                                <ArchiveRestore v-if="pocket.is_archived" class="h-4 w-4 text-muted-foreground" />
                                <Archive v-else class="h-4 w-4 text-muted-foreground" />
                            </Button>

                            <Button variant="ghost" size="icon" type="button" :aria-label="t('pockets.index.delete')" @click="deletePocket(pocket)">
                                <Trash2 class="h-4 w-4 text-muted-foreground" />
                            </Button>
                        </div>
                    </li>
                </VueDraggable>
            </section>
        </div>

        <PocketArchiveDialog
            v-model:open="archiveDialogOpen"
            :pocket-id="archivingPocketId"
            :pocket-name="archivingPocket?.name ?? null"
            :icon="archivingPocket?.icon ?? null"
            :color="archivingPocket?.color ?? null"
            :is-archived="archivingPocket?.is_archived ?? false"
            :disabled="archiveProcessing"
            @processing="(value) => (archiveProcessing = value)"
            @success="archivingPocketId = null"
        />
    </AppLayout>
</template>
