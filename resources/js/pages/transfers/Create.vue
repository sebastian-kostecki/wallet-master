<script setup lang="ts">
import DatePickerInput from '@/components/forms/DatePickerInput.vue';
import DropdownSelect, { type DropdownOption } from '@/components/forms/DropdownSelect.vue';
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { normalizeAmount } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRightLeft, AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

type Account = {
    id: number;
    name: string;
    currency_id: number;
    is_deleted: boolean;
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

const accountOptions = computed<DropdownOption<number>[]>(() =>
    props.accounts.map((a) => ({
        value: a.id,
        label: a.is_deleted ? `${a.name} (${t('transfers.form.deletedSuffix')})` : a.name,
        disabled: a.is_deleted,
    })),
);

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
const hasFormError = computed(() => Boolean(form.hasErrors && !form.errors.from_account_id && !form.errors.to_account_id && !form.errors.date && !form.errors.amount));

const fromAccount = computed(() => props.accounts.find((a) => a.id === form.from_account_id) ?? null);
const toAccount = computed(() => props.accounts.find((a) => a.id === form.to_account_id) ?? null);

const isSameAccount = computed(() => form.from_account_id !== null && form.to_account_id !== null && form.from_account_id === form.to_account_id);

function submit() {
    if (!canTransfer.value) {
        return;
    }

    form.amount = normalizeAmount(form.amount);
    form.post(route('transfers.store'), {
        onSuccess: () => {},
        onError: (errors) => {
            if (Object.keys(errors).length > 0) {
                return;
            }

            toast.dismiss();
            toast.error(t('transfers.toast.genericError'));
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('transfers.create.title')" />

        <template #headerActions>
            <Button variant="secondary" as-child>
                <Link :href="route('transactions.index') + currentSearch">{{ t('actions.cancel') }}</Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <div class="max-w-2xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/30 dark:text-blue-400">
                        <ArrowRightLeft class="h-4 w-4" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-lg font-semibold">{{ t('transfers.create.title') }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ t('transfers.create.description') }}
                        </p>
                    </div>
                </div>

                <div v-if="!canTransfer" class="mt-6 rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 text-sm dark:border-sidebar-border">
                    <p class="text-muted-foreground">{{ t('transfers.create.empty') }}</p>
                    <div class="mt-4">
                        <Button as-child>
                            <Link :href="route('accounts.create')">{{ t('accounts.index.addAccount') }}</Link>
                        </Button>
                    </div>
                </div>

                <form v-else @submit.prevent="submit" class="mt-6 grid gap-6" :aria-busy="form.processing ? 'true' : 'false'">
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
                            />
                            <div v-if="fromAccount?.is_deleted" class="mt-2 flex items-start gap-2 text-xs text-muted-foreground">
                                <AlertTriangle class="mt-0.5 h-4 w-4" aria-hidden="true" />
                                <span>{{ t('transfers.form.deletedNotice') }}</span>
                            </div>
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
                            />
                            <div v-if="toAccount?.is_deleted" class="mt-2 flex items-start gap-2 text-xs text-muted-foreground">
                                <AlertTriangle class="mt-0.5 h-4 w-4" aria-hidden="true" />
                                <span>{{ t('transfers.form.deletedNotice') }}</span>
                            </div>
                        </FormField>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <FormField for-id="amount" :label="t('transfers.form.amount')" :error="form.errors.amount">
                            <Input id="amount" v-model="form.amount" inputmode="decimal" :disabled="form.processing" :placeholder="t('transfers.form.amountPlaceholder')" />
                        </FormField>

                        <FormField for-id="date" :label="t('transfers.form.date')" :error="form.errors.date">
                            <DatePickerInput id="date" :model-value="form.date" :disabled="form.processing" @update:model-value="(value) => (form.date = value)" />
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

                    <div class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 text-sm text-muted-foreground dark:border-sidebar-border">
                        {{ t('transfers.form.hint') }}
                    </div>

                    <div v-if="isSameAccount" :id="formErrorId" class="text-sm text-rose-600 dark:text-rose-400">
                        {{ t('transfers.form.sameAccountError') }}
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Button variant="secondary" as-child :disabled="form.processing">
                            <Link :href="route('transactions.index') + currentSearch">{{ t('actions.cancel') }}</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing || isSameAccount">
                            {{ form.processing ? t('transfers.form.saving') : t('transfers.form.submit') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

