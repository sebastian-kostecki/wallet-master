<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun, SunMoon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { appearance, updateAppearance } = useAppearance();

const nextAppearance = computed(() => {
    if (appearance.value === 'light') {
        return 'dark' as const;
    }

    if (appearance.value === 'dark') {
        return 'system' as const;
    }

    return 'light' as const;
});

const label = computed(() => t(`nav.theme.${appearance.value}`));

function cycleAppearance(): void {
    updateAppearance(nextAppearance.value);
}
</script>

<template>
    <button
        type="button"
        class="flex size-8 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-foreground/[0.06] hover:text-foreground"
        :aria-label="label"
        :title="label"
        @click="cycleAppearance"
    >
        <Sun v-if="appearance === 'light'" class="size-4" />
        <Moon v-else-if="appearance === 'dark'" class="size-4" />
        <SunMoon v-else class="size-4" />
    </button>
</template>
