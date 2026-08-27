<script setup lang="ts">
import CategoryBadge from '@/components/categories/CategoryBadge.vue';
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import UnlinkTransferDialog from '@/components/transfers/UnlinkTransferDialog.vue';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import { categoriesByIdMap, filterCategoriesByType, firstCategoryId, type CategoryOption } from '@/lib/categories';
import { displayAmount, normalizeAmount } from '@/lib/money';
import { track } from '@/lib/telemetry';
import { useForm } from '@inertiajs/vue3';
import { ChevronDown, Coins } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type Account = {
    id: number;
    name: string;
    currency_id?: number;
    bank?: string;
    bank_icon_url: string | null;
};

type Transaction = {
    id: number;
    account_id: number;
    date: string;
    booked_at: string;
    amount: string | number;
    type: string;
    description: string;
    subject: string | null;
    raw_statement_description: string | null;
    transfer_id: string | null;
    category_id: number | null;
    pocket_id: number | null;
    pocket_movement: 'deposit' | 'withdrawal' | null;
};

type PocketOption = {
    id: number;
    name: string;
    icon: string;
    color: string;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    transaction: Transaction | null;
    accounts: Account[];
    categories: CategoryOption[];
    pockets: PocketOption[];
    accountIdFilter: number | null;
    formResetKey?: number;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();
const { transactionsIndexSearch } = useTransactionsIndexSearch();

const transactionKind = ref<'income' | 'expense'>('expense');
const statementOpen = ref(false);
const unlinkDialogOpen = ref(false);
const unlinkProcessing = ref(false);

const form = useForm<{
    account_id: number | null;
    category_id: number | null;
    pocket_id: number | null;
    pocket_movement: 'deposit' | 'withdrawal' | null;
    date: string;
    booked_at: string;
    amount: string;
    description: string;
    subject: string;
}>({
    account_id: null,
    category_id: null,
    pocket_id: null,
    pocket_movement: null,
    date: '',
    booked_at: '',
    amount: '0,00',
    description: '',
    subject: '',
});

const accountOptions = computed<DropdownOption<number>[]>(() => props.accounts.map((a) => ({ value: a.id, label: a.name })));
const accountsById = computed(() => new Map(props.accounts.map((a) => [a.id, a])));
const categoriesById = computed(() => categoriesByIdMap(props.categories));
const pocketsById = computed(() => new Map(props.pockets.map((pocket) => [pocket.id, pocket])));

const editType = computed<'income' | 'expense'>(() => (props.transaction?.type === 'income' ? 'income' : 'expense'));

const activeType = computed<'income' | 'expense'>(() => (props.mode === 'edit' ? editType.value : transactionKind.value));

const categoryOptions = computed<DropdownOption<number>[]>(() =>
    filterCategoriesByType(props.categories, activeType.value).map((c) => ({
        value: c.id,
        label: c.name,
    })),
);

const pocketOptions = computed<DropdownOption<number | null>[]>(() => [
    { value: null, label: t('transactions.fields.pocketNone') },
    ...props.pockets.map((p) => ({
        value: p.id,
        label: p.name,
    })),
]);

const selectedAccount = computed(() => {
    if (form.account_id === null) {
        return null;
    }

    return accountsById.value.get(form.account_id) ?? null;
});

const isLinkedTransfer = computed(() => Boolean(props.mode === 'edit' && props.transaction?.transfer_id));
const hasStatement = computed(() => (props.transaction?.raw_statement_description ?? '').trim() !== '');

const title = computed(() => t(props.mode === 'edit' ? 'transactions.edit.title' : 'transactions.create.title'));

const description = computed(() => {
    if (props.mode !== 'edit' || props.transaction === null) {
        return t('transactions.create.description');
    }

    const text = props.transaction.description.trim();

    return text !== '' ? text : (props.transaction.subject ?? '').trim();
});

function todayDdMmYyyy(): string {
    const now = new Date();
    const d = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));

    return `${String(d.getUTCDate()).padStart(2, '0')}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${d.getUTCFullYear()}`;
}

function isoToDdMmYyyy(input: string): string {
    const trimmed = input.trim();
    const datePart = /^\d{4}-\d{2}-\d{2}/.test(trimmed) ? trimmed.slice(0, 10) : trimmed;
    const parts = datePart.split('-');

    if (parts.length !== 3) {
        return input;
    }

    const [yyyy, mm, dd] = parts;

    return `${dd}-${mm}-${yyyy}`;
}

function applyAmountSign(amount: string): string {
    const trimmed = amount.trim();

    if (trimmed === '') {
        return amount;
    }

    if (transactionKind.value === 'expense') {
        return trimmed.startsWith('-') ? amount : `-${amount}`;
    }

    return trimmed.startsWith('-') ? amount.replace(/^\s*-+/, '') : amount;
}

