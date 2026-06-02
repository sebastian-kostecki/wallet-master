<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { ArrowRightLeft, ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
};

type CandidateTransaction = {
    id: number;
    date: string;
    booked_at: string;
    amount: string;
    description: string;
};

export type TransferCandidatePair = {
    anchor_transaction_id: number;
    amount: string;
    date_delta_days: number;
    from_account: Account;
    to_account: Account;
    from_transaction: CandidateTransaction;
    to_transaction: CandidateTransaction;
};

const props = defineProps<{
    pairs: TransferCandidatePair[];
    accounts: Account[];
    accountFilterId?: number | null;
}>();

const { t } = useI18n();

const expanded = ref(false);
const isSubmitting = ref(false);

const accountsById = computed(() => new Map(props.accounts.map((account) => [account.id, account.name])));

const groupedPairs = computed(() => {
    if (props.accountFilterId !== null && props.accountFilterId !== undefined) {
        return [{ accountId: props.accountFilterId, pairs: props.pairs }];
    }

    const groups = new Map<number, TransferCandidatePair[]>();

    for (const pair of props.pairs) {
        const accountId = pair.from_account.id;
        const existing = groups.get(accountId) ?? [];
        existing.push(pair);
        groups.set(accountId, existing);
    }

    return Array.from(groups.entries()).map(([accountId, pairs]) => ({
        accountId,
        pairs,
    }));
});

function accountLabel(accountId: number): string {
    return accountsById.value.get(accountId) ?? `#${accountId}`;
}

function confirmPair(anchorTransactionId: number) {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    router.post(
        route('transfers.candidates.confirm', anchorTransactionId as any),
        {},
        {
            preserveScroll: true,
            only: ['pending_transfer_candidates'],
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function rejectPair(anchorTransactionId: number) {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    router.post(
        route('transfers.candidates.reject', anchorTransactionId as any),
        {},
        {
            preserveScroll: true,
            only: ['pending_transfer_candidates'],
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="rounded-lg border border-sky-500/40 bg-sky-50/40 dark:border-sky-500/30 dark:bg-sky-950/20">
        <button
            type="button"
            class="flex w-full items-start gap-3 p-4 text-left"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            <ArrowRightLeft class="mt-0.5 h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-foreground">
                    {{ t('transfers.candidates.banner.title', { count: pairs.length }) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ t('transfers.candidates.banner.subtitle') }}
                </p>
            </div>
            <ChevronDown
                class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground transition-transform"
                :class="cn(expanded && 'rotate-180')"
                aria-hidden="true"
            />
        </button>

        <div v-if="expanded" class="border-t border-sky-500/20 px-4 pb-4 pt-3">
            <div class="grid gap-4">
                <div v-for="group in groupedPairs" :key="group.accountId" class="grid gap-2">
                    <p
                        v-if="accountFilterId === null || accountFilterId === undefined"
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        {{ accountLabel(group.accountId) }}
                    </p>

                    <div class="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('transfers.candidates.table.from') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('transfers.candidates.table.to') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('transfers.candidates.table.amount') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('transfers.candidates.table.dates') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ t('transfers.candidates.table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="pair in group.pairs"
                                    :key="pair.anchor_transaction_id"
                                    class="border-t border-sidebar-border/50 dark:border-sidebar-border"
                                >
                                    <td class="px-3 py-2">
                                        <p class="font-medium">{{ pair.from_account.name }}</p>
                                        <p class="max-w-xs truncate text-xs text-muted-foreground" :title="pair.from_transaction.description">
                                            {{ pair.from_transaction.description }}
                                        </p>
                                        <p class="text-xs tabular-nums text-muted-foreground">{{ pair.from_transaction.date }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <p class="font-medium">{{ pair.to_account.name }}</p>
                                        <p class="max-w-xs truncate text-xs text-muted-foreground" :title="pair.to_transaction.description">
                                            {{ pair.to_transaction.description }}
                                        </p>
                                        <p class="text-xs tabular-nums text-muted-foreground">{{ pair.to_transaction.date }}</p>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap tabular-nums">{{ pair.amount }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs text-muted-foreground">
                                        {{
                                            t('transfers.candidates.table.dateDelta', {
                                                days: pair.date_delta_days,
                                            })
                                        }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <Button
                                                variant="default"
                                                size="sm"
                                                type="button"
                                                :disabled="isSubmitting"
                                                @click="confirmPair(pair.anchor_transaction_id)"
                                            >
                                                {{ t('transfers.candidates.actions.confirm') }}
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                type="button"
                                                :disabled="isSubmitting"
                                                @click="rejectPair(pair.anchor_transaction_id)"
                                            >
                                                {{ t('transfers.candidates.actions.reject') }}
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
