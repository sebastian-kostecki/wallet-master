<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useI18n } from 'vue-i18n';

interface Props {
    class?: string;
}

defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground shadow-sm">
        <AppLogoIcon class="size-5 fill-current text-white dark:text-slate-950" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">{{ t('brand.name') }}</span>
        <span class="truncate text-xs leading-none text-muted-foreground group-data-[collapsible=icon]:hidden">{{ t('brand.shortPromise') }}</span>
    </div>
</template>