function defaultAccountId(): number | null {
    if (props.accountIdFilter !== null && props.accounts.some((a) => a.id === props.accountIdFilter)) {
        return props.accountIdFilter;
    }

    return props.accounts[0]?.id ?? null;
}

function resetForm(): void {
    form.clearErrors();
    statementOpen.value = false;

    if (props.mode === 'edit' && props.transaction) {
        transactionKind.value = editType.value;
        form.account_id = props.transaction.account_id;
        form.category_id = props.transaction.category_id;
        form.pocket_id = props.transaction.pocket_id;
        form.pocket_movement = props.transaction.pocket_movement;
        form.date = isoToDdMmYyyy(props.transaction.date);
        form.booked_at = props.transaction.booked_at === props.transaction.date ? '' : isoToDdMmYyyy(props.transaction.booked_at);
        form.amount = displayAmount(String(props.transaction.amount));
        form.description = props.transaction.description;
        form.subject = props.transaction.subject ?? '';

        return;
    }

    form.reset();
    transactionKind.value = 'expense';
    form.account_id = defaultAccountId();
    form.category_id = firstCategoryId(filterCategoriesByType(props.categories, 'expense'));
    form.pocket_id = null;
    form.pocket_movement = null;
    form.date = todayDdMmYyyy();
    form.booked_at = '';
    form.amount = '0,00';
    form.description = '';
    form.subject = '';
}

async function focusAccountTrigger(): Promise<void> {
    await nextTick();
    document.getElementById('account_id')?.focus();
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            resetForm();

            if (props.mode === 'create') {
                track('transaction_create_opened');
            } else {
                await focusAccountTrigger();
            }
        }
    },
);

watch(
    () => props.formResetKey,
    () => {
        if (props.open && props.mode === 'create') {
            resetForm();
        }
    },
);

watch(transactionKind, (kind) => {
    if (props.mode === 'edit') {
        return;
    }

    const filtered = filterCategoriesByType(props.categories, kind);

    if (!filtered.some((c) => c.id === form.category_id)) {
        form.category_id = firstCategoryId(filtered);
    }
});

watch(
    () => form.pocket_id,
    (pocketId) => {
        if (!isLinkedTransfer.value) {
            return;
        }

        if (pocketId === null) {
            form.pocket_movement = null;

            return;
        }

        if (form.pocket_movement === null) {
            form.pocket_movement = 'deposit';
        }
    },
);

function onOpenChange(value: boolean): void {
    if (form.processing && !value) {
        return;
    }

    emit('update:open', value);
}

function fieldDescribedBy(errorId: string | undefined, hasError: boolean, hintId: string | undefined, includeHint: boolean): string | undefined {
    const ids: string[] = [];

    if (hasError && errorId) {
        ids.push(errorId);
    }

    if (includeHint && hintId) {
        ids.push(hintId);
    }

    return ids.length > 0 ? ids.join(' ') : undefined;
}

