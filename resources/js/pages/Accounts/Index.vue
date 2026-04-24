<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { buttonVariants } from '@/components/ui/button';
import AccountsSummaryCard from '@/components/accounts/AccountsSummaryCard.vue';
import AccountCard from '@/components/accounts/AccountCard.vue';
import AdjustAccountBalanceDialog from '@/components/accounts/modals/AdjustAccountBalanceDialog.vue';
import DeleteAccountDialog from '@/components/accounts/modals/DeleteAccountDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { PiggyBank, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Currency = {
    id: number;
    code: string;
    symbol: string | null;
    precision: number;
};

type Account = {
    id: number;
    name: string;
    current_balance: string;
    bank: string;
    bank_icon_url: string | null;
    type: string;
    type_label: string;
    currency: Currency;
};

const props = defineProps<{
    accounts: Account[];
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('accounts.index.title'),
        href: '/accounts',
    },
]);

const money = new Intl.NumberFormat('pl-PL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function formatMoney(value: string) {
    const parsed = Number(value);
    if (Number.isNaN(parsed)) {
        return value;
    }

    return money.format(parsed);
}

const accountTypeIcon = computed(() => {
    return {
        ror: Wallet,
        savings: PiggyBank,
    } as const;
});

function resolveAccountTypeIcon(type: string) {
    return accountTypeIcon.value[type as keyof typeof accountTypeIcon.value] ?? Wallet;
}

function goToEditAccount(accountId: number) {
    router.visit(route('accounts.edit', accountId));
}

const adjustingAccountId = ref<number | null>(null);
const selectedAccount = computed(() => props.accounts.find((a) => a.id === adjustingAccountId.value) ?? null);
const adjustDialogOpen = ref(false);
const adjustProcessing = ref(false);

function openAdjustBalance(accountId: number) {
    adjustingAccountId.value = accountId;
    adjustDialogOpen.value = true;
}

const deletingAccountId = ref<number | null>(null);
const deletingAccount = computed(() => props.accounts.find((a) => a.id === deletingAccountId.value) ?? null);
const deleteDialogOpen = ref(false);
const deleteProcessing = ref(false);

function openDeleteDialog(accountId: number) {
    deletingAccountId.value = accountId;
    deleteDialogOpen.value = true;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('accounts.index.title')" />

        <template #headerActions>
            <Button as-child>
                <Link :href="route('accounts.create')">{{ t('accounts.index.addAccount') }}</Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <AccountsSummaryCard :accounts="accounts" />

            <div v-if="accounts.length === 0" class="rounded-xl border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                <p class="text-sm text-muted-foreground">{{ t('accounts.index.empty.message') }}</p>
                <Link :href="route('accounts.create')" :class="cn(buttonVariants({}), 'mt-4')">{{ t('accounts.index.empty.cta') }}</Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <AccountCard
                    v-for="account in accounts"
                    :key="account.id"
                    :account="account"
                    :format-money="formatMoney"
                    :account-type-icon="resolveAccountTypeIcon(account.type)"
                    :delete-disabled="deleteProcessing"
                    :adjust-disabled="adjustProcessing"
                    @delete="openDeleteDialog"
                    @edit="goToEditAccount"
                    @adjust-balance="openAdjustBalance"
                />
            </div>
        </div>

        <AdjustAccountBalanceDialog
            v-model:open="adjustDialogOpen"
            :account-id="adjustingAccountId"
            :initial-new-balance="selectedAccount?.current_balance ?? null"
            @processing="(value: any) => (adjustProcessing = value)"
            @success="adjustingAccountId = null"
        />

        <DeleteAccountDialog
            v-model:open="deleteDialogOpen"
            :account-id="deletingAccountId"
            :account-name="deletingAccount?.name ?? null"
            :current-balance="deletingAccount?.current_balance ?? null"
            :currency-symbol="deletingAccount?.currency.symbol ?? null"
            :format-money="formatMoney"
            @processing="(value: any) => (deleteProcessing = value)"
            @success="deletingAccountId = null"
        />
    </AppLayout>
</template>
