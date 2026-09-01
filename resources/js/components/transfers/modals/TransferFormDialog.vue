<script setup lang="ts">
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import Icon from '@/components/Icon.vue';
import PocketBadge from '@/components/pockets/PocketBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTransactionsIndexSearch } from '@/composables/useTransactionsIndexSearch';
import { normalizeAmount } from '@/lib/money';
import { suggestedPocketMovement } from '@/lib/suggestedPocketMovement';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type Account = {
    id: number;
    name: string;
    bank?: string;
    type?: string;
    is_deleted?: boolean;
    bank_icon_url: string | null;
};

type PocketOption = {
    id: number;
    name: string;
    icon: string;
    color: string;
};

const props = defineProps<{
    open: boolean;
    accounts: Account[];
    pockets: PocketOption[];
    accountIdFilter: number | null;
    formResetKey?: number;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();
const { transactionsIndexSearch } = useTransactionsIndexSearch();

const selectableAccounts = computed(() => props.accounts.filter((account) => !account.is_deleted));
const canTransfer = computed(() => selectableAccounts.value.length >= 2);

const accountOptions = computed<DropdownOption<number>[]>(() =>
    selectableAccounts.value.map((account) => ({ value: account.id, label: account.name })),
);
const accountsById = computed(() => new Map(selectableAccounts.value.map((account) => [account.id, account])));
const pocketsById = computed(() => new Map(props.pockets.map((pocket) => [pocket.id, pocket])));

const pocketOptions = computed<DropdownOption<number | null>[]>(() => [
    { value: null, label: t('transactions.fields.pocketNone') },
    ...props.pockets.map((pocket) => ({ value: pocket.id, label: pocket.name })),
]);

function isCashBank(bank: string | null | undefined): boolean {
    return (bank ?? '').toLowerCase() === 'cash';
}

function accountWellClass(bank: string | null | undefined): string {
    return isCashBank(bank)
        ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
        : 'bg-muted';
}

function todayDdMmYyyy(): string {
    const now = new Date();
    const d = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
    const dd = String(d.getUTCDate()).padStart(2, '0');
    const mm = String(d.getUTCMonth() + 1).padStart(2, '0');

    return `${dd}-${mm}-${d.getUTCFullYear()}`;
}

function defaultFromId(): number | null {
    const filterId = props.accountIdFilter;

    if (filterId !== null && selectableAccounts.value.some((account) => account.id === filterId)) {
        return filterId;
    }

    return selectableAccounts.value[0]?.id ?? null;
}

function defaultToId(fromId: number | null): number | null {
    return selectableAccounts.value.find((account) => account.id !== fromId)?.id ?? null;
}

const form = useForm<{
    from_account_id: number | null;
    to_account_id: number | null;
    pocket_id: number | null;
    pocket_movement: 'deposit' | 'withdrawal' | null;
    date: string;
    amount: string;
    subject: string;
    description: string;
}>({
    from_account_id: null,
    to_account_id: null,
    pocket_id: null,
    pocket_movement: null,
    date: todayDdMmYyyy(),
    amount: '0,00',
    subject: '',
    description: '',
});

const formErrorId = 'transfer-form-error';

const fromAccount = computed(() => (form.from_account_id !== null ? (accountsById.value.get(form.from_account_id) ?? null) : null));
const toAccount = computed(() => (form.to_account_id !== null ? (accountsById.value.get(form.to_account_id) ?? null) : null));
const isSameAccount = computed(() => form.from_account_id !== null && form.to_account_id !== null && form.from_account_id === form.to_account_id);

function resetForm(): void {
    const fromId = defaultFromId();

    form.clearErrors();
    form.from_account_id = fromId;
    form.to_account_id = defaultToId(fromId);
    form.pocket_id = null;
    form.pocket_movement = null;
    form.date = todayDdMmYyyy();
    form.amount = '0,00';
    form.subject = '';
    form.description = '';
}

async function focusFirstControl(): Promise<void> {
    await nextTick();

    if (!canTransfer.value) {
        document.getElementById('transfer-add-account')?.focus();

        return;
    }

    document.getElementById('from_account_id')?.focus();
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            resetForm();
            await focusFirstControl();
        }
    },
);

watch(
    () => props.formResetKey,
    () => {
        if (props.open) {
            resetForm();
        }
    },
);

