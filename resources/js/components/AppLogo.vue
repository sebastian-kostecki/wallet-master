<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useI18n } from 'vue-i18n';

interface Props {
    class?: string;
    compact?: boolean;
}

withDefaults(defineProps<Props>(), {
    compact: false,
});

const { t } = useI18n();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-xl bg-primary text-primary-foreground">
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <div class="ml-1 grid min-w-0 flex-1 text-left text-sm group-data-[collapsible=icon]:hidden">
        <span class="truncate font-semibold leading-none tracking-tight">{{ t('brand.name') }}</span>
        <span v-if="!compact" class="truncate text-xs leading-none text-muted-foreground">
            {{ t('brand.shortPromise') }}
        </span>
    </div>
</template>
