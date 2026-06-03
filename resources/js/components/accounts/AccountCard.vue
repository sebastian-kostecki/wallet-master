<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Coins, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

type Currency = {
    symbol: string | null;
};

type Account = {
    id: number;
    name: string;
    current_balance: string;
    bank: string;
    bank_icon_url: string | null;
    type: string;
    type_label_key: string;
    currency: Currency;
};

defineProps<{
    account: Account;
    formatMoney: (value: string) => string;
    accountTypeIcon: unknown;
    deleteDisabled: boolean;
    adjustDisabled: boolean;
}>();

const { t } = useI18n();

defineEmits<{
    delete: [accountId: number];
    edit: [accountId: number];
    adjustBalance: [accountId: number];
}>();
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <div class="shrink-0">
                    <img
                        v-if="account.bank_icon_url"
                        :src="account.bank_icon_url"
                        :alt="account.bank"
                        class="h-10 w-10 rounded-lg object-contain"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="flex h-10 w-10 items-center justify-center rounded-lg"
                        :class="
                            account.bank === 'cash'
                                ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                : 'bg-muted text-muted-foreground'
                        "
                        aria-hidden="true"
                    >
                        <component :is="account.bank === 'cash' ? Coins : accountTypeIcon" class="h-5 w-5" />
                    </div>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-medium">{{ account.name }}</p>
                    </div>
                    <p class="mt-1 truncate text-xs text-muted-foreground">{{ t(account.type_label_key) }}</p>
                </div>
            </div>

            <TooltipProvider :delay-duration="0">
                <Tooltip>
                    <TooltipTrigger>
                        <span class="inline-flex">
                            <button
                                type="button"
                                class="focus:outline-hidden rounded-md p-2 text-muted-foreground hover:text-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                :disabled="deleteDisabled"
                                @click="$emit('delete', account.id)"
                                :aria-label="t('accounts.card.deleteAria')"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>{{ t('accounts.card.deleteAria') }}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>

        <div class="mt-4 flex items-end justify-between gap-4">
            <div>
                <p class="text-xs text-muted-foreground">{{ t('accounts.card.currentBalance') }}</p>
                <p class="mt-1 text-lg font-semibold tabular-nums">
                    {{ formatMoney(account.current_balance) }} {{ account.currency.symbol ?? t('currency.defaultSymbol') }}
                </p>
            </div>

            <div class="flex gap-2">
                <Button variant="secondary" @click="$emit('edit', account.id)">{{ t('actions.edit') }}</Button>
                <Button variant="outline" :disabled="adjustDisabled" @click="$emit('adjustBalance', account.id)">{{
                    t('actions.setBalance')
                }}</Button>
            </div>
        </div>
    </div>
</template>
