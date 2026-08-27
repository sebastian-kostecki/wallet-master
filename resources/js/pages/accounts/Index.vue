<script setup lang="ts">
import AccountCard from '@/components/accounts/AccountCard.vue';
import AccountRow from '@/components/accounts/AccountRow.vue';
import AccountsViewToggle from '@/components/accounts/AccountsViewToggle.vue';
import AccountFormDialog from '@/components/accounts/modals/AccountFormDialog.vue';
import AdjustAccountBalanceDialog from '@/components/accounts/modals/AdjustAccountBalanceDialog.vue';
import DeleteAccountDialog from '@/components/accounts/modals/DeleteAccountDialog.vue';
import DepositCard from '@/components/deposits/DepositCard.vue';
import DepositRow from '@/components/deposits/DepositRow.vue';
import CloseDepositDialog from '@/components/deposits/modals/CloseDepositDialog.vue';
import DepositFormDialog from '@/components/deposits/modals/DepositFormDialog.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { accountKindFromUrl, accountsPageKindFromUrl, isWalletAccount } from '@/lib/accountKind';
import type { CategoryOption } from '@/lib/categories';
import { usePage } from '@inertiajs/vue3';
import { PiggyBank, Wallet } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
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
    opening_balance: string;
    currency_id: number;
    bank: string;
    bank_icon_url: string | null;
    type: string;
    type_label_key: string;
    currency: Currency;
};

type Deposit = {
    id: number;
    name: string | null;
    principal: string;
    opened_at: string;
    matures_at: string | null;
    status: 'active' | 'closed';
    closed_at: string | null;
    source_account_id: number;
    has_interest: boolean;
    currency: Currency | null;
};

type BankAccount = {
    id: number;
    name: string;
    bank_icon_url?: string | null;
};

const props = withDefaults(
    defineProps<{
        accounts: Account[];
        deposits?: Deposit[];
        bank_accounts?: BankAccount[];
        income_categories?: CategoryOption[];
        currencies: Array<{
            id: number;
            code: string;
            name: string;
            symbol: string | null;
            precision: number;
        }>;
        banks: Array<{
            value: string;
            label_key: string;
            icon_url?: string | null;
            icon_name?: string | null;
        }>;
        accountTypes: Array<{
            value: string;
            label_key: string;
            icon_url?: string | null;
            icon_name?: string | null;
        }>;
    }>(),
    {
        deposits: () => [],
        bank_accounts: () => [],
        income_categories: () => [],
    },
);

const { t } = useI18n();
const page = usePage();
const pageKind = computed(() => accountsPageKindFromUrl(page.url));
const kind = computed(() => accountKindFromUrl(page.url));

const visibleAccounts = computed(() => {
    return props.accounts.filter((account) => (kind.value === 'wallets' ? isWalletAccount(account) : !isWalletAccount(account)));
});

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

function isoToDdMmYyyy(input: string | null): string {
    if (input === null || input.trim() === '') {
        return '—';
    }

    const trimmed = input.trim();
    const datePart = /^\d{4}-\d{2}-\d{2}/.test(trimmed) ? trimmed.slice(0, 10) : trimmed;
    const parts = datePart.split('-');

    if (parts.length !== 3) {
        return input;
    }

    const [yyyy, mm, dd] = parts;

    return `${dd}-${mm}-${yyyy}`;
}

const accountTypeIcon = computed(() => {
    return {
        checking: Wallet,
        savings: PiggyBank,
    } as const;
});

function resolveAccountTypeIcon(type: string) {
    return accountTypeIcon.value[type as keyof typeof accountTypeIcon.value] ?? Wallet;
}

type ViewMode = 'grid' | 'list';
const viewMode = ref<ViewMode>('grid');

onMounted(() => {
    const storageKey = pageKind.value === 'deposits' ? 'deposits.viewMode' : 'accounts.viewMode';
    const saved = localStorage.getItem(storageKey);
    if (saved === 'grid' || saved === 'list') {
        viewMode.value = saved;
    }
});

function setViewMode(mode: ViewMode) {
    viewMode.value = mode;
    const storageKey = pageKind.value === 'deposits' ? 'deposits.viewMode' : 'accounts.viewMode';
    localStorage.setItem(storageKey, mode);
}

const formDialogOpen = ref(false);
const formDialogMode = ref<'create' | 'edit'>('create');
const editingAccountId = ref<number | null>(null);
const editingAccount = computed(() => props.accounts.find((account) => account.id === editingAccountId.value) ?? null);

function openCreateDialog() {
    formDialogMode.value = 'create';
    editingAccountId.value = null;
    formDialogOpen.value = true;
}

function openEditDialog(accountId: number) {
    formDialogMode.value = 'edit';
    editingAccountId.value = accountId;
    formDialogOpen.value = true;
}

const depositFormDialogOpen = ref(false);
const depositFormDialogMode = ref<'create' | 'edit'>('create');
const editingDepositId = ref<number | null>(null);
const editingDeposit = computed(() => props.deposits.find((deposit) => deposit.id === editingDepositId.value) ?? null);

function openCreateDepositDialog() {
    depositFormDialogMode.value = 'create';
    editingDepositId.value = null;
    depositFormDialogOpen.value = true;
}

function openEditDepositDialog(depositId: number) {
    depositFormDialogMode.value = 'edit';
    editingDepositId.value = depositId;
    depositFormDialogOpen.value = true;
}