watch(
    () => form.pocket_id,
    (pocketId) => {
        if (pocketId === null) {
            form.pocket_movement = null;

            return;
        }

        if (form.pocket_movement === null) {
            form.pocket_movement = suggestedPocketMovement(fromAccount.value?.type, toAccount.value?.type);
        }
    },
);

watch([() => form.from_account_id, () => form.to_account_id], () => {
    if (form.pocket_id === null) {
        return;
    }

    form.pocket_movement = suggestedPocketMovement(fromAccount.value?.type, toAccount.value?.type);
});

function onOpenChange(value: boolean): void {
    if (form.processing && !value) {
        return;
    }

    emit('update:open', value);
}

function submit(): void {
    if (!canTransfer.value) {
        return;
    }

    form.amount = normalizeAmount(form.amount);

    form.transform((data) => {
        if (data.pocket_id === null) {
            const { pocket_movement, ...payload } = data;

            return payload;
        }

        return data;
    });

    form.post(route('transfers.store') + transactionsIndexSearch.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('transfers.create.title') }}</DialogTitle>
                <DialogDescription>{{ t('transfers.create.description') }}</DialogDescription>
            </DialogHeader>

            <div v-if="!canTransfer" class="grid gap-6">
                <p class="text-sm text-muted-foreground">{{ t('transfers.create.empty') }}</p>
                <DialogFooter>
                    <Button type="button" variant="secondary" @click="onOpenChange(false)">
                        {{ t('actions.cancel') }}
                    </Button>
                    <Button id="transfer-add-account" as-child>
                        <Link :href="route('accounts.index')">{{ t('accounts.index.addAccount') }}</Link>
                    </Button>
                </DialogFooter>
            </div>

            <form v-else class="grid gap-6" :aria-busy="form.processing ? 'true' : 'false'" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <FormField for-id="from_account_id" :label="t('transfers.form.fromAccount')" :error="form.errors.from_account_id">
                        <template #default="{ errorId, hasError }">
                            <DropdownSelect
                                id="from_account_id"
                                :model-value="form.from_account_id"
                                :options="accountOptions"
                                :placeholder="t('transfers.form.selectPlaceholder')"
                                :disabled="form.processing"
                                :aria-invalid="Boolean(hasError || (isSameAccount && form.to_account_id !== null))"
                                :aria-describedby="hasError ? errorId : undefined"
                                @update:model-value="(value) => (form.from_account_id = value)"
                            >
                                <template #trigger-leading>
                                    <span
                                        v-if="fromAccount"
                                        class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                        :class="accountWellClass(fromAccount.bank)"
                                        aria-hidden="true"
                                    >
                                        <img
                                            v-if="fromAccount.bank_icon_url && !isCashBank(fromAccount.bank)"
                                            :src="fromAccount.bank_icon_url"
                                            :alt="fromAccount.name"
                                            class="h-5 w-5 object-cover"
                                        />
                                        <Icon v-else-if="isCashBank(fromAccount.bank)" :name="'coins'" class="h-3.5 w-3.5" aria-hidden="true" />
                                        <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                            {{ fromAccount.name.charAt(0).toUpperCase() }}
                                        </span>
                                    </span>
                                </template>

                                <template #option-leading="{ option }">
                                    <span
                                        class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                        :class="accountWellClass(accountsById.get(option.value)?.bank)"
                                        aria-hidden="true"
                                    >
                                        <img
                                            v-if="accountsById.get(option.value)?.bank_icon_url && !isCashBank(accountsById.get(option.value)?.bank)"
                                            :src="accountsById.get(option.value)?.bank_icon_url ?? ''"
                                            :alt="accountsById.get(option.value)?.name ?? ''"
                                            class="h-5 w-5 object-cover"
                                        />
                                        <Icon
                                            v-else-if="isCashBank(accountsById.get(option.value)?.bank)"
                                            :name="'coins'"
                                            class="h-3.5 w-3.5"
                                            aria-hidden="true"
                                        />
                                        <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                            {{ (accountsById.get(option.value)?.name ?? '?').charAt(0).toUpperCase() }}
                                        </span>
                                    </span>
                                </template>
                            </DropdownSelect>
                        </template>
                    </FormField>

                    <FormField for-id="to_account_id" :label="t('transfers.form.toAccount')" :error="form.errors.to_account_id">
                        <template #default="{ errorId, hasError }">
                            <DropdownSelect
                                id="to_account_id"
                                :model-value="form.to_account_id"
                                :options="accountOptions"
                                :placeholder="t('transfers.form.selectPlaceholder')"
                                :disabled="form.processing"
                                :aria-invalid="Boolean(hasError || isSameAccount)"
                                :aria-describedby="hasError ? errorId : isSameAccount ? formErrorId : undefined"
                                @update:model-value="(value) => (form.to_account_id = value)"
                            >
                                <template #trigger-leading>
                                    <span
                                        v-if="toAccount"
                                        class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                        :class="accountWellClass(toAccount.bank)"
                                        aria-hidden="true"
                                    >
                                        <img
                                            v-if="toAccount.bank_icon_url && !isCashBank(toAccount.bank)"
                                            :src="toAccount.bank_icon_url"
                                            :alt="toAccount.name"
                                            class="h-5 w-5 object-cover"
                                        />
                                        <Icon v-else-if="isCashBank(toAccount.bank)" :name="'coins'" class="h-3.5 w-3.5" aria-hidden="true" />
                                        <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                            {{ toAccount.name.charAt(0).toUpperCase() }}
                                        </span>
                                    </span>
                                </template>

                                <template #option-leading="{ option }">
                                    <span
                                        class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                        :class="accountWellClass(accountsById.get(option.value)?.bank)"
                                        aria-hidden="true"
                                    >
                                        <img
                                            v-if="accountsById.get(option.value)?.bank_icon_url && !isCashBank(accountsById.get(option.value)?.bank)"
                                            :src="accountsById.get(option.value)?.bank_icon_url ?? ''"
                                            :alt="accountsById.get(option.value)?.name ?? ''"
                                            class="h-5 w-5 object-cover"
                                        />
                                        <Icon
                                            v-else-if="isCashBank(accountsById.get(option.value)?.bank)"
                                            :name="'coins'"
                                            class="h-3.5 w-3.5"
                                            aria-hidden="true"
                                        />
                                        <span v-else class="text-[10px] font-semibold text-muted-foreground">
                                            {{ (accountsById.get(option.value)?.name ?? '?').charAt(0).toUpperCase() }}
                                        </span>
                                    </span>
                                </template>
                            </DropdownSelect>
                        </template>
                    </FormField>
                </div>

                <FormField for-id="pocket_id" :label="t('transfers.form.pocket')" :error="form.errors.pocket_id">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="pocket_id"
                            :model-value="form.pocket_id"
                            :options="pocketOptions"
                            :placeholder="t('transfers.form.pocketPlaceholder')"
                            :disabled="form.processing || pocketOptions.length <= 1"
                            :aria-invalid="hasError"
                            :aria-describedby="hasError ? errorId : undefined"
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
                    v-if="form.pocket_id !== null"
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

                <div class="grid gap-4 md:grid-cols-2">
                    <FormField for-id="amount" :label="t('transfers.form.amount')" :error="form.errors.amount">
                        <template #default="{ errorId, hasError }">
                            <Input
                                id="amount"
                                v-model="form.amount"
                                inputmode="decimal"
                                :disabled="form.processing"
                                :placeholder="t('transfers.form.amountPlaceholder')"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                            />
                        </template>
                    </FormField>

                    <FormField for-id="date" :label="t('transfers.form.date')" :error="form.errors.date">
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
                </div>

                <FormField for-id="subject" :label="t('transfers.form.subjectOptional')" :error="form.errors.subject">
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

                <FormField for-id="description" :label="t('transfers.form.descriptionOptional')" :error="form.errors.description">
                    <template #default="{ errorId, hasError }">
                        <textarea
                            id="description"
                            v-model="form.description"
                            :disabled="form.processing"
                            rows="3"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            class="flex min-h-[84px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        />
                    </template>
                </FormField>

                <div v-if="isSameAccount" :id="formErrorId" class="text-sm text-rose-600 dark:text-rose-400">
                    {{ t('transfers.form.sameAccountError') }}
                </div>

                <DialogFooter>
                    <Button type="button" variant="secondary" :disabled="form.processing" @click="onOpenChange(false)">
                        {{ t('actions.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing || isSameAccount" :aria-busy="form.processing || undefined">
                        {{ form.processing ? t('transfers.form.saving') : t('transfers.form.submit') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
