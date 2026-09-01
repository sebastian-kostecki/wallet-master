<script setup lang="ts">
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import PocketProgressBar from '@/components/pockets/PocketProgressBar.vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/formatMoney';
import { GripVertical, Pencil, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

export type PocketRowCurrency = {
    code: string;
    symbol: string;
    precision: number;
};

export type PocketRowPocket = {
    id: number;
    name: string;
    icon: string;
    color: string;
    currency: PocketRowCurrency;
    target_amount: string | null;
    progress_percent: number | null;
    balance: string;
    is_completed: boolean;
    is_overdue: boolean;
};

defineProps<{
    pocket: PocketRowPocket;
}>();

defineEmits<{
    edit: [pocket: PocketRowPocket];
    delete: [pocket: PocketRowPocket];
}>();

const { t } = useI18n();
</script>

<template>
    <li
        class="list-row-hover flex flex-col gap-2 border-b border-border/50 px-3 py-2.5 last:border-b-0 hover:bg-foreground/[0.03] sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex min-w-0 flex-1 items-start gap-2">
            <button
                type="button"
                class="drag-handle shrink-0 cursor-grab touch-none rounded-lg p-1 text-muted-foreground hover:bg-foreground/[0.06] active:cursor-grabbing"
                :aria-label="t('pockets.index.dragHandle')"
            >
                <GripVertical class="size-4" :stroke-width="1.7" aria-hidden="true" />
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

        <div class="no-drag flex flex-wrap items-center gap-2 sm:justify-end">
            <span class="text-sm font-medium tabular-nums">{{ formatMoney(pocket.balance, pocket.currency) }}</span>

            <span
                v-if="pocket.is_completed"
                class="inline-flex items-center rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400"
            >
                {{ t('pockets.status.completed') }}
            </span>
            <span
                v-if="pocket.is_overdue"
                class="inline-flex items-center rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"
            >
                {{ t('pockets.status.overdue') }}
            </span>

            <Button variant="ghost" size="icon" type="button" :aria-label="t('actions.edit')" @click="$emit('edit', pocket)">
                <Pencil class="size-4 text-muted-foreground" :stroke-width="1.7" aria-hidden="true" />
            </Button>

            <Button variant="ghost" size="icon" type="button" :aria-label="t('pockets.index.delete')" @click="$emit('delete', pocket)">
                <Trash2 class="size-4 text-muted-foreground" :stroke-width="1.7" aria-hidden="true" />
            </Button>
        </div>
    </li>
</template>
