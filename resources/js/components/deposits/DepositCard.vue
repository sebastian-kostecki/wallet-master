<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Landmark } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Currency = {
    symbol: string | null;
};

type Deposit = {
    id: number;
    name: string | null;
    principal: string;
    opened_at: string;
    matures_at: string | null;
    status: 'active' | 'closed';
    closed_at: string | null;
    has_interest: boolean;
    currency: Currency | null;
};

const props = defineProps<{
    deposit: Deposit;
    formatMoney: (value: string) => string;
    formatDate: (value: string | null) => string;
}>();

defineEmits<{
    edit: [depositId: number];
    close: [depositId: number];
}>();

const { t } = useI18n();

const isActive = computed(() => props.deposit.status === 'active');
const displayName = computed(() => props.deposit.name?.trim() || t('deposits.fallbackName'));
const currencySymbol = computed(() => props.deposit.currency?.symbol ?? t('currency.defaultSymbol'));

const statusClass = computed(() => {
    if (props.deposit.status === 'active') {
        return 'bg-emerald-500/10 text-emerald-700 border-emerald-500/20 dark:text-emerald-300';
    }

    return 'bg-muted text-muted-foreground border-border/50';
});
</script>

<template>
    <div class="rounded-2xl border border-transparent bg-card p-4" :class="isActive ? '' : 'opacity-75'">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground" aria-hidden="true">
                    <Landmark class="h-5 w-5" stroke-width="1.7" />
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-medium">{{ displayName }}</p>
                        <span
                            class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                            :class="statusClass"
                        >
                            {{ t(`deposits.status.${deposit.status}`) }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ t('deposits.card.opened') }}: {{ formatDate(deposit.opened_at) }}
                        <template v-if="deposit.matures_at"> · {{ t('deposits.card.matures') }}: {{ formatDate(deposit.matures_at) }} </template>
                        <template v-if="deposit.closed_at"> · {{ t('deposits.card.closed') }}: {{ formatDate(deposit.closed_at) }} </template>
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-end justify-between gap-4">
            <div>
                <p class="text-xs text-muted-foreground">{{ t('deposits.card.principal') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">{{ formatMoney(deposit.principal) }} {{ currencySymbol }}</p>
            </div>

            <div v-if="isActive" class="flex gap-2">
                <Button variant="secondary" @click="$emit('edit', deposit.id)">{{ t('actions.edit') }}</Button>
                <Button variant="outline" @click="$emit('close', deposit.id)">{{ t('deposits.actions.close') }}</Button>
            </div>
        </div>
    </div>
</template>
