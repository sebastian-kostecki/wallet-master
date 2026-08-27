<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ArrowDownLeft, ArrowRightLeft, ArrowUpRight, Pencil, PiggyBank, Scale, Trash2, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
    type: string;
    type_label_key: string;
    bank_icon_url: string | null;
    bank?: string;
    currency?: {
        symbol: string | null;
    } | null;
};

type TransactionCategory = {
    id: number;
    name: string;
    type: string;
    type_label_key: string;
    icon: string;
    color: string;
};

type Transaction = {
    id: number;
    account_id: number;
    date: string;
    booked_at: string;
    date_relative: string;
    amount: string | number;
    type: 'income' | 'expense' | string;
    description: string;
    subject: string | null;
    raw_statement_description: string | null;
    transfer_id: string | null;
    category_id: number | null;
    category: TransactionCategory | null;
    pocket_id: number | null;
    pocket_movement: 'deposit' | 'withdrawal' | null;
    pocket: { id: number; name: string } | null;
    account: Account | null;
    currency: { id: number; code: string; symbol: string | null; precision: number } | null;
};

const props = defineProps<{
    transaction: Transaction;
    variant: 'desktop' | 'mobile';
    deleteProcessing: boolean;
    formatAmount: (value: string | number) => string;
    formatCurrencySymbol: (account: Transaction['account']) => string;
}>();

defineEmits<{
    edit: [transaction: Transaction];
    delete: [transactionId: number];
}>();

const { t, locale } = useI18n();

const tx = computed(() => props.transaction);

function toNumber(value: string | number): number {
    if (typeof value === 'number') {
        return value;
    }

    const parsed = Number(value);
    return Number.isNaN(parsed) ? 0 : parsed;
}

function normalizeDateIso(input: string): string {
    const trimmed = input.trim();
    const match = trimmed.match(/^(\d{4}-\d{2}-\d{2})/);

    return match?.[1] ?? trimmed;
}

function formatDateIsoToDots(input: string): string {
    const iso = normalizeDateIso(input);
    const parts = iso.split('-');
    if (parts.length !== 3) {
        return input;
    }

    const [yyyy, mm, dd] = parts;
    if (!yyyy || !mm || !dd) {
        return input;
    }

    return `${dd}.${mm}.${yyyy}`;
}

function transactionDisplayDateIso(row: Transaction): string {
    const booked = normalizeDateIso(row.booked_at ?? '');
    if (booked !== '') {
        return booked;
    }

    return normalizeDateIso(row.date);
}

function operationDateRelative(dateIso: string): string {
    const iso = normalizeDateIso(dateIso);
    const parts = iso.split('-');
    if (parts.length !== 3) {
        return '';
    }

    const y = Number(parts[0]);
    const m = Number(parts[1]);
    const d = Number(parts[2]);
    if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) {
        return '';
    }

    const target = new Date(y, m - 1, d);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    target.setHours(0, 0, 0, 0);
    const diffDays = Math.round((target.getTime() - today.getTime()) / 86400000);

    const lng = locale.value === 'pl' ? 'pl' : 'en';
    const rtf = new Intl.RelativeTimeFormat(lng, { numeric: 'auto' });

    return rtf.format(diffDays, 'day');
}

function hasRawStatementDescription(row: Transaction): boolean {
    return (row.raw_statement_description ?? '').trim() !== '';
}

function truncateText(input: string | null | undefined, maxLength: number): { text: string; isTruncated: boolean } {
    const value = (input ?? '').trim();
    if (value === '') {
        return { text: '—', isTruncated: false };
    }

    if (value.length <= maxLength) {
        return { text: value, isTruncated: false };
    }

    const slice = value.slice(0, Math.max(0, maxLength));
    const lastSpace = slice.lastIndexOf(' ');
    const trimmed = (lastSpace >= Math.floor(maxLength * 0.6) ? slice.slice(0, lastSpace) : slice).trimEnd();

    return { text: `${trimmed}…`, isTruncated: true };
}

type TransactionIconVariant = 'internal' | 'expense' | 'income' | 'adjustment';

function transactionVariant(row: Transaction): TransactionIconVariant {
    if (row.type === 'adjustment') {
        return 'adjustment';
    }

    if (row.transfer_id) {
        return 'internal';
    }

    return row.type === 'expense' ? 'expense' : 'income';
}

