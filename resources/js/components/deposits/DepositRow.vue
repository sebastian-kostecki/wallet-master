<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Landmark, Pencil } from 'lucide-vue-next';
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
    <div class="list-row-hover px-3 py-2.5 hover:bg-foreground/[0.03]" :class="isActive ? '' : 'opacity-75'">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground" aria-hidden="true">
                <Landmark class="h-4 w-4" stroke-width="1.7" />
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="truncate text-sm font-medium">{{ displayName }}</p>
                    <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide" :class="statusClass">
                        {{ t(`deposits.status.${deposit.status}`) }}
                    </span>
                </div>
                <p class="mt-1 truncate text-xs text-muted-foreground">
                    {{ t('deposits.card.opened') }}: {{ formatDate(deposit.opened_at) }}
                    <template v-if="deposit.matures_at"> · {{ t('deposits.card.matures') }}: {{ formatDate(deposit.matures_at) }} </template>
                    <template v-if="deposit.closed_at"> · {{ t('deposits.card.closed') }}: {{ formatDate(deposit.closed_at) }} </template>
                </p>
            </div>

            <div class="hidden text-right sm:block">
                <p class="text-xs text-muted-foreground">{{ t('deposits.card.principal') }}</p>
                <p class="mt-1 text-sm font-semibold tabular-nums">{{ formatMoney(deposit.principal) }} {{ currencySymbol }}</p>
            </div>

            <div v-if="isActive" class="ml-auto flex items-center gap-1">
                <TooltipProvider :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger>
                            <Button variant="ghost" size="icon" type="button" :aria-label="t('actions.edit')" @click="$emit('edit', deposit.id)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ t('actions.edit') }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <Button variant="outline" size="sm" @click="$emit('close', deposit.id)">{{ t('deposits.actions.close') }}</Button>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between gap-3 sm:hidden">
            <p class="text-xs text-muted-foreground">{{ t('deposits.card.principal') }}</p>
            <p class="text-sm font-semibold tabular-nums">{{ formatMoney(deposit.principal) }} {{ currencySymbol }}</p>
        </div>
    </div>
</template>
