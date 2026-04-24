<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Coins, Pencil, Trash2 } from 'lucide-vue-next';
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
    type_label: string;
    currency: Currency;
};

defineProps<{
    account: Account;
    formatMoney: (value: string) => string;
    accountTypeIcon: unknown;
    deleteDisabled: boolean;
    adjustDisabled: boolean;
}>();

defineEmits<{
    delete: [accountId: number];
    edit: [accountId: number];
    adjustBalance: [accountId: number];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
        <div class="flex items-center gap-3">
            <div class="shrink-0">
                <img
                    v-if="account.bank_icon_url"
                    :src="account.bank_icon_url"
                    :alt="account.bank"
                    class="h-9 w-9 rounded-lg object-contain"
                    loading="lazy"
                />
                <component v-else :is="accountTypeIcon" class="h-9 w-9 text-muted-foreground" aria-hidden="true" />
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">{{ account.name }}</p>
                <p class="mt-1 truncate text-xs text-muted-foreground">{{ account.type_label }}</p>
            </div>

            <div class="hidden text-right sm:block">
                <p class="text-xs text-muted-foreground">{{ t('accounts.card.currentBalance') }}</p>
                <p class="mt-1 text-sm font-semibold tabular-nums">
                    {{ formatMoney(account.current_balance) }} {{ account.currency.symbol ?? t('currency.defaultSymbol') }}
                </p>
            </div>

            <div class="ml-auto flex items-center gap-1">
                <TooltipProvider :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                :aria-label="t('actions.edit')"
                                @click="$emit('edit', account.id)"
                            >
                                <Pencil class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ t('actions.edit') }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger>
                            <span class="inline-flex">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    type="button"
                                    :aria-label="t('actions.setBalance')"
                                    :disabled="adjustDisabled"
                                    @click="$emit('adjustBalance', account.id)"
                                >
                                    <Coins class="h-4 w-4" />
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ t('actions.setBalance') }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger>
                            <span class="inline-flex">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    type="button"
                                    :aria-label="t('accounts.card.deleteAria')"
                                    :disabled="deleteDisabled"
                                    @click="$emit('delete', account.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ t('accounts.card.deleteAria') }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between gap-3 sm:hidden">
            <p class="text-xs text-muted-foreground">{{ t('accounts.card.currentBalance') }}</p>
            <p class="text-sm font-semibold tabular-nums">
                {{ formatMoney(account.current_balance) }} {{ account.currency.symbol ?? t('currency.defaultSymbol') }}
            </p>
        </div>
    </div>
</template>
