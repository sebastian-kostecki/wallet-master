<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { defaultBankForKind, type AccountKind } from '@/lib/accountKind';
import { displayAmount, normalizeAmount } from '@/lib/money';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from 'ziggy-js';

type Currency = {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    precision: number;
};

type Option = {
    value: string;
    label_key: string;
    icon_url?: string | null;
    icon_name?: string | null;
};

type Account = {
    id: number;
    name: string;
    bank: string;
    type: string;
    currency_id: number;
    opening_balance: string;
    current_balance: string;
    currency: { symbol: string | null } | null;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    kind: AccountKind;
    account: Account | null;
    currencies: Currency[];
    banks: Option[];
    accountTypes: Option[];
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();

const form = useForm<{
    name: string;
    bank: string;
    type: string;
    currency_id: number | null;
    opening_balance: string;
}>({
    name: '',
    bank: '',
    type: '',
    currency_id: null,
    opening_balance: '0,00',
});

const title = computed(() => {
    if (props.mode === 'edit') {
        return t('accounts.edit.title');
    }

    return t(props.kind === 'wallets' ? 'accounts.create.titleWallet' : 'accounts.create.title');
});

const description = computed(() => {
    if (props.mode === 'edit') {
        return props.account?.name ?? '';
    }

    return t(props.kind === 'wallets' ? 'accounts.create.descriptionWallet' : 'accounts.create.description');
});

const selectedBank = computed(() => props.banks.find((b) => b.value === form.bank) ?? null);
const selectedAccountType = computed(() => props.accountTypes.find((item) => item.value === form.type) ?? null);
const currencySymbol = computed(() => props.account?.currency?.symbol ?? t('currency.defaultSymbol'));

function accountTypeIconClasses(typeValue: string | undefined): string {
    if (typeValue === 'checking') {
        return 'bg-gradient-to-br from-sky-100 to-indigo-200 text-sky-900 dark:from-sky-950/40 dark:to-indigo-950/40 dark:text-sky-200';
    }

    if (typeValue === 'savings') {
        return 'bg-gradient-to-br from-emerald-100 to-lime-200 text-emerald-900 dark:from-emerald-950/40 dark:to-lime-950/40 dark:text-emerald-200';
    }

    return 'bg-muted text-muted-foreground';
}

function resolveBankIconUrl(bankValue: string): string | null {
    return props.banks.find((b) => b.value === bankValue)?.icon_url ?? null;
}

const bankOptions = computed<DropdownOption<string>[]>(() => props.banks.map((b) => ({ value: b.value, label: t(b.label_key) })));
const accountTypeOptions = computed<DropdownOption<string>[]>(() =>
    props.accountTypes.map((item) => ({ value: item.value, label: t(item.label_key) })),
);
const currencyOptions = computed<DropdownOption<number>[]>(() => props.currencies.map((c) => ({ value: c.id, label: `${c.code} — ${c.name}` })));

function resetForm(): void {
    if (props.mode === 'edit' && props.account) {
        form.clearErrors();
        form.name = props.account.name;
        form.bank = props.account.bank;
        form.type = props.account.type;
        form.currency_id = props.account.currency_id;
        form.opening_balance = displayAmount(props.account.opening_balance);
        return;
    }

    form.reset();
    form.clearErrors();
    form.name = '';
    form.bank = defaultBankForKind(props.kind, props.banks);
    form.type = props.accountTypes[0]?.value ?? '';
    form.currency_id = props.currencies[0]?.id ?? null;
    form.opening_balance = '0,00';
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);

function onOpenChange(value: boolean): void {
    if (form.processing && !value) {
        return;
    }

    emit('update:open', value);
}

function submit(): void {
    form.opening_balance = normalizeAmount(form.opening_balance);

    const visit = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    };

    if (props.mode === 'edit' && props.account) {
        form.patch(route('accounts.update', props.account.id), visit);
        return;
    }

    form.post(route('accounts.store'), visit);
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <form class="grid gap-6" @submit.prevent="submit">
                <FormField for-id="account_name" :label="t('accounts.create.fields.name.label')" :error="form.errors.name">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="account_name"
                            v-model="form.name"
                            required
                            autofocus
                            :placeholder="t('accounts.create.fields.name.placeholder')"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <FormField for-id="account_bank" :label="t('accounts.create.fields.bank.label')" :error="form.errors.bank">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="account_bank"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.bank"
                            :options="bankOptions"
                            :placeholder="t('accounts.create.fields.bank.placeholder')"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.bank = value ?? '')"
                        >
                            <template #trigger-leading>
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded"
                                    :class="
                                        selectedBank?.value === 'cash'
                                            ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                            : 'bg-muted'
                                    "
                                    aria-hidden="true"
                                >
                                    <img
                                        v-if="selectedBank?.icon_url"
                                        :src="selectedBank.icon_url"
                                        :alt="t(selectedBank.label_key)"
                                        class="h-5 w-5 object-cover"
                                        loading="lazy"
                                    />
                                    <Icon
                                        v-else
                                        :name="selectedBank?.value === 'cash' ? 'coins' : 'landmark'"
                                        class="h-3.5 w-3.5"
                                        aria-hidden="true"
                                    />
                                </span>
                            </template>
                            <template #option-leading="{ option }">
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded"
                                    :class="
                                        option.value === 'cash'
                                            ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                            : 'bg-muted'
                                    "
                                    aria-hidden="true"
                                >
                                    <img
                                        v-if="resolveBankIconUrl(option.value)"
                                        :src="resolveBankIconUrl(option.value) ?? undefined"
                                        :alt="option.label"
                                        class="h-5 w-5 object-cover"
                                        loading="lazy"
                                    />
                                    <Icon v-else :name="option.value === 'cash' ? 'coins' : 'landmark'" class="h-3.5 w-3.5" aria-hidden="true" />
                                </span>
                            </template>
                        </DropdownSelect>
                    </template>
                </FormField>

                <FormField for-id="account_type" :label="t('accounts.create.fields.type.label')" :error="form.errors.type">
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="account_type"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.type"
                            :options="accountTypeOptions"
                            :placeholder="t('accounts.create.fields.type.placeholder')"
                            :disabled="form.processing"
                            @update:model-value="(value) => (form.type = value ?? '')"
                        >
                            <template #trigger-leading>
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded"
                                    :class="accountTypeIconClasses(selectedAccountType?.value)"
                                    aria-hidden="true"
                                >
                                    <Icon :name="selectedAccountType?.icon_name ?? 'wallet'" class="h-3.5 w-3.5" aria-hidden="true" />
                                </span>
                            </template>
                            <template #option-leading="{ option }">
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded"
                                    :class="accountTypeIconClasses(option.value)"
                                    aria-hidden="true"
                                >
                                    <Icon
                                        :name="accountTypes.find((item) => item.value === option.value)?.icon_name ?? 'wallet'"
                                        class="h-3.5 w-3.5"
                                        aria-hidden="true"
                                    />
                                </span>
                            </template>
                        </DropdownSelect>
                    </template>
                </FormField>

                <FormField
                    v-if="mode === 'create'"
                    for-id="account_currency"
                    :label="t('accounts.create.fields.currency.label')"
                    :error="form.errors.currency_id"
                >
                    <template #default="{ errorId, hasError }">
                        <DropdownSelect
                            id="account_currency"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                            :model-value="form.currency_id"
                            :options="currencyOptions"
                            :placeholder="t('accounts.create.fields.currency.placeholder')"
                            :disabled="form.processing || currencies.length === 0"
                            @update:model-value="(value) => (form.currency_id = value)"
                        >
                            <template #trigger-leading>
                                <Icon name="coins" class="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                            </template>
                        </DropdownSelect>
                    </template>
                </FormField>

                <FormField
                    for-id="account_opening_balance"
                    :label="t('accounts.create.fields.openingBalance.label')"
                    :error="form.errors.opening_balance"
                    :hint="mode === 'edit' ? t('accounts.edit.openingBalanceHint') : undefined"
                >
                    <template #default="{ errorId, hasError }">
                        <div class="relative">
                            <Input
                                id="account_opening_balance"
                                v-model="form.opening_balance"
                                inputmode="decimal"
                                :placeholder="t('accounts.create.fields.openingBalance.placeholder')"
                                :class="mode === 'edit' ? 'pr-10' : undefined"
                                :aria-invalid="hasError ? true : undefined"
                                :aria-describedby="hasError ? errorId : undefined"
                            />
                            <span
                                v-if="mode === 'edit'"
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                            >
                                {{ currencySymbol }}
                            </span>
                        </div>
                    </template>
                </FormField>

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
</template>
