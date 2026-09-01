<script setup lang="ts">
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { ChevronDown, Landmark } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
};

type MatchingDeposit = {
    id: number;
    name: string | null;
    principal: string;
    status: 'active' | 'closed';
};

export type DepositCandidate = {
    transaction_id: number;
    kind: 'open' | 'close_principal';
    amount: string;
    date: string;
    description: string;
    account: Account;
    matching_deposits?: MatchingDeposit[];
};

const props = defineProps<{
    candidates: DepositCandidate[];
    accountFilterId?: number | null;
}>();

const { t } = useI18n();

const bannerTitleId = 'deposit-candidates-banner-title';
const bannerContentId = 'deposit-candidates-banner-content';

const expanded = ref(false);
const isSubmitting = ref(false);
const selectedDepositByTransactionId = ref<Record<number, number | null>>({});

const accountsById = computed(() => new Map(props.candidates.map((candidate) => [candidate.account.id, candidate.account.name])));

const groupedCandidates = computed(() => {
    if (props.accountFilterId !== null && props.accountFilterId !== undefined) {
        return [{ accountId: props.accountFilterId, candidates: props.candidates }];
    }

    const groups = new Map<number, DepositCandidate[]>();

    for (const candidate of props.candidates) {
        const accountId = candidate.account.id;
        const existing = groups.get(accountId) ?? [];
        existing.push(candidate);
        groups.set(accountId, existing);
    }

    return Array.from(groups.entries()).map(([accountId, candidates]) => ({
        accountId,
        candidates,
    }));
});

watch(
    () => props.candidates,
    (candidates) => {
        for (const candidate of candidates) {
            if (candidate.kind !== 'close_principal') {
                continue;
            }

            const matchingDeposits = candidate.matching_deposits ?? [];
            if (matchingDeposits.length === 0) {
                continue;
            }

            if (selectedDepositByTransactionId.value[candidate.transaction_id] === undefined) {
                selectedDepositByTransactionId.value[candidate.transaction_id] = matchingDeposits[0]?.id ?? null;
            }
        }
    },
    { immediate: true, deep: true },
);

function accountLabel(accountId: number): string {
    return accountsById.value.get(accountId) ?? `#${accountId}`;
}

function kindLabel(kind: DepositCandidate['kind']): string {
    return kind === 'open' ? t('deposits.candidates.kindOpen') : t('deposits.candidates.kindClose');
}

function depositOptions(candidate: DepositCandidate): DropdownOption<number>[] {
    return (candidate.matching_deposits ?? []).map((deposit) => ({
        value: deposit.id,
        label: deposit.name?.trim() || t('deposits.fallbackName'),
    }));
}

function canConfirmClose(candidate: DepositCandidate): boolean {
    if (candidate.kind !== 'close_principal') {
        return true;
    }

    return selectedDepositByTransactionId.value[candidate.transaction_id] !== null;
}

function confirmCandidate(candidate: DepositCandidate) {
    if (isSubmitting.value || !canConfirmClose(candidate)) {
        return;
    }

    isSubmitting.value = true;

    const payload =
        candidate.kind === 'close_principal'
            ? {
                  deposit_id: selectedDepositByTransactionId.value[candidate.transaction_id],
              }
            : {};

    router.post(route('deposits.candidates.confirm', candidate.transaction_id as any), payload, {
        preserveScroll: true,
        only: ['pending_deposit_candidates'],
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}

function rejectCandidate(transactionId: number) {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    router.post(
        route('deposits.candidates.reject', transactionId as any),
        {},
        {
            preserveScroll: true,
            only: ['pending_deposit_candidates'],
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}
</script>

<template>
    <div
        role="region"
        :aria-labelledby="bannerTitleId"
        class="rounded-lg border border-violet-500/40 bg-violet-50/40 dark:border-violet-500/30 dark:bg-violet-950/20"
    >
        <button
            type="button"
            class="flex w-full items-start gap-3 p-4 text-left"
            :aria-expanded="expanded"
            :aria-controls="bannerContentId"
            :aria-label="t('deposits.candidates.a11y.toggle', { count: candidates.length })"
            @click="expanded = !expanded"
        >
            <Landmark class="mt-0.5 h-5 w-5 shrink-0 text-violet-600 dark:text-violet-400" aria-hidden="true" stroke-width="1.7" />
            <div class="min-w-0 flex-1">
                <p :id="bannerTitleId" class="text-sm font-medium text-foreground">
                    {{ t('deposits.candidates.bannerTitle', { count: candidates.length }) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ t('deposits.candidates.subtitle') }}
                </p>
            </div>
            <ChevronDown
                class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground transition-transform"
                :class="cn(expanded && 'rotate-180')"
                aria-hidden="true"
            />
        </button>

        <div v-if="expanded" :id="bannerContentId" class="border-t border-violet-500/20 px-4 pb-4 pt-3">
            <div class="grid gap-4">
                <div v-for="group in groupedCandidates" :key="group.accountId" class="grid gap-2">
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
                                    <th class="px-3 py-2 text-left font-medium">{{ t('deposits.candidates.table.kind') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('deposits.candidates.table.description') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('deposits.candidates.table.amount') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('deposits.candidates.table.date') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('deposits.candidates.table.deposit') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ t('deposits.candidates.table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="candidate in group.candidates"
                                    :key="candidate.transaction_id"
                                    class="border-t border-sidebar-border/50 dark:border-sidebar-border"
                                >
                                    <td class="whitespace-nowrap px-3 py-2 text-xs font-medium">
                                        {{ kindLabel(candidate.kind) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <p class="max-w-xs truncate font-medium" :title="candidate.description">
                                            {{ candidate.description }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">{{ candidate.account.name }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ candidate.amount }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs text-muted-foreground">{{ candidate.date }}</td>
                                    <td class="px-3 py-2">
                                        <div v-if="candidate.kind === 'close_principal'" class="min-w-44">
                                            <DropdownSelect
                                                :id="`deposit-candidate-${candidate.transaction_id}`"
                                                :model-value="selectedDepositByTransactionId[candidate.transaction_id] ?? null"
                                                :options="depositOptions(candidate)"
                                                :placeholder="t('deposits.candidates.depositSelect.placeholder')"
                                                :disabled="isSubmitting"
                                                :aria-label="t('deposits.candidates.depositSelect.label')"
                                                @update:model-value="
                                                    (value: number | null) => (selectedDepositByTransactionId[candidate.transaction_id] = value)
                                                "
                                            />
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <Button
                                                variant="default"
                                                size="sm"
                                                type="button"
                                                :disabled="isSubmitting || !canConfirmClose(candidate)"
                                                :aria-label="
                                                    t('deposits.candidates.a11y.confirm', {
                                                        description: candidate.description,
                                                    })
                                                "
                                                @click="confirmCandidate(candidate)"
                                            >
                                                {{ t('deposits.candidates.confirm') }}
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                type="button"
                                                :disabled="isSubmitting"
                                                :aria-label="
                                                    t('deposits.candidates.a11y.reject', {
                                                        description: candidate.description,
                                                    })
                                                "
                                                @click="rejectCandidate(candidate.transaction_id)"
                                            >
                                                {{ t('deposits.candidates.reject') }}
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
