<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { apiFetch } from '@/lib/apiFetch';
import { cn } from '@/lib/utils';
import { router, usePage } from '@inertiajs/vue3';
import { echo } from '@laravel/echo-vue';
import { CheckCircle2, ChevronDown, Coins, Loader2, ShieldAlert, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { ImportFailedRow } from '@/components/import/ImportFailedRowsBanner.vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
    bank: string;
    bank_icon_url: string | null;
};

type ImportStatus = 'draft' | 'queued' | 'processing' | 'committed' | 'failed' | string;

type ImportState = {
    id: number;
    status: ImportStatus;
    rows_total: number | null;
    rows_imported: number | null;
    rows_skipped_duplicate: number | null;
    rows_failed_validation: number | null;
    error_summary: string | null;
    committed_at: string | null;
    failed_rows?: ImportFailedRow[];
    failed_rows_total?: number;
};

const props = withDefaults(
    defineProps<{
        open: boolean;
        accounts: Account[];
        preselectedAccountId?: number | null;
        disabled?: boolean;
        currentSearch?: string;
    }>(),
    {
        preselectedAccountId: null,
        disabled: false,
        currentSearch: '',
    },
);

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();

type Step = 'form' | 'processing' | 'result';
const step = ref<Step>('form');

const accountOptions = computed<DropdownOption<number>[]>(() =>
    props.accounts.map((a) => ({
        value: a.id,
        label: a.name,
        disabled: a.bank === 'cash',
    })),
);

const accountsById = computed(() => new Map(props.accounts.map((a) => [a.id, a])));

const selectedAccountId = ref<number | null>(props.preselectedAccountId ?? null);
const selectedAccount = computed(() => (selectedAccountId.value ? (accountsById.value.get(selectedAccountId.value) ?? null) : null));
const selectedBankLabel = computed(() => (selectedAccount.value ? t(`accounts.enums.bank.${selectedAccount.value.bank}`) : '—'));

const file = ref<File | null>(null);
const fileError = ref<string>('');
const accountError = ref<string>('');
const isDragging = ref(false);

const importId = ref<number | null>(null);
const importState = ref<ImportState | null>(null);
const processingHint = computed(() => t('imports.dialog.processing.hint'));

const isUploadingOrCommitting = ref(false);
const canStart = computed(() => {
    return (
        !props.disabled &&
        !isUploadingOrCommitting.value &&
        selectedAccountId.value !== null &&
        selectedAccount.value?.bank !== 'cash' &&
        file.value !== null &&
        fileError.value.trim() === '' &&
        accountError.value.trim() === ''
    );
});

const longRunningSeconds = 60;
const showLongRunning = ref(false);
const longRunningTimer = ref<number | null>(null);

const pollIntervalMs = 5000;
const pollTimer = ref<number | null>(null);

function clearPollTimer() {
    if (pollTimer.value !== null) {
        window.clearInterval(pollTimer.value);
        pollTimer.value = null;
    }
}

function startPollTimer() {
    clearPollTimer();
    pollTimer.value = window.setInterval(() => {
        if (step.value === 'processing') {
            void refreshImportState();
        }
    }, pollIntervalMs);
}

function clearLongRunningTimer() {
    if (longRunningTimer.value !== null) {
        window.clearTimeout(longRunningTimer.value);
        longRunningTimer.value = null;
    }
}

function startLongRunningTimer() {
    showLongRunning.value = false;
    clearLongRunningTimer();
    longRunningTimer.value = window.setTimeout(() => {
        showLongRunning.value = true;
    }, longRunningSeconds * 1000);
}

onBeforeUnmount(() => {
    clearLongRunningTimer();
    clearPollTimer();
});

function resetState() {
    step.value = 'form';
    selectedAccountId.value = props.preselectedAccountId ?? null;
    file.value = null;
    fileError.value = '';
    accountError.value = '';
    isDragging.value = false;
    importId.value = null;
    importState.value = null;
    isUploadingOrCommitting.value = false;
    showLongRunning.value = false;
    clearLongRunningTimer();
    clearPollTimer();
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetState();
        } else {
            clearLongRunningTimer();
            clearPollTimer();
        }
    },
);

function validateFile(next: File | null): string {
    if (!next) {
        return t('imports.validation.required');
    }

    const ext = next.name.toLowerCase().split('.').pop() ?? '';
    const allowed = ['csv', 'txt', 'xlsx'];
    if (!allowed.includes(ext)) {
        return t('imports.validation.fileType');
    }

    const maxBytes = 10 * 1024 * 1024;
    if (next.size > maxBytes) {
        return t('imports.validation.fileSize');
    }

    return '';
}