function submit(): void {
    form.amount = normalizeAmount(form.amount);

    if (props.mode === 'create') {
        form.amount = applyAmountSign(form.amount);
    }

    if ((form.booked_at ?? '').trim() === '') {
        form.booked_at = form.date;
    }

    form.transform((data) => {
        const payload = { ...data };

        if (isLinkedTransfer.value) {
            if (payload.pocket_id === null) {
                payload.pocket_movement = null;
            }
        } else {
            delete payload.pocket_id;
            delete payload.pocket_movement;
        }

        return payload;
    });

    const visit = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    };

    if (props.mode === 'edit' && props.transaction) {
        form.put(route('transactions.update', props.transaction.id) + transactionsIndexSearch.value, visit);

        return;
    }

    form.post(route('transactions.store') + transactionsIndexSearch.value, visit);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <div v-if="isLinkedTransfer" class="rounded-xl border border-primary/30 bg-primary/5 p-4 dark:border-primary/40 dark:bg-primary/10">
                <p class="text-sm font-semibold text-foreground">{{ t('transactions.edit.transfer.bannerTitle') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('transactions.edit.transfer.bannerDescription') }}</p>
                <Button class="mt-3" type="button" variant="secondary" :disabled="unlinkProcessing" @click="unlinkDialogOpen = true">
                    {{ t('transactions.edit.transfer.unlinkAction') }}
                </Button>
            </div>

            <form class="grid gap-6" @submit.prevent="submit" :aria-busy="form.processing ? 'true' : 'false'">
                <fieldset v-if="mode === 'create'" class="grid gap-2 border-0 p-0" :disabled="form.processing">
                    <legend class="text-sm font-medium">{{ t('transactions.form.type') }}</legend>
                    <div class="grid grid-cols-2 gap-1 rounded-xl border border-input bg-muted/30 p-1">
                        <Button
                            type="button"
                            :variant="transactionKind === 'income' ? 'secondary' : 'ghost'"
                            class="h-9 justify-center rounded-lg"
                            :aria-pressed="transactionKind === 'income'"
                            :disabled="form.processing"
                            autofocus
                            @click="transactionKind = 'income'"
                        >
                            {{ t('transactions.types.income') }}
                        </Button>
                        <Button
                            type="button"
                            :variant="transactionKind === 'expense' ? 'secondary' : 'ghost'"
                            class="h-9 justify-center rounded-lg"
                            :aria-pressed="transactionKind === 'expense'"
                            :disabled="form.processing"
                            @click="transactionKind = 'expense'"
                        >
                            {{ t('transactions.types.expense') }}
                        </Button>
                    </div>
                </fieldset>

                <FormField
                    for-id="account_id"
                    :label="t('transactions.form.account')"
                    :error="form.errors.account_id"
                    :hint="isLinkedTransfer ? t('transactions.edit.transfer.accountHint') : null"
                >
                    <template #default="{ errorId, hintId, hasError }">
                        <DropdownSelect
                            id="account_id"
                            :aria-invalid="hasError"
                            :aria-disabled="isLinkedTransfer"
                            :aria-describedby="fieldDescribedBy(errorId, hasError, hintId, isLinkedTransfer)"
                            :model-value="form.account_id"
                            :options="accountOptions"
                            :placeholder="t('transactions.form.account')"
                            :disabled="form.processing || accounts.length === 0 || isLinkedTransfer"
                            @update:model-value="(value) => (form.account_id = value)"
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
                    </template>
                </FormField>

                <FormField v-if="!isLinkedTransfer" for-id="category_id" :label="t('transactions.fields.category')" :error="form.errors.category_id">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="category_id"
                            :aria-invalid="hasError"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.category_id"
                            :options="categoryOptions"
                            :placeholder="t('transactions.fields.category')"
                            :disabled="form.processing || categoryOptions.length === 0"
                            @update:model-value="(value) => (form.category_id = value)"
                        >
                            <template #trigger-leading>
                                <CategoryBadge
                                    v-if="form.category_id !== null && categoriesById.get(form.category_id)"
                                    :icon="categoriesById.get(form.category_id)!.icon"
                                    :color="categoriesById.get(form.category_id)!.color"
                                    size="sm"
                                    :show-name="false"
                                />
                            </template>
                            <template #option-leading="{ option }">
                                <CategoryBadge
                                    v-if="categoriesById.get(option.value)"
                                    :icon="categoriesById.get(option.value)!.icon"
                                    :color="categoriesById.get(option.value)!.color"
                                    size="sm"
                                    :show-name="false"
                                />
                            </template>
                        </DropdownSelect>
                    </template>
                </FormField>

                <FormField v-if="isLinkedTransfer" for-id="pocket_id" :label="t('transfers.form.pocket')" :error="form.errors.pocket_id">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="pocket_id"
                            :aria-invalid="hasError"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.pocket_id"
                            :options="pocketOptions"
                            :placeholder="t('transfers.form.pocketPlaceholder')"
                            :disabled="form.processing || pocketOptions.length <= 1"
                            @update:model-value="(value) => (form.pocket_id = value)"
                        >
                            <template #trigger-leading>
                                <PocketBadge
                                    v-if="form.pocket_id !== null && pocketsById.get(form.pocket_id)"
                                    :icon="pocketsById.get(form.pocket_id)!.icon"
                                    :color="pocketsById.get(form.pocket_id)!.color"
                                    size="sm"
                                    :show-name="false"
                                />
                            </template>
                            <template #option-leading="{ option }">
                                <PocketBadge
                                    v-if="option.value !== null && pocketsById.get(option.value)"
                                    :icon="pocketsById.get(option.value)!.icon"
                                    :color="pocketsById.get(option.value)!.color"
                                    size="sm"
                                    :show-name="false"
                                />
                            </template>
                        </DropdownSelect>
                    </template>
                </FormField>

                <FormField
                    v-if="isLinkedTransfer && form.pocket_id !== null"
                    for-id="pocket_movement"
                    :label="t('transfers.form.pocketMovement')"
                    :error="form.errors.pocket_movement"
                    :hint="t('transfers.form.pocketMovementHint')"
                >
                    <template #default="{ errorId, hintId, hasError, hasHint }">
                        <div
                            class="grid grid-cols-2 gap-1 rounded-xl border border-input bg-muted/30 p-1"
                            role="group"
                            :aria-describedby="[hasError ? errorId : undefined, hasHint ? hintId : undefined].filter(Boolean).join(' ') || undefined"
                        >
                            <Button
                                type="button"
                                :variant="form.pocket_movement === 'deposit' ? 'secondary' : 'ghost'"
                                class="h-9 justify-center rounded-lg"
                                :aria-pressed="form.pocket_movement === 'deposit'"
                                :disabled="form.processing"
                                @click="form.pocket_movement = 'deposit'"
                            >
                                {{ t('transfers.form.pocketMovementDeposit') }}
                            </Button>
                            <Button
                                type="button"
                                :variant="form.pocket_movement === 'withdrawal' ? 'secondary' : 'ghost'"
                                class="h-9 justify-center rounded-lg"
                                :aria-pressed="form.pocket_movement === 'withdrawal'"
                                :disabled="form.processing"
                                @click="form.pocket_movement = 'withdrawal'"
                            >
                                {{ t('transfers.form.pocketMovementWithdrawal') }}
                            </Button>
                        </div>
                    </template>
                </FormField>

                <FormField for-id="date" :label="t('transactions.form.date')" :error="form.errors.date">
                    <template #default="{ errorId, hasError }">
                        <DatePickerInput
                            id="date"
                            :aria-invalid="hasError"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.date"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.date = value)"
                        />
                    </template>
                </FormField>

                <FormField
                    for-id="amount"
                    :label="t('transactions.form.amount')"
                    :error="form.errors.amount"
                    :hint="isLinkedTransfer ? t('transactions.edit.transfer.amountHint') : null"
                >
                    <template #default="{ errorId, hintId, hasError }">
                        <Input
                            id="amount"
                            v-model="form.amount"
                            inputmode="decimal"
                            :disabled="form.processing || isLinkedTransfer"
                            :aria-disabled="isLinkedTransfer ? true : undefined"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="fieldDescribedBy(errorId, hasError, hintId, isLinkedTransfer)"
                        />
                    </template>
                </FormField>

                <FormField for-id="subject" :label="t('transactions.form.subject')" :error="form.errors.subject">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="subject"
                            v-model="form.subject"
                            :disabled="form.processing"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <FormField for-id="description" :label="t('transactions.form.description')" :error="form.errors.description">
                    <template #default="{ errorId, hasError }">
                        <textarea
                            id="description"
                            v-model="form.description"
                            :disabled="form.processing"
                            rows="4"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            class="flex min-h-[96px] w-full rounded-xl border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        />
                    </template>
                </FormField>

                <Collapsible class="w-full">
                    <CollapsibleTrigger
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-lg text-left text-sm font-medium outline-none ring-offset-background hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        {{ t('advancedSection.toggle') }}
                        <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <p class="mt-2 text-sm text-muted-foreground">{{ t('transactions.form.advancedDatesHint') }}</p>
                        <FormField class="mt-4" for-id="booked_at" :label="t('transactions.form.booked_at')" :error="form.errors.booked_at">
                            <template #default="{ errorId, hasError }">
                                <DatePickerInput
                                    id="booked_at"
                                    :aria-invalid="hasError"
                                    :aria-describedby="hasError ? errorId : undefined"
                                    :model-value="form.booked_at"
                                    :disabled="form.processing"
                                    @update:model-value="(value) => (form.booked_at = value)"
                                />
                            </template>
                        </FormField>
                    </CollapsibleContent>
                </Collapsible>

                <Collapsible v-if="mode === 'edit' && hasStatement" v-model:open="statementOpen" class="w-full">
                    <CollapsibleTrigger
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-lg text-left text-sm font-medium outline-none ring-offset-background hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        {{ t('transactions.edit.statement.title') }}
                        <ChevronDown class="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <p class="mt-2 text-sm text-muted-foreground">{{ t('transactions.edit.statement.description') }}</p>
                        <p class="mt-3 whitespace-pre-wrap break-words text-sm text-foreground">
                            {{ transaction?.raw_statement_description }}
                        </p>
                    </CollapsibleContent>
                </Collapsible>

                <DialogFooter>
                    <Button type="button" variant="secondary" :disabled="form.processing" @click="onOpenChange(false)">
                        {{ t('actions.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing" :aria-busy="form.processing || undefined">
                        {{ t('actions.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <UnlinkTransferDialog
        v-model:open="unlinkDialogOpen"
        :transfer-id="transaction?.transfer_id ?? null"
        :disabled="unlinkProcessing"
        @processing="(value: boolean) => (unlinkProcessing = value)"
    />
</template>