function transactionIcon(row: Transaction) {
    const variant = transactionVariant(row);

    if (variant === 'internal') {
        return {
            component: ArrowRightLeft,
            containerClass: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        } as const;
    }

    if (variant === 'adjustment') {
        return {
            component: Scale,
            containerClass: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        } as const;
    }

    if (variant === 'expense') {
        return {
            component: ArrowDownLeft,
            containerClass: 'bg-red-500/10 text-red-600 dark:text-red-400',
        } as const;
    }

    return {
        component: ArrowUpRight,
        containerClass: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    } as const;
}

const adjustmentBadgeClass =
    'inline-flex items-center rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-600 dark:text-amber-400';

function amountClass(row: Transaction): string {
    return toNumber(row.amount) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
}

function resolveAccountTypeIcon(type: string) {
    return type === 'savings' ? PiggyBank : Wallet;
}

const displayDateIso = computed(() => transactionDisplayDateIso(tx.value));
</script>

<template>
    <tr v-if="variant === 'desktop'" class="list-row-hover border-b border-border/50 last:border-b-0">
        <td class="whitespace-nowrap px-3 py-2.5 tabular-nums">
            <div class="text-sm font-medium text-foreground">
                {{ formatDateIsoToDots(displayDateIso) }}
            </div>
            <div class="mt-0.5 text-xs text-muted-foreground">
                {{ tx.date_relative || operationDateRelative(displayDateIso) }}
            </div>
        </td>
        <td class="px-3 py-2.5">
            <TooltipProvider v-if="hasRawStatementDescription(tx)" :delay-duration="0">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <div
                            class="flex min-w-0 cursor-pointer items-center gap-3"
                            :aria-label="t('transactions.index.a11y.showStatementDescription')"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                :class="transactionIcon(tx).containerClass"
                                aria-hidden="true"
                            >
                                <component :is="transactionIcon(tx).component" class="h-4 w-4" />
                            </div>

                            <div class="w-full min-w-0">
                                <div v-if="tx.type === 'adjustment'" class="mb-1">
                                    <span :class="adjustmentBadgeClass">
                                        {{ t('transactions.index.badges.adjustment') }}
                                    </span>
                                </div>
                                <p class="truncate text-sm font-medium text-foreground">
                                    {{ truncateText(tx.subject, 80).text }}
                                </p>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                    {{ truncateText(tx.description, 120).text }}
                                </p>
                            </div>
                        </div>
                    </TooltipTrigger>
                    <TooltipContent>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs font-medium text-muted-foreground">
                                    {{ t('transactions.edit.statement.title') }}
                                </p>
                                <p class="mt-1 max-w-md whitespace-pre-wrap break-words">
                                    {{ tx.raw_statement_description }}
                                </p>
                            </div>
                            <div v-if="truncateText(tx.subject, 80).isTruncated && (tx.subject ?? '').trim() !== ''">
                                <p class="text-xs font-medium text-muted-foreground">
                                    {{ t('transactions.index.table.subject') }}
                                </p>
                                <p class="mt-1 max-w-md break-words">{{ tx.subject }}</p>
                            </div>
                            <div v-if="truncateText(tx.description, 120).isTruncated">
                                <p class="text-xs font-medium text-muted-foreground">
                                    {{ t('transactions.index.table.description') }}
                                </p>
                                <p class="mt-1 max-w-md break-words">{{ tx.description }}</p>
                            </div>
                        </div>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>

            <div v-else class="flex min-w-0 items-center gap-3">
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                    :class="transactionIcon(tx).containerClass"
                    aria-hidden="true"
                >
                    <component :is="transactionIcon(tx).component" class="h-4 w-4" />
                </div>

                <div class="w-full min-w-0">
                    <div v-if="tx.type === 'adjustment'" class="mb-1">
                        <span :class="adjustmentBadgeClass">
                            {{ t('transactions.index.badges.adjustment') }}
                        </span>
                    </div>
                    <TooltipProvider :delay-duration="0">
                        <Tooltip v-if="truncateText(tx.subject, 80).isTruncated">
                            <TooltipTrigger as-child>
                                <p class="truncate text-sm font-medium text-foreground" :title="tx.subject ?? undefined">
                                    {{ truncateText(tx.subject, 80).text }}
                                </p>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p class="max-w-sm break-words">{{ tx.subject }}</p>
                            </TooltipContent>
                        </Tooltip>
                        <p v-else class="truncate text-sm font-medium text-foreground" :title="tx.subject ?? undefined">
                            {{ truncateText(tx.subject, 80).text }}
                        </p>
                    </TooltipProvider>

                    <TooltipProvider :delay-duration="0">
                        <Tooltip v-if="truncateText(tx.description, 120).isTruncated">
                            <TooltipTrigger as-child>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground" :title="tx.description">
                                    {{ truncateText(tx.description, 120).text }}
                                </p>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p class="max-w-sm break-words">{{ tx.description }}</p>
                            </TooltipContent>
                        </Tooltip>
                        <p v-else class="mt-0.5 truncate text-xs text-muted-foreground" :title="tx.description">
                            {{ truncateText(tx.description, 120).text }}
                        </p>
                    </TooltipProvider>
                </div>
            </div>
        </td>
        <td class="overflow-hidden px-3 py-2.5">
            <div v-if="tx.transfer_id" class="flex flex-col gap-0.5">
                <span class="text-sm text-muted-foreground">
                    {{ t('transactions.index.table.transfer') }}
                </span>
                <span v-if="tx.pocket" class="text-xs text-muted-foreground">
                    {{ tx.pocket.name }}
                </span>
            </div>
            <div v-else-if="tx.category" class="min-w-0 max-w-full">
                <CategoryBadge :name="tx.category.name" :icon="tx.category.icon" :color="tx.category.color" size="md" />
            </div>
            <span v-else class="text-sm text-muted-foreground">—</span>
        </td>
        <td class="overflow-hidden px-3 py-2.5">
            <div class="flex min-w-0 items-center gap-2">
                <div class="shrink-0">
                    <img
                        v-if="tx.account?.bank_icon_url"
                        :src="tx.account.bank_icon_url"
                        :alt="tx.account.name"
                        class="h-9 w-9 rounded-lg object-contain"
                        loading="lazy"
                    />
                    <component
                        v-else-if="tx.account"
                        :is="resolveAccountTypeIcon(tx.account.type)"
                        class="h-9 w-9 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div v-else class="h-9 w-9 rounded-lg bg-muted" aria-hidden="true" />
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-medium">
                            {{ tx.account?.name ?? t('transactions.index.readOnly.deletedAccount') }}
                        </p>
                        <span v-if="!tx.account" class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                            {{ t('transactions.index.readOnly.badge') }}
                        </span>
                    </div>
                    <p v-if="tx.account" class="mt-0.5 truncate text-xs text-muted-foreground">
                        {{ t(tx.account.type_label_key) }}
                    </p>
                </div>
            </div>
        </td>
        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">
            <span :class="['font-medium', amountClass(tx)]">
                {{ toNumber(tx.amount) >= 0 ? '+' : '-' }}{{ formatAmount(Math.abs(toNumber(tx.amount))) }}
                {{ formatCurrencySymbol(tx.account) }}
            </span>
        </td>
        <td class="px-3 py-2.5 text-right">
            <TooltipProvider v-if="tx.account" :delay-duration="0">
                <div class="inline-flex w-full items-center justify-end gap-1">
                    <Tooltip>
                        <TooltipTrigger>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                :aria-label="t('transactions.index.a11y.edit', { description: tx.description })"
                                @click="$emit('edit', tx)"
                            >
                                <Pencil class="h-4 w-4" aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ t('actions.edit') }}</p>
                        </TooltipContent>
                    </Tooltip>

                    <Tooltip>
                        <TooltipTrigger>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                :disabled="deleteProcessing"
                                :aria-label="t('transactions.delete.iconLabel', { description: tx.description })"
                                @click="$emit('delete', tx.id)"
                            >
                                <Trash2 class="h-4 w-4" aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ t('transactions.delete.action') }}</p>
                        </TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
            <span v-else class="text-xs text-muted-foreground">{{ t('transactions.index.readOnly.noActions') }}</span>
        </td>
    </tr>
    <div v-else class="list-row-hover border-b border-border/50 px-3 py-2.5 last:border-b-0">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div v-if="tx.type === 'adjustment'" class="mb-1">
                    <span :class="adjustmentBadgeClass">
                        {{ t('transactions.index.badges.adjustment') }}
                    </span>
                </div>

                <TooltipProvider v-if="hasRawStatementDescription(tx)" :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <div class="cursor-pointer" :aria-label="t('transactions.index.a11y.showStatementDescription')">
                                <p class="text-sm font-medium">
                                    {{ truncateText(tx.description, 90).text }}
                                </p>
                                <p v-if="(tx.subject ?? '').trim() !== ''" class="mt-1 text-xs text-muted-foreground">
                                    {{ truncateText(tx.subject, 70).text }}
                                </p>
                            </div>
                        </TooltipTrigger>
                        <TooltipContent>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-xs font-medium text-muted-foreground">
                                        {{ t('transactions.edit.statement.title') }}
                                    </p>
                                    <p class="mt-1 max-w-md whitespace-pre-wrap break-words">
                                        {{ tx.raw_statement_description }}
                                    </p>
                                </div>
                                <div v-if="truncateText(tx.subject, 70).isTruncated && (tx.subject ?? '').trim() !== ''">
                                    <p class="text-xs font-medium text-muted-foreground">
                                        {{ t('transactions.index.table.subject') }}
                                    </p>
                                    <p class="mt-1 max-w-md break-words">{{ tx.subject }}</p>
                                </div>
                                <div v-if="truncateText(tx.description, 90).isTruncated">
                                    <p class="text-xs font-medium text-muted-foreground">
                                        {{ t('transactions.index.table.description') }}
                                    </p>
                                    <p class="mt-1 max-w-md break-words">{{ tx.description }}</p>
                                </div>
                            </div>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <template v-else>
                    <TooltipProvider :delay-duration="0">
                        <Tooltip v-if="truncateText(tx.description, 90).isTruncated">
                            <TooltipTrigger as-child>
                                <p class="text-sm font-medium" :title="tx.description">
                                    {{ truncateText(tx.description, 90).text }}
                                </p>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p class="max-w-sm break-words">{{ tx.description }}</p>
                            </TooltipContent>
                        </Tooltip>
                        <p v-else class="text-sm font-medium" :title="tx.description">
                            {{ truncateText(tx.description, 90).text }}
                        </p>
                    </TooltipProvider>
                </template>

                <p class="mt-1 text-xs tabular-nums text-muted-foreground">
                    {{ formatDateIsoToDots(displayDateIso) }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ tx.date_relative || operationDateRelative(displayDateIso) }}
                </p>
                <div v-if="tx.category" class="mt-1">
                    <CategoryBadge :name="tx.category.name" :icon="tx.category.icon" :color="tx.category.color" size="sm" />
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ tx.account?.name ?? t('transactions.index.readOnly.deletedAccount') }}
                    <span v-if="!tx.account" class="ml-2 rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">
                        {{ t('transactions.index.readOnly.badge') }}
                    </span>
                </p>

                <TooltipProvider v-if="!hasRawStatementDescription(tx) && tx.subject" :delay-duration="0">
                    <Tooltip v-if="truncateText(tx.subject, 70).isTruncated">
                        <TooltipTrigger as-child>
                            <p class="mt-1 text-xs text-muted-foreground" :title="tx.subject ?? undefined">
                                {{ truncateText(tx.subject, 70).text }}
                            </p>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p class="max-w-sm break-words">{{ tx.subject }}</p>
                        </TooltipContent>
                    </Tooltip>
                    <p v-else class="mt-1 text-xs text-muted-foreground" :title="tx.subject ?? undefined">
                        {{ truncateText(tx.subject, 70).text }}
                    </p>
                </TooltipProvider>
            </div>
            <div class="shrink-0 text-right">
                <p class="text-sm font-semibold tabular-nums" :class="amountClass(tx)">
                    {{ toNumber(tx.amount) >= 0 ? '+' : '-' }}{{ formatAmount(Math.abs(toNumber(tx.amount))) }}
                    {{ formatCurrencySymbol(tx.account) }}
                </p>
                <div class="mt-2">
                    <TooltipProvider v-if="tx.account" :delay-duration="0">
                        <div class="inline-flex items-center gap-1">
                            <Tooltip>
                                <TooltipTrigger>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        type="button"
                                        :aria-label="t('transactions.index.a11y.edit', { description: tx.description })"
                                        @click="$emit('edit', tx)"
                                    >
                                        <Pencil class="h-4 w-4" aria-hidden="true" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{{ t('actions.edit') }}</p>
                                </TooltipContent>
                            </Tooltip>

                            <Tooltip>
                                <TooltipTrigger>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        type="button"
                                        :disabled="deleteProcessing"
                                        :aria-label="t('transactions.delete.iconLabel', { description: tx.description })"
                                        @click="$emit('delete', tx.id)"
                                    >
                                        <Trash2 class="h-4 w-4" aria-hidden="true" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{{ t('transactions.delete.action') }}</p>
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </TooltipProvider>
                </div>
            </div>
        </div>
    </div>
</template>