function onFileSelected(next: File | null) {
    file.value = next;
    fileError.value = validateFile(next);
}

async function uploadImport(): Promise<{ import_id: number; headers?: string[] }> {
    if (selectedAccountId.value === null || file.value === null) {
        throw new Error('Missing required fields.');
    }

    const form = new FormData();
    form.append('account_id', String(selectedAccountId.value));
    form.append('file', file.value);

    const res = await apiFetch(route('imports.upload'), {
        method: 'POST',
        body: form,
    });

    if (!res.ok) {
        const data = await res.json().catch(() => null);
        const accountIdMessage = data?.errors?.account_id?.[0] ?? '';
        const code = (data?.code as string | undefined) ?? '';

        if (code === 'bank_unsupported') {
            accountError.value = t('imports.validation.cashBlocked');
            throw new Error(t('imports.validation.cashBlocked'));
        }

        accountError.value = accountIdMessage || '';

        if (code === 'unrecognized_headers') {
            fileError.value = t('imports.toast.unrecognizedHeaders');
            throw new Error(t('imports.toast.unrecognizedHeaders'));
        }

        const fileMessage = data?.errors?.file?.[0] ?? '';
        fileError.value = fileMessage || (res.status === 422 ? t('imports.validation.generic') : t('imports.validation.generic'));
        throw new Error('Upload failed.');
    }

    const json = (await res.json()) as { import_id: number; headers?: string[] };
    return json;
}

async function commitImport(nextImportId: number) {
    const res = await apiFetch(route('imports.commit', nextImportId as any), {
        method: 'POST',
    });

    if (!res.ok) {
        const json = await res.json().catch(() => null);
        const message = json?.message ?? t('imports.toast.startFailed');
        throw new Error(message);
    }
}

async function refreshImportState() {
    if (importId.value === null) {
        return;
    }

    const res = await apiFetch(route('imports.show', importId.value as any));

    if (!res.ok) {
        return;
    }

    const json = (await res.json()) as ImportState;
    importState.value = json;

    const status = json.status;
    if (status === 'committed' || status === 'failed') {
        clearLongRunningTimer();
        clearPollTimer();
        step.value = 'result';
    }
}

watch(step, (nextStep) => {
    if (nextStep === 'processing') {
        startPollTimer();
    } else {
        clearPollTimer();
    }
});

const page = usePage() as any;
const currentUserId = computed<number | null>(() => (page.props.auth?.user?.id as number | undefined) ?? null);

const wsChannelName = ref<string | null>(null);

function handleImportStatusUpdated(payload: ImportState) {
    if (!props.open) {
        return;
    }

    if (importId.value === null || payload.id !== importId.value) {
        return;
    }

    importState.value = payload;

    if (payload.status === 'committed' || payload.status === 'failed') {
        clearLongRunningTimer();
        clearPollTimer();
        step.value = 'result';
    }
}

function resubscribeToImportUpdates() {
    const nextUserId = currentUserId.value;

    if (wsChannelName.value !== null) {
        echo().leaveChannel(`private-${wsChannelName.value}`);
        wsChannelName.value = null;
    }

    if (!nextUserId) {
        return;
    }

    const channelName = `App.Models.User.${nextUserId}`;
    wsChannelName.value = channelName;

    const channel = echo().private(channelName);

    // Laravel Echo usually uses the event class basename. Keep a few variants to be resilient
    // to differing broadcast naming (e.g. fully-qualified class name or custom broadcastAs).
    channel.listen('ImportStatusUpdated', (payload: ImportState) => handleImportStatusUpdated(payload));
    channel.listen('App\\Events\\ImportStatusUpdated', (payload: ImportState) => handleImportStatusUpdated(payload));
    channel.listen('.import.updated', (payload: ImportState) => handleImportStatusUpdated(payload));
}

watch(currentUserId, () => resubscribeToImportUpdates(), { immediate: true });
watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen && wsChannelName.value !== null) {
            echo().leaveChannel(`private-${wsChannelName.value}`);
            wsChannelName.value = null;
        }
    },
);

