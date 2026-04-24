<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Check, ChevronDown } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

type Currency = {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    precision: number;
};

type Option = {
    value: string;
    label: string;
    icon_url?: string | null;
    icon_name?: string | null;
};

const props = defineProps<{
    currencies: Currency[];
    banks: Option[];
    accountTypes: Option[];
}>();

const { t } = useI18n();

const initialCurrencyId = computed(() => props.currencies[0]?.id ?? null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('accounts.index.title'),
        href: '/accounts',
    },
    {
        title: t('accounts.create.title'),
        href: '/accounts/create',
    },
]);

const form = useForm<{
    name: string;
    bank: string;
    type: string;
    currency_id: number | null;
    opening_balance: string;
}>({
    name: '',
    bank: props.banks[0]?.value ?? '',
    type: props.accountTypes[0]?.value ?? '',
    currency_id: initialCurrencyId.value,
    opening_balance: '0,00',
});

const selectedBank = computed(() => props.banks.find((b) => b.value === form.bank) ?? null);
const selectedAccountType = computed(() => props.accountTypes.find((t) => t.value === form.type) ?? null);
const selectedCurrency = computed(() => props.currencies.find((c) => c.id === form.currency_id) ?? null);

function normalizeAmount(input: string) {
    return input.replace(/\s/g, '').replace(',', '.');
}

function submit() {
    form.opening_balance = normalizeAmount(form.opening_balance);
    form.post(route('accounts.store'));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('accounts.create.title')" />

        <template #headerActions>
            <Button variant="secondary" as-child>
                <Link :href="route('accounts.index')">{{ t('accounts.create.back') }}</Link>
            </Button>
        </template>

        <div class="flex flex-col gap-6 p-4">
            <div class="max-w-xl rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <form @submit.prevent="submit" class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('accounts.create.fields.name.label') }}</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            required
                            autofocus
                            :placeholder="t('accounts.create.fields.name.placeholder')"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="bank">{{ t('accounts.create.fields.bank.label') }}</Label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    id="bank"
                                    type="button"
                                    variant="outline"
                                    class="h-10 w-full justify-between px-3"
                                    :disabled="form.processing"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <img
                                            v-if="selectedBank?.icon_url"
                                            :src="selectedBank.icon_url"
                                            :alt="selectedBank.label"
                                            class="h-5 w-5 shrink-0 rounded object-contain"
                                            loading="lazy"
                                        />
                                        <Icon v-else name="landmark" class="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        <span class="truncate text-left">
                                            {{ selectedBank?.label ?? t('accounts.create.fields.bank.placeholder') }}
                                        </span>
                                    </span>
                                    <ChevronDown class="h-4 w-4 shrink-0 opacity-60" aria-hidden="true" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-[--radix-dropdown-menu-trigger-width] min-w-56">
                                <DropdownMenuItem
                                    v-for="bank in banks"
                                    :key="bank.value"
                                    class="cursor-pointer justify-between"
                                    @select="() => (form.bank = bank.value)"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <img
                                            v-if="bank.icon_url"
                                            :src="bank.icon_url"
                                            :alt="bank.label"
                                            class="h-5 w-5 shrink-0 rounded object-contain"
                                            loading="lazy"
                                        />
                                        <Icon v-else name="wallet" class="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        <span class="truncate">{{ bank.label }}</span>
                                    </span>
                                    <Check v-if="form.bank === bank.value" class="h-4 w-4 opacity-70" aria-hidden="true" />
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <InputError :message="form.errors.bank" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="type">{{ t('accounts.create.fields.type.label') }}</Label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    id="type"
                                    type="button"
                                    variant="outline"
                                    class="h-10 w-full justify-between px-3"
                                    :disabled="form.processing"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <Icon
                                            :name="selectedAccountType?.icon_name ?? 'wallet'"
                                            class="h-5 w-5 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <span class="truncate text-left">
                                            {{ selectedAccountType?.label ?? t('accounts.create.fields.type.placeholder') }}
                                        </span>
                                    </span>
                                    <ChevronDown class="h-4 w-4 shrink-0 opacity-60" aria-hidden="true" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-[--radix-dropdown-menu-trigger-width] min-w-56">
                                <DropdownMenuItem
                                    v-for="accountType in accountTypes"
                                    :key="accountType.value"
                                    class="cursor-pointer justify-between"
                                    @select="() => (form.type = accountType.value)"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <Icon
                                            :name="accountType.icon_name ?? 'wallet'"
                                            class="h-5 w-5 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <span class="truncate">{{ accountType.label }}</span>
                                    </span>
                                    <Check v-if="form.type === accountType.value" class="h-4 w-4 opacity-70" aria-hidden="true" />
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="currency">{{ t('accounts.create.fields.currency.label') }}</Label>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    id="currency"
                                    type="button"
                                    variant="outline"
                                    class="h-10 w-full justify-between px-3"
                                    :disabled="form.processing || currencies.length === 0"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <Icon name="coins" class="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        <span class="truncate text-left">
                                            <template v-if="selectedCurrency">
                                                {{ selectedCurrency.code }} — {{ selectedCurrency.name }}
                                            </template>
                                            <template v-else>{{ t('accounts.create.fields.currency.placeholder') }}</template>
                                        </span>
                                    </span>
                                    <ChevronDown class="h-4 w-4 shrink-0 opacity-60" aria-hidden="true" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-[--radix-dropdown-menu-trigger-width] min-w-56">
                                <DropdownMenuItem
                                    v-for="currency in currencies"
                                    :key="currency.id"
                                    class="cursor-pointer justify-between"
                                    @select="() => (form.currency_id = currency.id)"
                                >
                                    <span class="min-w-0 truncate">
                                        {{ currency.code }} — {{ currency.name }}
                                    </span>
                                    <Check v-if="form.currency_id === currency.id" class="h-4 w-4 opacity-70" aria-hidden="true" />
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <InputError :message="form.errors.currency_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="opening_balance">{{ t('accounts.create.fields.openingBalance.label') }}</Label>
                        <Input
                            id="opening_balance"
                            inputmode="decimal"
                            v-model="form.opening_balance"
                            :placeholder="t('accounts.create.fields.openingBalance.placeholder')"
                        />
                        <InputError :message="form.errors.opening_balance" />
                    </div>

                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="form.processing">{{ t('actions.save') }}</Button>
                        <p class="text-sm text-muted-foreground">{{ t('accounts.create.openingBalanceHint') }}</p>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