const closingDepositId = ref<number | null>(null);
const closingDeposit = computed(() => props.deposits.find((deposit) => deposit.id === closingDepositId.value) ?? null);
const closeDepositDialogOpen = ref(false);

function openCloseDepositDialog(depositId: number) {
    closingDepositId.value = depositId;
    closeDepositDialogOpen.value = true;
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

const headerAddLabel = computed(() => {
    if (pageKind.value === 'deposits') {
        return t('accounts.index.addDeposit');
    }

    return t(kind.value === 'wallets' ? 'accounts.index.addWallet' : 'accounts.index.addAccount');
});

function onHeaderAddClick() {
    if (pageKind.value === 'deposits') {
        openCreateDepositDialog();
        return;
    }

    openCreateDialog();
}
</script>

<template>
    <AppLayout>
        <template #headerActions>
            <Button @click="onHeaderAddClick">{{ headerAddLabel }}</Button>
        </template>

        <template #headerTabsTrailing>
            <AccountsViewToggle :model-value="viewMode" @update:model-value="setViewMode" />
        </template>

        <div class="flex flex-col gap-6">
            <template v-if="pageKind === 'deposits'">
                <section
                    v-if="deposits.length === 0"
                    role="region"
                    aria-labelledby="deposits-empty-heading"
                    class="flex flex-col items-center py-16 text-center"
                >
                    <h2 id="deposits-empty-heading" class="text-2xl font-medium tracking-tight">
                        {{ t('accounts.index.empty.deposits.heading') }}
                    </h2>
                    <p class="mt-2 max-w-sm text-sm text-muted-foreground">{{ t('accounts.index.empty.deposits.message') }}</p>
                    <Button class="mt-4" @click="openCreateDepositDialog">{{ t('accounts.index.empty.deposits.cta') }}</Button>
                </section>

                <div v-if="deposits.length > 0 && viewMode === 'grid'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <DepositCard
                        v-for="deposit in deposits"
                        :key="deposit.id"
                        :deposit="deposit"
                        :format-money="formatMoney"
                        :format-date="isoToDdMmYyyy"
                        @edit="openEditDepositDialog"
                        @close="openCloseDepositDialog"
                    />
                </div>

                <div v-else-if="deposits.length > 0" class="overflow-hidden rounded-2xl border border-transparent bg-card">
                    <DepositRow
                        v-for="deposit in deposits"
                        :key="deposit.id"
                        class="border-b border-border/50 last:border-b-0"
                        :deposit="deposit"
                        :format-money="formatMoney"
                        :format-date="isoToDdMmYyyy"
                        @edit="openEditDepositDialog"
                        @close="openCloseDepositDialog"
                    />
                </div>
            </template>

            <template v-else>
                <section
                    v-if="visibleAccounts.length === 0"
                    role="region"
                    aria-labelledby="accounts-empty-heading"
                    class="flex flex-col items-center py-16 text-center"
                >
                    <h2 id="accounts-empty-heading" class="text-2xl font-medium tracking-tight">
                        {{ t(`accounts.index.empty.${kind}.heading`) }}
                    </h2>
                    <p class="mt-2 max-w-sm text-sm text-muted-foreground">{{ t(`accounts.index.empty.${kind}.message`) }}</p>
                    <Button class="mt-4" @click="openCreateDialog">{{ t(`accounts.index.empty.${kind}.cta`) }}</Button>
                </section>

                <div v-if="visibleAccounts.length > 0 && viewMode === 'grid'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <AccountCard
                        v-for="account in visibleAccounts"
                        :key="account.id"
                        :account="account"
                        :format-money="formatMoney"
                        :account-type-icon="resolveAccountTypeIcon(account.type)"
                        :delete-disabled="deleteProcessing"
                        :adjust-disabled="adjustProcessing"
                        @delete="openDeleteDialog"
                        @edit="openEditDialog"
                        @adjust-balance="openAdjustBalance"
                    />
                </div>

                <div v-else-if="visibleAccounts.length > 0" class="overflow-hidden rounded-2xl border border-transparent bg-card">
                    <AccountRow
                        v-for="account in visibleAccounts"
                        :key="account.id"
                        class="border-b border-border/50 last:border-b-0"
                        :account="account"
                        :format-money="formatMoney"
                        :account-type-icon="resolveAccountTypeIcon(account.type)"
                        :delete-disabled="deleteProcessing"
                        :adjust-disabled="adjustProcessing"
                        @delete="openDeleteDialog"
                        @edit="openEditDialog"
                        @adjust-balance="openAdjustBalance"
                    />
                </div>
            </template>
        </div>

        <template v-if="pageKind !== 'deposits'">
            <AccountFormDialog
                v-model:open="formDialogOpen"
                :mode="formDialogMode"
                :kind="kind"
                :account="editingAccount"
                :currencies="currencies"
                :banks="banks"
                :account-types="accountTypes"
            />

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
        </template>

        <template v-else>
            <DepositFormDialog
                v-model:open="depositFormDialogOpen"
                :mode="depositFormDialogMode"
                :deposit="editingDeposit"
                :bank-accounts="bank_accounts"
            />

            <CloseDepositDialog
                v-model:open="closeDepositDialogOpen"
                :deposit="closingDeposit"
                :bank-accounts="bank_accounts"
                :income-categories="income_categories"
            />
        </template>
    </AppLayout>
</template>