async function start() {
    accountError.value = '';
    fileError.value = validateFile(file.value);

    if (selectedAccountId.value === null) {
        accountError.value = t('imports.validation.required');
    }

    if (!canStart.value) {
        return;
    }

    const account = selectedAccount.value;
    if (!account) {
        accountError.value = t('imports.validation.required');
        return;
    }

    if (account.bank === 'cash') {
        accountError.value = t('imports.validation.cashBlocked');
        return;
    }

    isUploadingOrCommitting.value = true;
    step.value = 'processing';

    try {
        const upload = await uploadImport();
        importId.value = upload.import_id;

        await commitImport(upload.import_id);

        await refreshImportState();
        startLongRunningTimer();
    } catch (e: any) {
        step.value = 'form';
        clearPollTimer();
        const message = typeof e?.message === 'string' ? e.message : t('imports.toast.startFailed');
        if (!accountError.value) {
            fileError.value = message;
        }
    } finally {
        isUploadingOrCommitting.value = false;
    }
}

const failedRowsExpanded = ref(false);

const failedRows = computed(() => importState.value?.failed_rows ?? []);

const failedRowsOverflow = computed(() => {
    const total = importState.value?.failed_rows_total ?? failedRows.value.length;

    return total > failedRows.value.length ? total - failedRows.value.length : 0;
});

function displayRawValue(value: string | null | undefined): string {
    return value && value.trim() !== '' ? value : '—';
}

const resultSummary = computed(() => {
    const s = importState.value;
    if (!s) {
        return '';
    }

    const imported = s.rows_imported ?? 0;
    const dupes = s.rows_skipped_duplicate ?? 0;
    const failed = s.rows_failed_validation ?? 0;

    if (imported === 0 && dupes > 0) {
        return t('imports.result.allDuplicates');
    }

    return t('imports.result.summary', {
        rows_imported: imported,
        rows_skipped_duplicate: dupes,
        rows_failed_validation: failed,
    });
});

