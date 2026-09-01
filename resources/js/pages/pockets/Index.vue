<script setup lang="ts">
import PocketFormDialog from '@/components/pockets/modals/PocketFormDialog.vue';
import PocketRow, { type PocketRowPocket } from '@/components/pockets/PocketRow.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';
import { ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';

type IconOption = {
    value: string;
    label_key: string;
};

type ColorOption = {
    value: string;
};

type Currency = {
    id: number;
    code: string;
    name: string;
    symbol: string;
    precision: number;
};

type Pocket = {
    id: number;
    name: string;
    icon: string;
    color: string;
    sort_order: number;
    currency_id: number;
    currency: Currency;
    initial_balance: string;
    target_amount: string | null;
    planning_mode: 'monthly' | 'by_date' | null;
    monthly_contribution: string | null;
    target_date: string | null;
    recommended_monthly: string | null;
    projected_completion_date: string | null;
    is_completed: boolean;
    is_overdue: boolean;
    progress_percent: number | null;
    balance: string;
};

const props = defineProps<{
    pockets: Pocket[];
    icons: IconOption[];
    colors: ColorOption[];
    currencies: Currency[];
}>();

const { t } = useI18n();

const list = ref<Pocket[]>([]);

watch(
    () => props.pockets,
    (pockets) => {
        list.value = [...pockets];
    },
    { immediate: true },
);

const formOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingPocket = ref<Pocket | null>(null);

function openCreateDialog(): void {
    formMode.value = 'create';
    editingPocket.value = null;
    formOpen.value = true;
}

function openEditDialog(pocket: PocketRowPocket): void {
    formMode.value = 'edit';
    editingPocket.value = pocket as Pocket;
    formOpen.value = true;
}

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

function deletePocket(pocket: PocketRowPocket): void {
    if (!window.confirm(t('pockets.index.deleteConfirm', { name: pocket.name }))) {
        return;
    }

    router.delete(route('pockets.destroy', pocket.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <template #headerActions>
            <Button type="button" @click="openCreateDialog">
                <Plus class="size-4" :stroke-width="1.7" aria-hidden="true" />
                {{ t('pockets.index.add') }}
            </Button>
        </template>

        <div class="overflow-hidden rounded-2xl border border-transparent bg-card">
            <p v-if="list.length === 0" class="px-3 py-4 text-sm text-muted-foreground">
                {{ t('pockets.index.empty') }}
            </p>

            <VueDraggable
                v-else
                v-model="list"
                tag="ul"
                handle=".drag-handle"
                filter=".no-drag"
                :animation="150"
                ghost-class="opacity-50"
                chosen-class="bg-muted/30"
                @end="onReorderEnd"
            >
                <PocketRow v-for="pocket in list" :key="pocket.id" :pocket="pocket" @edit="openEditDialog" @delete="deletePocket" />
            </VueDraggable>
        </div>

        <PocketFormDialog v-model:open="formOpen" :mode="formMode" :pocket="editingPocket" :icons="icons" :colors="colors" :currencies="currencies" />
    </AppLayout>
</template>
