<script setup lang="ts">
import { formatMoney, type CurrencyDisplay } from '@/lib/formatMoney';
import { useI18n } from 'vue-i18n';

defineProps<{
    saved: string;
    released: string;
    currency: CurrencyDisplay;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="space-y-1 tabular-nums">
        <div>
            <span class="text-xs text-muted-foreground">+ {{ t('budget.monthly.saved') }}</span>
            <span class="ml-1">{{ formatMoney(saved, currency) }}</span>
        </div>
        <div>
            <span class="text-xs text-muted-foreground">− {{ t('budget.monthly.released') }}</span>
            <span class="ml-1">{{ formatMoney(released, currency) }}</span>
        </div>
    </div>
</template>