function goToTransactions() {
    if (importId.value === null) {
        emit('update:open', false);
        return;
    }

    const now = new Date();
    const from = new Date(now.getFullYear(), now.getMonth(), 1);
    const to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    const pad2 = (n: number) => String(n).padStart(2, '0');
    const formatDdMmYyyy = (d: Date) => `${pad2(d.getDate())}-${pad2(d.getMonth() + 1)}-${d.getFullYear()}`;

    router.get(
        route('transactions.index'),
        {
            from: formatDdMmYyyy(from),
            to: formatDdMmYyyy(to),
            sort: 'date',
            direction: 'desc',
        },
        { preserveScroll: true, replace: true },
    );
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ t('imports.dialog.title') }}</DialogTitle>
                <DialogDescription>
                    <span v-if="selectedAccount">
                        {{ t('imports.dialog.context', { account: selectedAccount.name, bank: selectedBankLabel }) }}
                    </span>
                    <span v-else>{{ t('imports.dialog.contextEmpty') }}</span>
                </DialogDescription>
            </DialogHeader>

            <div v-if="step === 'form'" class="grid gap-6">
                <div class="grid gap-2">
                    <DropdownSelect
                        id="import_account_id"
                        :model-value="selectedAccountId"
                        :options="accountOptions"
                        :placeholder="t('imports.dialog.account.label')"
                        :disabled="disabled || isUploadingOrCommitting || accounts.length === 0"
                        :aria-label="t('imports.dialog.account.label')"
                        :aria-invalid="accountError.trim() !== ''"
                        @update:model-value="(value: any) => (selectedAccountId = value)"
                    >
                        <template #trigger-leading>
                            <span
                                v-if="selectedAccount"
                                class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                :class="
                                    selectedAccount.bank === 'cash'
                                        ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                        : 'bg-muted'
                                "
                                aria-hidden="true"
                            >
                                <img
                                    v-if="selectedAccount.bank_icon_url"
                                    :src="selectedAccount.bank_icon_url"
                                    :alt="selectedAccount.name"
                                    class="h-5 w-5 object-cover"
                                />
                                <Coins v-else-if="selectedAccount.bank === 'cash'" class="h-3.5 w-3.5" />
                                <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                    {{ selectedAccount.name.charAt(0).toUpperCase() }}
                                </span>
                            </span>
                        </template>

                        <template #option-leading="{ option }">
                            <span
                                class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                :class="
                                    accountsById.get(option.value)?.bank === 'cash'
                                        ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                        : 'bg-muted'
                                "
                                aria-hidden="true"
                            >
                                <img
                                    v-if="accountsById.get(option.value)?.bank_icon_url"
                                    :src="accountsById.get(option.value)?.bank_icon_url ?? ''"
                                    :alt="accountsById.get(option.value)?.name ?? ''"
                                    class="h-5 w-5 object-cover"
                                />
                                <Coins v-else-if="accountsById.get(option.value)?.bank === 'cash'" class="h-3.5 w-3.5" />
                                <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                    {{ (accountsById.get(option.value)?.name ?? '?').charAt(0).toUpperCase() }}
                                </span>
                            </span>
                        </template>
                    </DropdownSelect>

                    <InputError :message="accountError" />
                    <p class="text-xs text-muted-foreground">{{ t('imports.dialog.account.hint') }}</p>
                </div>

                <div class="grid gap-2">
                    <p class="text-sm font-medium">{{ t('imports.dialog.file.label') }}</p>

                    <div
                        class="relative rounded-lg border border-dashed p-6 transition-colors"
                        :class="
                            cn(
                                isDragging ? 'border-primary bg-primary/5' : 'border-sidebar-border/70 dark:border-sidebar-border',
                                fileError ? 'border-rose-500/70' : '',
                            )
                        "
                        role="button"
                        tabindex="0"
                        @dragenter.prevent="isDragging = true"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="
                            (e) => {
                                isDragging = false;
                                const f = (e.dataTransfer?.files?.[0] as File | undefined) ?? null;
                                onFileSelected(f);
                            }
                        "
                        @keydown.enter.prevent="($refs.fileInput as HTMLInputElement | undefined)?.click()"
                        @keydown.space.prevent="($refs.fileInput as HTMLInputElement | undefined)?.click()"
                        @click="($refs.fileInput as HTMLInputElement | undefined)?.click()"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            class="hidden"
                            accept=".csv,.txt,.xlsx"
                            @change="(e: any) => onFileSelected((e.target?.files?.[0] as File | undefined) ?? null)"
                        />

                        <div class="flex items-start gap-4">
                            <div class="mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                <Upload class="h-5 w-5" aria-hidden="true" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-foreground">{{ t('imports.dialog.file.title') }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ t('imports.dialog.file.description') }}</p>
                                <div v-if="file" class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-muted px-3 py-1 text-xs text-foreground">{{ file.name }}</span>
                                    <Button variant="secondary" size="sm" type="button" @click.stop="onFileSelected(null)">
                                        {{ t('imports.dialog.file.change') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <InputError :message="fileError" />
                </div>

                <div class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 bg-muted/20 p-4 text-sm dark:border-sidebar-border">
                    <ShieldAlert class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                    <div class="grid gap-1">
                        <p class="font-medium text-foreground">{{ t('imports.dialog.warning.title') }}</p>
                        <p class="text-xs text-muted-foreground">{{ t('imports.dialog.warning.undo') }}</p>
                        <p class="text-xs text-muted-foreground">{{ t('imports.dialog.warning.dedupe') }}</p>
                        <p class="text-xs text-muted-foreground">{{ t('imports.dialog.warning.after') }}</p>
                    </div>
                </div>
            </div>

            <div v-else-if="step === 'processing'" class="grid gap-4">
                <div class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border" aria-busy="true">
                    <Loader2 class="mt-0.5 h-5 w-5 animate-spin text-muted-foreground" aria-hidden="true" />
                    <div class="grid gap-1">
                        <p class="text-sm font-medium text-foreground">{{ t('imports.dialog.processing.title') }}</p>
                        <p class="text-xs text-muted-foreground">{{ processingHint }}</p>
                    </div>
                </div>

                <div
                    v-if="showLongRunning && importState?.status !== 'committed' && importState?.status !== 'failed'"
                    class="rounded-lg bg-muted/30 p-4"
                >
                    <p class="text-sm text-muted-foreground">{{ t('imports.dialog.processing.longRunning') }}</p>
                    <div class="mt-3">
                        <Button variant="secondary" type="button" @click="refreshImportState">{{ t('imports.dialog.processing.refresh') }}</Button>
                    </div>
                </div>
            </div>

            <div v-else class="grid gap-6">
                <div
                    class="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    :class="importState?.status === 'failed' ? 'bg-rose-50/30 dark:bg-rose-950/10' : 'bg-emerald-50/30 dark:bg-emerald-950/10'"
                >
                    <CheckCircle2
                        v-if="importState?.status !== 'failed'"
                        class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                        aria-hidden="true"
                    />
                    <ShieldAlert v-else class="mt-0.5 h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400" aria-hidden="true" />
                    <div class="grid gap-1">
                        <p class="text-sm font-medium text-foreground">
                            {{ importState?.status === 'failed' ? t('imports.result.failedTitle') : t('imports.result.successTitle') }}
                        </p>
                        <p class="text-xs text-muted-foreground">{{ resultSummary }}</p>
                        <p v-if="importState?.status === 'failed' && importState?.error_summary" class="text-xs text-rose-700 dark:text-rose-300">
                            {{ importState.error_summary }}
                        </p>
                        <p v-if="(importState?.rows_failed_validation ?? 0) > 0" class="text-xs text-muted-foreground">
                            {{ t('imports.failed_rows.modal.hint') }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="(importState?.rows_failed_validation ?? 0) > 0 && failedRows.length > 0"
                    class="rounded-lg border border-amber-500/40 bg-amber-50/30 dark:border-amber-500/30 dark:bg-amber-950/10"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 p-4 text-left"
                        :aria-expanded="failedRowsExpanded"
                        @click="failedRowsExpanded = !failedRowsExpanded"
                    >
                        <p class="text-sm font-medium text-foreground">{{ t('imports.failed_rows.modal.title') }}</p>
                        <ChevronDown
                            class="h-5 w-5 shrink-0 text-muted-foreground transition-transform"
                            :class="failedRowsExpanded ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                    </button>

                    <div v-if="failedRowsExpanded" class="border-t border-amber-500/20 px-4 pb-4 pt-2">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-xs text-muted-foreground">
                                    <tr>
                                        <th class="px-2 py-2 text-left font-medium">{{ t('imports.failed_rows.table.row') }}</th>
                                        <th class="px-2 py-2 text-left font-medium">{{ t('imports.failed_rows.table.date') }}</th>
                                        <th class="px-2 py-2 text-left font-medium">{{ t('imports.failed_rows.table.amount') }}</th>
                                        <th class="px-2 py-2 text-left font-medium">{{ t('imports.failed_rows.table.description') }}</th>
                                        <th class="px-2 py-2 text-left font-medium">{{ t('imports.failed_rows.table.reason') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in failedRows" :key="row.id" class="border-t border-sidebar-border/50">
                                        <td class="px-2 py-2 tabular-nums">{{ row.row_number }}</td>
                                        <td class="px-2 py-2 whitespace-nowrap">{{ displayRawValue(row.date_raw) }}</td>
                                        <td class="px-2 py-2 whitespace-nowrap tabular-nums">{{ displayRawValue(row.amount_raw) }}</td>
                                        <td class="max-w-xs truncate px-2 py-2">{{ displayRawValue(row.description_raw) }}</td>
                                        <td class="px-2 py-2 text-xs text-muted-foreground">{{ t(row.reason_label_key) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="failedRowsOverflow > 0" class="mt-2 text-xs text-muted-foreground">
                            {{ t('imports.failed_rows.modal.more_on_transactions', { count: failedRowsOverflow }) }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p class="text-xs text-muted-foreground">{{ t('imports.result.metrics.imported') }}</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                            {{ importState?.rows_imported ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p class="text-xs text-muted-foreground">{{ t('imports.result.metrics.duplicates') }}</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-muted-foreground">
                            {{ importState?.rows_skipped_duplicate ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p class="text-xs text-muted-foreground">{{ t('imports.result.metrics.failed') }}</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-rose-600 dark:text-rose-400">
                            {{ importState?.rows_failed_validation ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button v-if="step === 'form'" variant="secondary" type="button" @click="emit('update:open', false)">
                    {{ t('imports.dialog.actions.cancel') }}
                </Button>
                <Button v-if="step === 'form'" type="button" :disabled="!canStart" @click="start">
                    {{ isUploadingOrCommitting ? t('imports.dialog.actions.startLoading') : t('imports.dialog.actions.start') }}
                </Button>

                <Button v-else-if="step === 'processing'" variant="secondary" type="button" @click="emit('update:open', false)">
                    {{ t('imports.dialog.actions.close') }}
                </Button>

                <template v-else>
                    <Button type="button" @click="goToTransactions">{{ t('imports.dialog.actions.goToTransactions') }}</Button>
                    <Button variant="secondary" type="button" @click="emit('update:open', false)">{{ t('imports.dialog.actions.close') }}</Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
