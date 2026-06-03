<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { ChevronDown, ShieldAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
};

export type ImportFailedRow = {
    id: number;
    import_id: number;
    account_id: number;
    row_number: number;
    reason_code: string;
    reason_label_key: string;
    date_raw: string | null;
    amount_raw: string | null;
    description_raw: string | null;
    subject_raw: string | null;
};

const props = defineProps<{
    rows: ImportFailedRow[];
    accounts: Account[];
    accountFilterId?: number | null;
}>();

const { t } = useI18n();

const expanded = ref(false);
const isDismissing = ref(false);

const accountsById = computed(() => new Map(props.accounts.map((account) => [account.id, account.name])));

const groupedRows = computed(() => {
    if (props.accountFilterId !== null && props.accountFilterId !== undefined) {
        return [{ accountId: props.accountFilterId, rows: props.rows }];
    }

    const groups = new Map<number, ImportFailedRow[]>();

    for (const row of props.rows) {
        const existing = groups.get(row.account_id) ?? [];
        existing.push(row);
        groups.set(row.account_id, existing);
    }

    return Array.from(groups.entries()).map(([accountId, rows]) => ({
        accountId,
        rows,
    }));
});

function accountLabel(accountId: number): string {
    return accountsById.value.get(accountId) ?? `#${accountId}`;
}

function dismissRow(id: number) {
    if (isDismissing.value) {
        return;
    }

    isDismissing.value = true;

    router.post(
        route('import-failed-rows.dismiss', id as any),
        {},
        {
            preserveScroll: true,
            only: ['unresolved_import_failed_rows'],
            onFinish: () => {
                isDismissing.value = false;
            },
        },
    );
}

function dismissAll() {
    if (isDismissing.value) {
        return;
    }

    isDismissing.value = true;

    const data =
        props.accountFilterId !== null && props.accountFilterId !== undefined
            ? { account_id: props.accountFilterId }
            : {};

    router.post(route('import-failed-rows.dismiss-all'), data, {
        preserveScroll: true,
        only: ['unresolved_import_failed_rows'],
        onFinish: () => {
            isDismissing.value = false;
        },
    });
}

function displayValue(value: string | null): string {
    return value && value.trim() !== '' ? value : '—';
}
</script>

<template>
    <div class="rounded-lg border border-amber-500/40 bg-amber-50/40 dark:border-amber-500/30 dark:bg-amber-950/20">
        <button
            type="button"
            class="flex w-full items-start gap-3 p-4 text-left"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            <ShieldAlert class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-foreground">
                    {{ t('imports.failed_rows.banner.title', { count: rows.length }) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ t('imports.failed_rows.banner.subtitle') }}
                </p>
            </div>
            <ChevronDown
                class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground transition-transform"
                :class="cn(expanded && 'rotate-180')"
                aria-hidden="true"
            />
        </button>

        <div v-if="expanded" class="border-t border-amber-500/20 px-4 pb-4 pt-3">
            <div class="mb-3 flex justify-end">
                <Button variant="secondary" size="sm" type="button" :disabled="isDismissing" @click="dismissAll">
                    {{ t('imports.failed_rows.actions.dismiss_all') }}
                </Button>
            </div>

            <div class="grid gap-4">
                <div v-for="group in groupedRows" :key="group.accountId" class="grid gap-2">
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
                                    <th class="px-3 py-2 text-left font-medium">{{ t('imports.failed_rows.table.row') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('imports.failed_rows.table.date') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('imports.failed_rows.table.amount') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('imports.failed_rows.table.description') }}</th>
                                    <th class="px-3 py-2 text-left font-medium">{{ t('imports.failed_rows.table.reason') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ t('imports.failed_rows.table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in group.rows"
                                    :key="row.id"
                                    class="border-t border-sidebar-border/50 dark:border-sidebar-border"
                                >
                                    <td class="px-3 py-2 tabular-nums">{{ row.row_number }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ displayValue(row.date_raw) }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap tabular-nums">{{ displayValue(row.amount_raw) }}</td>
                                    <td class="max-w-xs truncate px-3 py-2" :title="row.description_raw ?? undefined">
                                        {{ displayValue(row.description_raw) }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-muted-foreground">{{ t(row.reason_label_key) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <Button variant="ghost" size="sm" type="button" :disabled="isDismissing" @click="dismissRow(row.id)">
                                            {{ t('imports.failed_rows.actions.dismiss') }}
                                        </Button>
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
