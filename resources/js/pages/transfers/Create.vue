<script setup lang="ts">
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { normalizeAmount } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Account = {
    id: number;
    name: string;
    currency_id: number;
    bank: string;
    is_deleted: boolean;
    bank_icon_url: string | null;
};

const props = defineProps<{
    accounts: Account[];
}>();

const { t } = useI18n();
const page = usePage() as any;

const currentSearch = computed(() => {
    const url = page.url as string;
    const idx = url.indexOf('?');
    return idx >= 0 ? url.slice(idx) : '';
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('transactions.index.title'),
        href: '/transactions',
    },
    {
        title: t('transfers.create.title'),
        href: '/transfers/create',
    },
]);

const selectableAccounts = computed(() => props.accounts.filter((a) => !a.is_deleted));
const canTransfer = computed(() => selectableAccounts.value.length >= 2);

const accountOptions = computed<DropdownOption<number>[]>(() => selectableAccounts.value.map((a) => ({ value: a.id, label: a.name })));
const accountsById = computed(() => new Map(selectableAccounts.value.map((a) => [a.id, a])));

function isCashBank(bank: string | null | undefined): boolean {
    return (bank ?? '').toLowerCase() === 'cash';
}

function todayDdMmYyyy(): string {
    const now = new Date();
    const d = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));

    const dd = String(d.getUTCDate()).padStart(2, '0');
    const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
    const yyyy = d.getUTCFullYear();

    return `${dd}-${mm}-${yyyy}`;
}

const defaultFromId = computed(() => selectableAccounts.value[0]?.id ?? null);
const defaultToId = computed(() => selectableAccounts.value[1]?.id ?? null);

const form = useForm<{
    from_account_id: number | null;
    to_account_id: number | null;
    date: string;
    amount: string;
    description: string;
}>({
    from_account_id: defaultFromId.value,
    to_account_id: defaultToId.value,
    date: todayDdMmYyyy(),
    amount: '0,00',
    description: '',
});

const formErrorId = 'transfer-form-error';

const fromAccount = computed(() => (form.from_account_id !== null ? accountsById.value.get(form.from_account_id) ?? null : null));
const toAccount = computed(() => (form.to_account_id !== null ? accountsById.value.get(form.to_account_id) ?? null : null));

const isSameAccount = computed(() => form.from_account_id !== null && form.to_account_id !== null && form.from_account_id === form.to_account_id);

function submit() {
    if (!canTransfer.value) {
        return;
    }

    form.amount = normalizeAmount(form.amount);
    form.post(route('transfers.store'), {
        onSuccess: () => {},
        onError: () => {},
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('transfers.create.title')" />

        <div class="flex flex-col gap-6 p-4">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <div v-if="!canTransfer" class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 text-sm dark:border-sidebar-border">
                        <p class="text-muted-foreground">{{ t('transfers.create.empty') }}</p>
                        <div class="mt-4">
                            <Button as-child>
                                <Link :href="route('accounts.create')">{{ t('accounts.index.addAccount') }}</Link>
                            </Button>
                        </div>
                    </div>

                    <form v-else @submit.prevent="submit" class="grid gap-6" :aria-busy="form.processing ? 'true' : 'false'">
                        <div class="grid gap-4 md:grid-cols-2">
                            <FormField for-id="from_account_id" :label="t('transfers.form.fromAccount')" :error="form.errors.from_account_id">
                                <DropdownSelect
                                    id="from_account_id"
                                    :model-value="form.from_account_id"
                                    :options="accountOptions"
                                    :placeholder="t('transfers.form.selectPlaceholder')"
                                    :disabled="form.processing"
                                    :aria-invalid="Boolean(form.errors.from_account_id || (isSameAccount && form.to_account_id !== null))"
                                    :aria-describedby="form.errors.from_account_id ? 'from_account_id-error' : undefined"
                                    @update:model-value="(value) => (form.from_account_id = value)"
                                >
                                    <template #trigger-leading>
                                        <span
                                            v-if="fromAccount"
                                            class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                            :class="
                                                isCashBank(fromAccount.bank)
                                                    ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                                    : 'bg-muted'
                                            "
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
                                            :class="
                                                isCashBank(accountsById.get(option.value)?.bank)
                                                    ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                                    : 'bg-muted'
                                            "
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
                            </FormField>

                            <FormField for-id="to_account_id" :label="t('transfers.form.toAccount')" :error="form.errors.to_account_id">
                                <DropdownSelect
                                    id="to_account_id"
                                    :model-value="form.to_account_id"
                                    :options="accountOptions"
                                    :placeholder="t('transfers.form.selectPlaceholder')"
                                    :disabled="form.processing"
                                    :aria-invalid="Boolean(form.errors.to_account_id || isSameAccount)"
                                    :aria-describedby="form.errors.to_account_id ? 'to_account_id-error' : isSameAccount ? formErrorId : undefined"
                                    @update:model-value="(value) => (form.to_account_id = value)"
                                >
                                    <template #trigger-leading>
                                        <span
                                            v-if="toAccount"
                                            class="inline-flex h-5 w-5 items-center justify-center overflow-hidden rounded"
                                            :class="
                                                isCashBank(toAccount.bank)
                                                    ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                                    : 'bg-muted'
                                            "
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
                                            :class="
                                                isCashBank(accountsById.get(option.value)?.bank)
                                                    ? 'bg-gradient-to-br from-amber-100 to-orange-200 text-amber-800 dark:from-amber-950/40 dark:to-orange-950/40 dark:text-amber-300'
                                                    : 'bg-muted'
                                            "
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
                            </FormField>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <FormField for-id="amount" :label="t('transfers.form.amount')" :error="form.errors.amount">
                                <Input
                                    id="amount"
                                    v-model="form.amount"
                                    inputmode="decimal"
                                    :disabled="form.processing"
                                    :placeholder="t('transfers.form.amountPlaceholder')"
                                />
                            </FormField>

                            <FormField for-id="date" :label="t('transfers.form.date')" :error="form.errors.date">
                                <DatePickerInput
                                    id="date"
                                    :model-value="form.date"
                                    :disabled="form.processing"
                                    @update:model-value="(value) => (form.date = value)"
                                />
                            </FormField>
                        </div>

                        <FormField for-id="description" :label="t('transfers.form.descriptionOptional')" :error="form.errors.description">
                            <textarea
                                id="description"
                                v-model="form.description"
                                :disabled="form.processing"
                                rows="3"
                                class="flex min-h-[84px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            />
                        </FormField>

                        <div v-if="isSameAccount" :id="formErrorId" class="text-sm text-rose-600 dark:text-rose-400">
                            {{ t('transfers.form.sameAccountError') }}
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <Button variant="secondary" as-child>
                                <Link :href="route('transactions.index') + currentSearch">{{ t('actions.cancel') }}</Link>
                            </Button>

                            <Button type="submit" :disabled="form.processing || isSameAccount">
                                {{ form.processing ? t('transfers.form.saving') : t('transfers.form.submit') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h2 class="text-base font-semibold">{{ t('transfers.create.title') }}</h2>
                    <div class="mt-3 grid gap-3 text-sm text-muted-foreground">
                        <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                            {{ t('transfers.create.description') }}
                        </div>
                        <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                            {{ t('transfers.form.hint') }}
                        </div>
                        <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                            {{ t('transfers.form.sameAccountError') }}
                        </div>
                        <div v-if="!canTransfer" class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border">
                            {{ t('transfers.create.empty') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

